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
        $this->s3 = new StorageS3($this->config->app->aws->accessKey,
                                  $this->config->app->aws->secretKey,
                                  $this->config->app->aws->s3Bucket);
        //set PDF settings
        $this->pdf_settings = [
            'app' => $this->config->app,
        ];
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
                throw new Exception("S3 lib could't find binary file for ticket code: $code (S3 path: $s3_path)");

            //sends file to buffer
            $this->_sendFileToBuffer($binary, self::$MIME_TYPES['png']);
        }
        catch (Exception $e) {
            //fallback for file
            $this->logger->error("TicketStorage::getTicket -> Error loading QR code: $code, err: ".$e->getMessage());
            return false;
        }
    }

    /**
     * Moves a QR ticket from S3 storage from one folder to other
     * @param int $src_user_id The source user id
     * @param string $src_code The source ticket associated code
     * @param int $dst_user_id The destination user id
     * @return boolean $moved Return true if object was moved to destination.
     */
    public function moveTicketQR($src_user_id = 0, $src_code = "", $dst_user_id = 0)
    {
        try {
            //validates that ticket belongs to user, get anonymous function from settings
            $getUserTicket = $this->storageConfig["getUserTicketFunction"];

            if(!is_callable($getUserTicket))
                throw new Exception("Invalid 'get user ticket' function");

            $user_ticket = $getUserTicket($src_user_id, $src_code);

            if(!$user_ticket)
                throw new Exception("Invalid ticket: $src_code for userId: $src_user_id.");

            $ticket_filename = $user_ticket->code.".png";
            $src_s3_path     = self::$DEFAULT_S3_URI."/".$src_user_id."/".$ticket_filename;
            $dst_s3_path     = self::$DEFAULT_S3_URI."/".$dst_user_id."/".$ticket_filename;
            //get image in S3
            $moved = $this->s3->copyObject($src_s3_path, null, $dst_s3_path);

            if(!$moved)
                throw new Exception("S3 lib could't copy ticket code: $src_code (S3 path: $src_s3_path)");

            //delete old one
            $this->s3->deleteObject($src_s3_path);

            return true;
        }
        catch (Exception $e) {
            //fallback for file
            $this->logger->error("TicketStorage::getTicket -> Error Moving QR code in S3 bucket: $src_code, err: ".$e->getMessage());
            return false;
        }
    }

    /**
     * Get Invoice PDFs by buy orders, if many they are merged.
     * @param int $user_id The user id
     * @param array $buy_orders An array of buy orders
     * @return binary
     */
    public function getMergedInvoices($user_id = 0, $buy_orders = array())
    {
        try {
            if(empty($buy_orders))
                throw new Exception("Empty buy_orders parameter");

            $binary = null;
            $count = count($buy_orders);

            //anonymous fn
            $getFile = function($buy_order) use ($user_id) {

                $invoice_filename = $buy_order.".pdf";
                $s3_path          = self::$DEFAULT_S3_URI."/".$user_id."/".$invoice_filename;
                //get file in S3
                $binary = $this->s3->getObject($s3_path, true);

                if(is_null($binary))
                    throw new Exception("No pdf file found with buy_order: $buy_order");

                return $binary;
            };

            //if is one file only
            if($count == 1) {
                $binary = $getFile($buy_orders[0]);
                //sends file to buffer
                $this->_sendFileToBuffer($binary, self::$MIME_TYPES['pdf']);
                return;
            }

            //create temporal folder
            $output_path = $this->storageConfig['local_temp_path'].$user_id."/";

            if(!is_dir($output_path))
                mkdir($output_path, 0775);

            $output_file = $output_path."invoice_".sha1(implode("_", $buy_orders)).".pdf";

            //for multiple buy orders, merge pdf files
            $files = array();
            foreach ($buy_orders as $order) {

                $binary = $getFile($order);
                $fname  = $output_path.$order.".pdf";

                //save temporary files to disk
                file_put_contents($fname, $binary);
                array_push($files, $fname);
            }

            //merge pdf files
            (new PdfHelper())->mergePdfFiles($files, $output_file, "browser");
            //clean folder
            $this->_deleteTempFiles($output_path, true);
            //send response
            $this->_sendFileToBuffer(null, self::$MIME_TYPES['pdf']);
        }
        catch (Exception $e) {
            //fallback for file
            $this->logger->error("TicketStorage::getInvoice -> Error loading PDF file: $code, err:".$e->getMessage());
            return false;
        }
    }

    /**
     * Handler - Generates QR for multiple tickets
     * @param mixed [object|array] $userTickets A user ticket object or an array of objects
     * @return mixed
     */
    public function generateQRForUserTickets($user_id, $userTickets = array())
    {
        //set qr settings
        $this->qr_settings = $this->storageConfig['qr_settings'];

        //instance qr maker with log & cache paths
        $qr_maker = new QRMaker(APP_PATH."logs/", APP_PATH."cache/");

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
     * Handler - Generates Checkout invoice with tickets as PDF file output
     * @param int $user_id The user Id
     * @param object $data The data object (checkout or review, including transaction props & An array of user ticket IDs)
     * @param string $type The invoice type, checkout or review.
     * @param boolean $save_s3 Saves the invoice in S3
     * @return mixed
     */
    public function generateInvoiceForUserTickets($user_id, $data, $type = "checkout", $save_s3 = true)
    {
        //handle exceptions
        $result = new \stdClass();

        try {
            //set settings
            $this->pdf_settings["data_$type"] = $data;
            $this->pdf_settings["save_s3"]    = $save_s3;
            //generate invoice
            $invoiceName = ($type == "checkout") ? $data->buyOrder : $data->token;
            $result->binary = $this->_generateInvoice($user_id, $invoiceName, $data->userTicketIds);
        }
        catch (\S3Exception $e) {
            $result->error = $e->getMessage();
        }
        catch (Exception $e) {
            $result->error = $e->getMessage();
        }

        if(isset($result->error)) {
            $this->logger->error("TicketStorage::generateInvoiceForUserTickets ($type) -> Error while generating and storing PDF: $result->error");
            return $result;
        }

        return $result;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Generates an Invoice with user tickets
     * @param  int $user_id The user ID
     * @param  string $invoiceName   The invoice file name
     * @param  array  $userTicketIds The user event tickets IDs
     * @return binary generated file
     */
    private function _generateInvoice($user_id, $invoiceName = "temp", $userTicketIds = array())
    {
        //get user model class
        $users_class = $this->getModuleClassName('users');
        //get model class
        $getUserTicketsUI = $this->storageConfig["getUserTicketsUIFunction"];

        if(!is_callable($getUserTicketsUI))
            throw new Exception("Invalid 'get user ticket UI' function");

        //get user by session
        $user = $users_class::getObjectById($user_id);
        //get ticket objects with UI properties
        $tickets = $getUserTicketsUI($user_id, $userTicketIds);

        if(!$tickets)
            throw new Exception("No tickets found for userID: ".$user_id." & tickets Ids: ".json_encode($userTicketIds));

        //set file paths
        $pdf_filename = $invoiceName.".pdf";
        $output_path  = $this->storageConfig['local_temp_path'].$pdf_filename;

        //set extended pdf data
        $this->pdf_settings["data_date"]     = DateHelper::getTranslatedCurrentDate();
        $this->pdf_settings["data_user"]     = $user;
        $this->pdf_settings["data_tickets"]  = $tickets;
        $this->pdf_settings["data_storage"]  = $this->storageConfig['local_temp_path'];

        //get template
        $html_raw = $this->simpleView->render($this->storageConfig['ticket_pdf_template_view'], $this->pdf_settings);

        //PDF generator (this is a heavy task for quick client response)
        $binary = (new PdfHelper())->generatePdfFileFromHtml($html_raw, $output_path, true);

        //upload pdf file to S3
        if($this->pdf_settings["save_s3"]) {

            $s3_path = self::$DEFAULT_S3_URI."/".$user_id."/".$pdf_filename;
            $this->s3->putObject($output_path, $s3_path, true);
        }

        //delete generated local files
        if(APP_ENVIRONMENT !== "development")
            $this->_deleteTempFiles();

        return $binary;
    }
    /**
     * Deletes temporary files in Local directory
     * @param string $path The path folder
     * @param boolean $del_folder Flag for folder deletion
     */
    private function _deleteTempFiles($path = null, $del_folder = false)
    {
        if(is_null($path))
            $path = $this->storageConfig['local_temp_path'];

        foreach (self::$MIME_TYPES as $key => $value) {
            array_map('unlink', glob( "$path*.$key"));
        }

        if($del_folder)
            rmdir($path);
    }
}
