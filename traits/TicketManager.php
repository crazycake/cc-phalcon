<?php
/**
 * Ticket Storage Trait
 * This class manages tickets resources to AWS S3.
 * Files are uploaded automatically to S3 in a defined URI.
 * Requires a Frontend or Backend Module with CoreController
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Traits;

//imports
use Phalcon\Exception;
use CrazyCake\Utils\StorageS3;  //AWS S3 File Storage helper
use CrazyCake\Qr\QRMaker;       //CrazyCake QR
use CrazyCake\Utils\PdfHelper;  //PDF generator
use CrazyCake\Utils\DateHelper; //Date Helper functions

trait TicketManager
{
	/**
     * abstract required methods
     */
    abstract public function setConfigurations();

    /**
     * Config var
     * @var array
     */
    public $storageConfig;

    /**
     * AWS S3 helper
     * @var object
     */
    protected $s3;

    /**
     * QR settings
     * @var array
     */
    protected $qr_settings;

    /**
     * PDF settings
     * @var array
     */
    protected $pdf_settings;

    /**
     * MIME types supported
     * @var array
     */
    protected static $MIME_TYPES = array(
        "png" => "image/png",
        "pdf" => "application/pdf"
    );

    /**
     * Default S3 URI path
     * @var string
     */
    protected static $DEFAULT_S3_URI = "tickets";

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Init S3 Helper library
     */
    public function initStorage()
    {
        //instance library
        $this->s3 = new StorageS3($this->config->app->awsAccessKey,
                                  $this->config->app->awsSecretKey,
                                  $this->config->app->awsS3Bucket);
        //set PDF settings
        $this->pdf_settings = array(
            'app' => $this->config->app,
        );
    }

    /**
     * Binary Image - Outputs QR ticket image as binary with Content-Type png
     * @param int $user_id The user id
     * @param string $code The ticket associated code
     * @return binary
     */
    public function getTicketQR($user_id = 0, $code = "")
    {
        try {

            //validates that ticket belongs to user, get anonymous function from settings
            $getUserTicket = $this->storageConfig["getUserTicketFunction"];

            if(!is_callable($getUserTicket))
                throw new Exception("Invalid 'get user ticket' function");

            $user_ticket = $getUserTicket($user_id, $code);

            if(!$user_ticket)
                throw new Exception("Invalid ticket: $code for userId: $user_id.");

            $ticket_filename = $user_ticket->code.".png";
            $s3_path         = self::$DEFAULT_S3_URI."/".$user_id."/".$ticket_filename;
            //get image in S3
            $binary = $this->s3->getObject($s3_path, true);

            if(!$binary)
                throw new Exception("S3 could't find binary file for ticket code: $code (S3 path: $s3_path)");

            //sends file to buffer
            $this->_sendFileToBuffer($binary, self::$MIME_TYPES['png']);
        }
        catch (Exception $e) {
            //fallback for file
            $this->logger->error("TicketStorage::getTicket -> Error loading QR code: $code, err:".$e->getMessage());
        }
    }

    /**
     * Get Invoice PDFs, if many they are merged
     * @param int $user_id The user id
     * @param array $buy_orders An array of buy orders
     * @return binary
     */
    public function getInvoice($user_id = 0, $buy_orders)
    {
        try {
            if(empty($buy_orders))
                throw new Exception("Empty buy_orders parameter");

            $binary = null;

            //for multiple buy orders, merge pdf files
            foreach ($buy_orders as $buy_order) {

                $invoice_filename = $buy_order.".pdf";
                $s3_path          = self::$DEFAULT_S3_URI."/".$user_id."/".$invoice_filename;
                //get image in S3
                $binary = $this->s3->getObject($s3_path, true);
                break;
            }

            if(is_null($binary))
                throw new Exception("No pdf files found with buy orders: ". print_r($buy_orders, true));

            //sends file to buffer
            $this->_sendFileToBuffer($binary, self::$MIME_TYPES['pdf']);
        }
        catch (Exception $e) {
            //fallback for file
            $this->logger->error("TicketStorage::getInvoice -> Error loading PDF file: $code, err:".$e->getMessage());
            //redirect to fallback
        }
    }

    /**
     * Hanlder - Generates QR for multiple tickets
     * @param mixed [object|array] $userTickets A user ticket object or an array of objects
     * @return mixed
     */
    public function generateQRForUserTickets($user_id, $userTickets)
    {
        //set qr settings
        $this->qr_settings = $this->storageConfig['qr_settings'];

        //instance qr maker with log & cache paths
        $qr_maker = new QRMaker($this->config->directories->logs, $this->config->directories->cache);

        //handle exceptions
        $result  = new \stdClass();
        $objects = array();

        try {

            //loop through each ticket
            foreach ($userTickets as $ticket) {

                if(!isset($ticket->code))
                    throw new Exception("Invalid ticket");

                //set configs
                $qr_filename = $ticket->code.".png";
                $qr_savepath = $this->storageConfig['local_temp_path'].$qr_filename;
                $s3_path     = self::$DEFAULT_S3_URI."/".$user_id."/".$qr_filename;

                //set extended qr data
                $this->qr_settings["data"]     = $ticket->qr_hash;
                $this->qr_settings["savename"] = $qr_savepath;

                //generate QR
                $qr_maker->generate($this->qr_settings);

                //PUSH IT to S3 as private resource
                $this->s3->putObject($qr_savepath, $s3_path, true);
                //register Object
                array_push($objects, $ticket);
            }
        }
        catch (\S3Exception $e) {
            $result->error = $e->getMessage();
        }
        catch (Exception $e) {
            $result->error= $e->getMessage();
        }

        //append Objects
        $result->objects = $objects;

        if(isset($result->error))
            $this->logger->error('TicketStorage::generateQRForUserTicket -> Error while generating and storing QR, err:'.$result->error);

        return $result;
    }

    /**
     * Hanlder - Generates PDF for ticket and invoice attached
     * @param int $user_id The user Id
     * @param object $checkout The checkout object (including transaction props)
     * @param array $userTicketIds An array of user ticket IDs
     * @return mixed
     */
    public function generateInvoiceForUserCheckout($user_id, $checkout, $userTicketIds = array())
    {
        //handle exceptions
        $result = new \stdClass();

        try {
            //get user model class
            $users_class = $this->getModuleClassName('users');
            //get model class
            $getUserTicketsUI = $this->storageConfig["getUserTicketsUIFunction"];

            if(!is_callable($getUserTicketsUI))
                throw new Exception("Invalid 'get user ticket UI' function");

            //get user by session
            $user = $users_class::getObjectById($user_id);
            //get ticket objects with UI properties
            $tickets = $getUserTicketsUI($user->id, $userTicketIds);

            if(!$tickets)
                throw new Exception("No tickets found for userID: ".$user->id." & buyOrder: ".$checkout->buyOrder);

            //set file paths
            $pdf_filename = $checkout->buyOrder.".pdf";
            $output_path  = $this->storageConfig['local_temp_path'].$pdf_filename;
            $s3_path      = self::$DEFAULT_S3_URI."/".$user->id."/".$pdf_filename;

            //set extended pdf data
            $this->pdf_settings["data_date"]     = DateHelper::getTranslatedCurrentDate();
            $this->pdf_settings["data_user"]     = $user;
            $this->pdf_settings["data_tickets"]  = $tickets;
            $this->pdf_settings["data_checkout"] = $checkout;
            $this->pdf_settings["data_storage"]  = $this->storageConfig['local_temp_path'];

            //get template
            $html_raw = $this->simpleView->render($this->storageConfig['ticket_pdf_template_view'], $this->pdf_settings);

            //PDF generator (this is a heavy task for quick client response)
            $result->binary = (new PdfHelper())->generatePdfFileFromHtml($html_raw, $output_path, true);

            //upload pdf file to S3
            $this->s3->putObject($output_path, $s3_path, true);
        }
        catch (\S3Exception $e) {
            $result->error = $e->getMessage();
        }
        catch (Exception $e) {
            $result->error = $e->getMessage();
        }

        //delete generated local files
        if(APP_ENVIRONMENT !== "development")
            $this->_deleteTempFiles();

        if(isset($result->error)) {
            $this->logger->error('TicketStorage::generatePDFForTicket -> Error while generating and storing PDF: '.$result->error);
            return $result;
        }

        return $result;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Deletes temporary files in Local directory
     */
    private function _deleteTempFiles()
    {
        $path = $this->storageConfig['local_temp_path'];

        foreach (self::$MIME_TYPES as $key => $value) {
            array_map('unlink', glob( "$path*.$key"));
        }
    }
}
