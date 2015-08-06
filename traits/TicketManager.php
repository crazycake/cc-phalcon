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
            'app_name' => $this->config->app->name,
        );
    }
    /**
     * Binary Image - Outputs QR ticket image as binary with Content-Type png
     * @param int $user_id The user id
     * @param string $hashed_id The hashed ticket_id received as POST param
     * @param string $file_type The file type
     * @return binary
     */
    public function getTicketQR($user_id = 0, $hashed_id = "", $file_type = "png")
    {
        try {

            if(!in_array($file_type, $this->storageConfig['allowed_resources_types']))
                throw new Exception("Invalid file type");

            if(empty($hashed_id))
                throw new Exception("Invalid ticket hashed_id");

            $ticket_id = $this->cryptify->decryptHashId($hashed_id);

            //validates that ticket belongs to user
            //get anonymous function from settings
            $getUserTicket = $this->storageConfig["getUserTicketFunction"];

            if(!is_callable($getUserTicket))
                throw new Exception("Invalid 'get user ticket' function");

            $user_ticket = $getUserTicket($user_id, $ticket_id);

            if(!$user_ticket)
                throw new Exception("Invalid ticket id for user");

            $ticket_filename = $user_ticket->qr_hash.".".$file_type;
            $s3_path         = self::$DEFAULT_S3_URI."/".$user_id."/".$ticket_filename;
            //get image in S3
            $binary = $this->s3->getObject($s3_path, true);

            if(!$binary)
                throw new Exception("S3 could't find binary file $file_type");
        }
        catch (Exception $e) {
            //fallback for file
            $this->logger->error("TicketStorage::getTicket -> Error loading image: ".$hashed_id." - Exception:".$e->getMessage());
            $binary = file_get_contents($this->_baseUrl($this->storageConfig['image_fallback_uri']));
        }

        //send output as binary image
        $this->view->disable();
        $this->response->setContentType(self::$MIME_TYPES[$file_type]); //must be declare before setContent
        $this->response->setContent($binary);
        $this->response->send();
    }

    /**
     * Hanlder - Generates QR for multiple tickets
     * @param mixed [object|array] $userTickets A user ticket object or an array of objects
     * @return mixed
     */
    public function generateQRForUserTickets($userTickets)
    {
        if(!is_array($userTickets))
            $userTickets = array($userTickets);

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

                //set configs
                $qr_filename = $ticket->qr_hash.".png";
                $qr_savepath = $this->storageConfig['local_temp_path'].$qr_filename;
                $s3_path     = self::$DEFAULT_S3_URI."/".$ticket->user_id."/".$qr_filename;

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

        if(isset($result->error)) {
            $this->logger->error('TicketStorage::generateQRForUserTicket -> Error while generating and storing QR, err:'.$result->error);
            return $result;
        }

        return $result;
    }

    /**
     * Hanlder - Generates PDF for ticket and invoice attached
     * @param object $checkout The checkout object (including transaction props)
     * @param array $userTickets The userTickets ORM array
     * @return mixed
     */
    public function generateInvoiceForUserCheckout($checkout, $userTickets)
    {
        //handle exceptions
        $error_occurred = false;

        try {
            //get user model class
            $users_class = $this->getModuleClassName('users');
            //get model class
            $getUserTicketsUI = $this->storageConfig["getUserTicketsUIFunction"];

            if(!is_callable($getUserTicketsUI))
                throw new Exception("Invalid 'get user ticket UI' function");

            //get user by session
            $user = $users_class::getObjectById($this->user_session["id"]);
            //get ticket objects with UI properties
            $tickets = $getUserTicketsUI($user->id, $userTickets);

            //set file paths
            $pdf_filename = $checkout->buyOrder.".pdf";
            $savepath     = $this->storageConfig['local_temp_path'].$pdf_filename;
            $s3_path      = self::$DEFAULT_S3_URI."/".$user->id."/".$pdf_filename;

            //set extended pdf data
            $this->pdf_settings["data_user"]       = $user;
            $this->pdf_settings["data_tickets"]    = $tickets;
            $this->pdf_settings["data_local_path"] = $savepath;

            //get template
            $html_raw = $this->simpleView->render($this->storageConfig['ticket_pdf_template_view'], $this->pdf_settings);

            //PDF generator (this is a heavy task for quick client response)
            (new PdfHelper())->generatePdfFileFromHtml($html_raw, $savepath);

            //upload pdf file to S3
            $this->s3->putObject($savepath, $s3_path, true);
        }
        catch (\S3Exception $e) {
            $error_occurred = $e->getMessage();
        }
        catch (Exception $e) {
            $error_occurred = $e->getMessage();
        }

        //delete generated local files
        if(APP_ENVIRONMENT !== "development") {
            if(is_dir($savepath)) rmdir($savepath);
        }

        if($error_occurred) {
            $this->logger->error('TicketStorage::generatePDFForTicket -> Error while generating and storing PDF: '.$error_occurred);
            return false;
        }

        return true;
    }
}
