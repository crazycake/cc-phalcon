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
    public function getTicket($user_id = 0, $hashed_id = "", $file_type = "png")
    {
        $binary_data = null;

        try {

            if(!in_array($file_type, $this->storageConfig['allowed_resources_types']))
                throw new Exception("Invalid file type");

            if(empty($hashed_id))
                throw new Exception("Invalid ticket hashed_id");

            $ticket_id = $this->cryptify->decryptHashId($hashed_id);

            //validates that ticket belongs to user
            //get anonymous function from settings
            $get_user_ticket_fn = $this->storageConfig["get_user_ticket_function"];

            if(!is_callable($get_user_ticket_fn))
                throw new Exception("Invalid 'get user ticket' function");

            $user_ticket = $get_user_ticket_fn($user_id, $ticket_id);

            if(!$user_ticket)
                throw new Exception("Invalid ticket id for user");

            $ticket_filename = $user_ticket->qr_hash.".".$file_type;
            $s3_path         = self::$DEFAULT_S3_URI."/".$user_id."/".$ticket_filename;
            //get image in S3
            $binary = $this->s3->getObject($s3_path, true);

            //regenerate image?
            if(!$binary) {
                if($file_type == "png") {
                    $binary = $this->generateQRForTicket($user_ticket, true);
                }
                else if($file_type == "pdf") {
                    $binary = $this->generatePDFForTicket($user_ticket, true);
                }
            }
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
     * Hanlder - Generates QR for ticket
     * @param object $user The UserTicket orm object
     * @param boolean $get_binary Get the output file as binary
     * @return mixed
     */
    public function generateQRForTicket($user_ticket, $get_binary = false)
    {
        //set qr settings
        $this->qr_settings = $this->storageConfig['qr_settings'];

        $qr_filename = $user_ticket->qr_hash.".png";
        $qr_savepath = $this->storageConfig['local_temp_path'].$qr_filename;
        $s3_path     = self::$DEFAULT_S3_URI."/".$user_ticket->user_id."/".$qr_filename;

        //handle exceptions
        $error_occurred = false;

        try {
            //set extended qr data
            $this->qr_settings["data"]     = $user_ticket->qr_hash;
            $this->qr_settings["savename"] = $qr_savepath;
            //instance qr maker with log & cache paths
            $qr_maker = new QRMaker($this->config->directories->logs, $this->config->directories->cache);
            $qr_maker->generate($this->qr_settings);
            //PUSH IT to S3 as private resource
            $this->s3->putObject($qr_savepath, $s3_path, true);
            //get binary file?
            $binary = $get_binary ? file_get_contents($qr_savepath) : true;
        }
        catch (\S3Exception $e) {
            $error_occurred = $e->getMessage();
        }
        catch (Exception $e) {
            $error_occurred = $e->getMessage();
        }

        if($error_occurred) {
            $this->logger->error('TicketStorage::generateQRForTicket -> Error while generating and storing QR: '.$error_occurred);
            return false;
        }

        return $binary;
    }

    /**
     * Hanlder - Generates PDF for ticket
     * @param object $user_ticket The UserTicket orm object
     * @param boolean $get_binary Get the output file as binary
     * @return mixed
     * @todo implement solution for multiple tickets
     */
    public function generatePDFForTicket($user_ticket, $get_binary = false)
    {
        //set qr settings
        $this->qr_settings = $this->storageConfig['qr_settings'];

        $pdf_filename = $user_ticket->qr_hash.".pdf";
        $pdf_savepath = $this->storageConfig['local_temp_path'].$pdf_filename;
        $qr_savepath  = str_replace(".pdf", ".png", $this->storageConfig['local_temp_path'].$pdf_filename);
        $s3_path      = self::$DEFAULT_S3_URI."/".$user_ticket->user_id."/".$pdf_filename;

        //handle exceptions
        $error_occurred = false;
        
        try {
            //get user model class
            $users_class = $this->getModuleClassName('users');
            //get model class
            $get_user_ticket_ui_fn = $this->storageConfig["get_user_ticket_ui_function"];

            if(!is_callable($get_user_ticket_ui_fn))
                throw new Exception("Invalid 'get user ticket UI' function");

            //get user
            $user = $users_class::getObjectById($user_ticket->user_id);
            //get ticket object with UI properties
            $user_ticket_ui = $get_user_ticket_ui_fn($user->id, $user_ticket);
            //set extended pdf data
            $this->pdf_settings["data_user"]    = $user;
            $this->pdf_settings["data_ticket"]  = $user_ticket_ui;
            $this->pdf_settings["data_qr_path"] = $qr_savepath;

            //get template
            $html_raw = $this->simpleView->render($this->storageConfig['ticket_pdf_template_view'], $this->pdf_settings);
            //PDF generator (this is a heavy task for quick client response)
            $binary = (new PdfHelper())->generatePdfFileFromHtml($html_raw, $pdf_savepath);
            //upload pdf file to S3
            $this->s3->putObject($pdf_savepath, $s3_path, true);
            //get binary file?
            $binary = $get_binary ? $binary : true;
        }
        catch (\S3Exception $e) {
            $error_occurred = $e->getMessage();
        }
        catch (Exception $e) {
            $error_occurred = $e->getMessage();
        }

        //delete generated local files
        if(APP_ENVIRONMENT !== "development") {
            if(is_file($pdf_savepath)) unlink($pdf_savepath);
            if(is_file($qr_savepath))  unlink($qr_savepath);
        }

        if($error_occurred) {
            $this->logger->error('TicketStorage::generatePDFForTicket -> Error while generating and storing PDF: '.$error_occurred);
            return false;
        }

        return $binary;
    }
}
