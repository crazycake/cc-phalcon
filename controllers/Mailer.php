<?php
/**
 * Mailer - Email Service Trait
 * Requires a Frontend or Backend Module with CoreController
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Controllers;


//imports
use Phalcon\Exception;
use Pelago\Emogrifier;

//core
use CrazyCake\Phalcon\AppModule;

/**
 * Simple Email Service Trait
 */
trait Mailer
{
    /**
     * Before render listener (debug)
     */
    abstract public function onRenderPreview();

    /**
     * Mailing CSS file
     * @var string
     */
    protected static $MAILER_CSS_FILE = MODULE_PATH."dev/volt/mailing/css/app.css";

    /**
     * Temporal path
     * @var string
     */
    protected static $MAILER_CACHE_PATH = STORAGE_PATH."cache/mailer/";

	/**
	 * Config var
	 * @var array
	 */
	public $mailer_conf;

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * This method must be call in constructor parent class
     * @param array $conf - The config array
     */
    public function initMailer($conf = [])
    {
        //defaults
        $defaults = [
            //entities
            "user_entity" => "User"
        ];

        //merge confs
        $conf = array_merge($defaults, $conf);
        //append class prefixes
        $conf["user_token_entity"] = AppModule::getClass($conf["user_entity"])."Token";
        $conf["user_entity"]       = AppModule::getClass($conf["user_entity"]);

        $this->mailer_conf = $conf;

        //create dir if not exists
        if(!is_dir(self::$MAILER_CACHE_PATH))
            mkdir(self::$MAILER_CACHE_PATH, 0755);
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Ajax Handler Action - Send the contact to message to our app
     * Requires email, name, message POST params
     */
    public function sendContactAction()
    {
        $data = $this->handleRequest([
            "email"   => "email",
            "name"    => "string",
            "message" => "string"
        ], "POST");

        $data["subject"] = "Contacto ".$this->config->app->name;

        //send contact email
        $this->sendAdminMessage($data);

        //send JSON response
        $this->jsonResponse(200);
    }

    /**
     * Async Handler - Sends mail for account activation (email validation)
     * @param int $user_id - The user ID
     * @return json response
     */
    public function accountActivation($user_id)
    {
        $user_class = $this->mailer_conf["user_entity"];
        $user       = $user_class::getById($user_id);

        if (!$user)
            $this->jsonResponse(403);

        //get user token
        $tokens_class = $this->mailer_conf["user_token_entity"];
        $token = $tokens_class::newTokenIfExpired($user_id, "activation");

        if (!$token)
            $this->jsonResponse(500);

        //create flux uri
        $uri = "auth/activation/".$token->encrypted;
        //set properties
        $this->mailer_conf["data_user"]  = $user;
        $this->mailer_conf["data_email"] = $user->email;
        $this->mailer_conf["data_url"]   = $this->baseUrl($uri);

        //set message properties
        $subject = $this->mailer_conf["trans"]["SUBJECT_ACTIVATION"];
        $to      = $this->mailer_conf["data_email"];

        //sends async email
        $this->sendMessage("activation", $subject, $to);
    }

    /**
     * Async Handler - Sends mail for password recovery
     * Generates & sends a validation token
     * @param int $user_id - The user ID
     * @return json response
     */
    public function passwordRecovery($user_id)
    {
        $user_class = $this->mailer_conf["user_entity"];
        $user = $user_class::getById($user_id);

        //if invalid user, send permission denied response
        if (!$user)
            $this->jsonResponse(403);

        //get user token
        $tokens_class = $this->mailer_conf["user_token_entity"];
        $token        = $tokens_class::newTokenIfExpired($user_id, "pass");

        if (!$token)
            $this->jsonResponse(500);

        //create flux uri
        $uri = "password/new/".$token->encrypted;
        //set rendered view
        $this->mailer_conf["data_user"]  = $user;
        $this->mailer_conf["data_email"] = $user->email;
        $this->mailer_conf["data_url"]   = $this->baseUrl($uri);
        $this->mailer_conf["data_token_expiration"] = $tokens_class::$TOKEN_EXPIRES_THRESHOLD["pass"];

        //set message properties
        $subject = $this->mailer_conf["trans"]["SUBJECT_PASSWORD"];
        $to      = $this->mailer_conf["data_email"];
        //sends async email
        $this->sendMessage("passwordRecovery", $subject, $to);
    }

	/**
     * Generates a new HTML styled with inline CSS as style attribute
     * DI dependency injector must have simpleView service
     * @param string $template - The mail template view
     * @return string
     */
    public function inlineHtml($template = "")
    {
        //set app var
        $this->mailer_conf["app"] = $this->config->app;

        if(!is_file(self::$MAILER_CSS_FILE))
            throw new Exception("Mailer cant find mailing CSS file: ".self::$MAILER_CSS_FILE);

        $template_view = "mailing/$template";

        //get the style file
        $html = $this->simpleView->render($template_view, $this->mailer_conf);
        $css  = file_get_contents(self::$MAILER_CSS_FILE);

        //HTML inliner
        $emogrifier = new Emogrifier($html, $css);
        $emogrifier->addExcludedSelector("head");
        $emogrifier->addExcludedSelector("meta");

        $html = $emogrifier->emogrify();

        return $html;
    }

    /**
     * Sends a system mail for exception alert
     * @param object $exception - An exception object
     * @param object $data - Informative appended data
     */
    public function adminException($exception, $data = null)
    {
        //Error on success checkout task
        if (isset($this->logger))
            $this->logger->error("Mailer::sendExceptionSystemMail -> something ocurred, err: ".$exception->getMessage());

        //Sending a warning to admin users!
        $this->sendAdminMessage([
            "subject" => "Exception Notification Error",
            "to"      => $this->config->app->emails->support,
            "email"   => $this->config->app->emails->sender,
            "name"    => $this->config->app->name." System",
            "message" => "A error occurred.".
                         "\nData:  ".(is_null($data) ? "empty" : json_encode($data, JSON_UNESCAPED_SLASHES)).
                         "\nTrace: ".$exception->getMessage()
        ]);
    }

    /**
     * Async Handler - Sends contact email (always used)
     * @param array $message_data - Must contains keys "name", "email" & "message"
     * @return json response
     */
    public function sendAdminMessage($message_data = [])
    {
    	if (empty($message_data))
    		return false;

        //set message properties
        $subject = isset($message_data["subject"]) ? $message_data["subject"] : $this->config->app->name;
        $to      = isset($message_data["to"]) ? $message_data["to"] : $this->config->app->emails->contact;

        //add prefix "data" to each element in array
        $this->mailer_conf = array_combine( array_map(function($k) { return "data_".$k; }, array_keys($message_data)), $message_data);

        //sends async email
        $this->sendMessage("contact", $subject, $to);
    }

    /**
     *  Sends a message through sendgrid API
     * @param string $template - The template name
     * @param string $subject - The mail subject
     * @param mixed(string|array) $recipients - The receiver emails
     * @param array $attachments - Array with sub-array(s) with content, type and name props
     * @return $result array
     */
    public function sendMessage($template, $subject, $recipients, $attachments = [])
    {
        //validation
        if (empty($template) || empty($subject) || empty($recipients))
            throw new Exception("Mailer::sendMessage -> Invalid params data for sending email");

        //parse recipients
        if (is_string($recipients))
            $recipients = explode(",", $recipients);

        //set default subject
        if (empty($subject))
            $subject = $this->config->app->name;

        try {

            //service instance
            $sendgrid = new \SendGrid($this->config->app->sendgrid->apiKey);
            $message  = new \SendGrid\Email();

            $message->setFrom($this->config->app->emails->sender)
                    ->setFromName($this->config->app->name)
                    ->setReplyTo($this->config->app->emails->support)
                    ->setSubject($subject)
                    ->setHtml($this->inlineHtml($template));

            //add recipients
            foreach ($recipients as $email)
                $message ->addTo($email);

            //parse attachments
            $this->_parseAttachments($attachments, $message);
            //s($sendgrid, $message);exit;

            //send email
            $result = $sendgrid->send($message);
            //s($result);

            return $result;
        }
        catch (Exception $e) {

            $this->logger->error("Mailer::sendMessage -> An error occurred: ".$e->getMessage());

            return false;
        }
    }

    /**
     * View for debuging - Renders a mail message or a template
     */
    public function previewAction($view = null)
    {
        if (APP_ENVIRONMENT == 'production' || empty($view))
            $this->redirectToNotFound();

        $user_class = $this->mailer_conf["user_entity"];

        $user = $user_class::findFirst();

        //set rendered view vars
        $this->mailer_conf["data_url"]  = $this->baseUrl('fake/path');
        $this->mailer_conf["data_user"] = $user;

        //for contact template
        $this->mailer_conf["data_name"]    = $user->first_name." ".$user->last_name;
        $this->mailer_conf["data_email"]   = $user->email;
        $this->mailer_conf["data_message"] = "This is an example message";

        //call listener
        $this->onRenderPreview();

        //get HTML
        $html_raw = $this->inlineHtml($view);
        //render view
        $this->view->disable();

        echo $html_raw;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Parse attachments to be sended to sendgrid API service
     * @param array $attachments - Attachments array
     * @param object $message - The sendgrid object
     * @return array The parsed array data
     */
    private function _parseAttachments($attachments = null, &$message)
    {
        if(!isset($message) || !is_array($attachments) || empty($attachments))
            return;

        foreach ($attachments as $attachment) {

            if(!isset($attachment["name"]) || !isset($attachment["binary"]))
                continue;

            $file_path = self::$MAILER_CACHE_PATH.$attachment["name"];

            //save file to disk. NOTE: base64 encode not supported by API yet :(
            file_put_contents($file_path, $attachment["binary"]);

            //set attachment
            $message->setAttachment($file_path);
        }
    }
}
