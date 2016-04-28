<?php
/**
 * Ticket Storage Trait
 * This class manages tickets resources to AWS S3.
 * Files are uploaded automatically to S3 in a defined URI.
 * Requires a Frontend or Backend Module with CoreController
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Tickets;

//imports
use Phalcon\Exception;
//core
use CrazyCake\Phalcon\AppModule;
use CrazyCake\Services\StorageS3; //AWS S3 File Storage helper
use CrazyCake\Qr\QRMaker;         //CrazyCake QR
use CrazyCake\Helpers\PDF;        //PDF helper
use CrazyCake\Helpers\Dates; //Date Helper functions

/**
 * Ticket Manager Trait
 */
trait TicketManager
{
    /**
     * Config var
     * @var array
     */
    protected $ticket_manager_conf;

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
    protected static $MIME_TYPES = [
        "png" => "image/png",
        "pdf" => "application/pdf"
    ];

    /**
     * Default S3 URI path
     * @var string
     */
    protected static $DEFAULT_S3_URI = "tickets";

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Init S3 Helper library
     * @param array $conf - Configuration array
     */
    public function initTicketManager($conf = array())
    {
        //set manager
        $this->ticket_manager_conf = $conf;

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
     * @param int $user_id - The user ID
     * @param string $code - The ticket associated code
     * @return binary
     */
    public function getTicketQR($user_id = 0, $code = "")
    {
        try {

            //validates that ticket belongs to user, get anonymous function from settings
            $getUserTicket = $this->ticket_manager_conf["getUserTicketFunction"];

            if(!is_callable($getUserTicket))
                throw new Exception("Invalid 'get user ticket' function");

            $user_ticket = $getUserTicket($code, $user_id);

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
     * @param int $src_user_id - The source user ID
     * @param string $src_code - The source ticket associated code
     * @param int $dst_user_id - The destination user ID
     * @return boolean $moved - Return true if object was moved to destination.
     */
    public function moveTicketQR($src_user_id = 0, $src_code = "", $dst_user_id = 0)
    {
        try {
            //validates that ticket belongs to user, get anonymous function from settings
            $getUserTicket = $this->ticket_manager_conf["getUserTicketFunction"];

            if(!is_callable($getUserTicket))
                throw new Exception("Invalid 'get user ticket' function");

            $user_ticket = $getUserTicket($src_code, $src_user_id);

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
            //$this->s3->deleteObject($src_s3_path);

            return true;
        }
        catch (\S3Exception $e) {
            //fallback for file
            $this->logger->error("TicketStorage::getTicket (S3 Exception) -> Error Moving QR code in S3 bucket: $src_code, err: ".$e->getMessage());
            return false;
        }
        catch (Exception $e) {
            //fallback for file
            $this->logger->error("TicketStorage::getTicket -> Error Moving QR code in S3 bucket: $src_code, err: ".$e->getMessage());
            return false;
        }
    }

    /**
     * Handler - Generates QR for multiple tickets
     * @param int $user_id - The user ID
     * @param mixed [object|array] $userTickets - A user ticket object or an array of objects
     * @return object - The result object
     */
    public function generateQRForUserTickets($user_id = 0, $userTickets = array())
    {
        //set qr settings
        $this->qr_settings = $this->ticket_manager_conf['qr_settings'];

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
                $qr_savepath = $this->ticket_manager_conf['local_temp_path'].$qr_filename;
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
     * @param int $user_id - The user ID
     * @param object $checkout - The checkout object
     * @param boolean $otf - On the fly flag, if false saves invoice in S3.
     * @return object - The result object
     */
    public function generateInvoice($user_id, $checkout, $otf = false)
    {
        //handle exceptions
        $result = new \stdClass();

        try {
            //set on the fly property
            $this->pdf_settings["otf"] = $otf;
            //set invoice name
            $this->pdf_settings["invoice_name"] = isset($checkout->buy_order) ? $checkout->buy_order : uniqid()."_".date('d-m-Y');

            //generate invoice
            $result->binary = $this->_buildInvoice($user_id, $checkout);
        }
        catch (\S3Exception $e) {
            $result->error = $e->getMessage();
        }
        catch (Exception $e) {
            $result->error = $e->getMessage();
        }

        if(isset($result->error)) {
            $this->logger->error("TicketStorage::generateInvoice (userId: $user_id) -> Error while generating and storing PDF: $result->error");
            return $result;
        }

        return $result;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Generates an Invoice with user tickets
     * @param int $user_id - The user ID
     * @param object $checkout - The checkout object
     * @return binary - The generated file
     */
    private function _buildInvoice($user_id, $checkout)
    {
        //get user model class
        $user_class = AppModule::getClass('user');
        //get model class
        $getObjectsForInvoice = $this->ticket_manager_conf["getObjectsForInvoiceFunction"];

        if(!is_callable($getObjectsForInvoice))
            throw new Exception("Invalid getObjectsForInvoice function");

        //get user by session
        $user = $user_class::getById($user_id);

        //get ticket objects with UI properties
        $objects = $getObjectsForInvoice($user_id, $checkout);

        //download qr tickets if invoice is for OTF actions
        if($objects && $this->pdf_settings["otf"])
            $this->_downloadTicketQrs($user_id, $objects);

        //set file paths
        $pdf_filename = $this->pdf_settings["invoice_name"].".pdf";
        $output_path  = $this->ticket_manager_conf['local_temp_path'].$pdf_filename;

        //set extended pdf data
        $this->pdf_settings["data_date"]     = Dates::getTranslatedCurrentDate();
        $this->pdf_settings["data_user"]     = $user;
        $this->pdf_settings["data_checkout"] = $checkout;
        $this->pdf_settings["data_objects"]  = $objects;
        $this->pdf_settings["data_storage"]  = $this->ticket_manager_conf['local_temp_path'];

        //get template
        $html_raw = $this->simpleView->render($this->ticket_manager_conf['ticket_pdf_template_view'], $this->pdf_settings);

        //PDF generator (this is a heavy task for quick client response)
        $binary = (new PDF())->generatePdfFileFromHtml($html_raw, $output_path, true);

        //upload pdf file to S3
        if($this->pdf_settings["otf"]) {

            $s3_path = self::$DEFAULT_S3_URI."/".$user_id."/".$pdf_filename;
            $this->s3->putObject($output_path, $s3_path, true);
        }

        //delete generated local files
        if(APP_ENVIRONMENT !== "local")
            $this->_deleteTempFiles();

        return $binary;
    }


    /**
     * Download QR images to local folder for preload.
     * This method is necessary for OTF invoice generator.
     * @param int $user_id - The user ID
     * @param array $userTickets - The user tickets array
     */
    private function _downloadTicketQrs($user_id = 0, $userTickets = array())
    {
        if(empty($userTickets))
            return;

        //loop through each ticket
        foreach ($userTickets as $ticket) {

            if(!isset($ticket->code))
                continue;

            //set configs
            $qr_filename = $ticket->code.".png";
            $qr_savepath = $this->ticket_manager_conf['local_temp_path'].$qr_filename;
            $s3_path     = self::$DEFAULT_S3_URI."/".$user_id."/".$qr_filename;

            //get image in S3
            $binary = $this->s3->getObject($s3_path, true);

            if(!$binary)
                continue;

            //save to disk
            file_put_contents($qr_savepath, $binary);
        }
    }

    /**
     * Deletes temporary files in Local directory
     * @param string $path - The path folder
     * @param boolean $del_folder - Flag for folder deletion
     */
    private function _deleteTempFiles($path = null, $del_folder = false)
    {
        if(is_null($path))
            $path = $this->ticket_manager_conf['local_temp_path'];

        foreach (self::$MIME_TYPES as $key => $value) {
            array_map('unlink', glob( "$path*.$key"));
        }

        if($del_folder)
            rmdir($path);
    }
}
