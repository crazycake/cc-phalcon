<?php
/**
 * Mailer - Email Service Trait
 * Requires a Frontend or Backend Module with CoreController
 * Requires Emogrifier & Mandrill class (composer)
 * Requires User & UserToken models
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Services;


//imports
use Phalcon\Exception;
use Pelago\Emogrifier;
use Mandrill;
//core
use CrazyCake\Phalcon\AppModule;

/**
 * Simple Email Service Trait
 */
trait Mailer
{
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
    public function initMailer($conf = array())
    {
        $this->mailer_conf = $conf;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Ajax Handler Action - Send the contact to message to our app
     * Requires email, name, message POST params
     */
    public function sendContactAction()
    {
        $data = $this->_handleRequestParams([
            "email"   => "email",
            "name"    => "string",
            "message" => "string"
        ]);

        $data["subject"] = "Contacto ".$this->config->app->name;

        //send contact email
        $this->_sendMailMessage("sendSystemMail", $data);

        //send JSON response
        $this->_sendJsonResponse(200);
        return;
    }

	/**
     * Async Handler - Sends contact email (always used)
     * @param array $message_data - Must contains keys "name", "email" & "message"
     * @return json response
     */
    public function sendSystemMail($message_data)
    {
    	if(empty($message_data))
    		return false;

        //set message properties
        $subject = isset($message_data["subject"]) ? $message_data["subject"] : $this->config->app->name;
        $to      = isset($message_data["to"]) ? $message_data["to"] : $this->config->app->emails->contact;
        $tags    = array("contact", "support");

        //add prefix "data" to each element in array
        $view_data = array_combine( array_map(function($k) { return "data_".$k; }, array_keys($message_data)), $message_data);

        //get HTML
        $html_raw = $this->_getInlineStyledHtml("contact", $view_data);

        //sends async email
        return $this->_sendMessage($html_raw, $subject, $to, $tags);
    }

    /**
     * Sends a system mail for exception alert
     * @param object $exception - An exception object
     * @param object $data - Informative appended data
     */
    public function sendSystemMailForException($exception, $data = null)
    {
        //Error on success checkout task
        if(isset($this->logger))
            $this->logger->error("Mailer::sendExceptionSystemMail -> something ocurred, err: ".$exception->getMessage());

        //Sending a warning to admin users!
        $this->sendSystemMail([
            "subject" => "Exception Notification Error",
            "to"      => $this->config->app->emails->support,
            "email"   => $this->config->app->emails->sender,
            "name"    => $this->config->app->name." System",
            "message" => "A error occurred.".
                         "\n Data:  ".(is_null($data) ? "empty" : json_encode($data, JSON_UNESCAPED_SLASHES)).
                         "\n Trace: ".$exception->getMessage()
        ]);
    }

    /**
     * Async Handler - Sends mail for account activation (email validation)
     * @param int $user_id - The user ID
     * @return json response
     */
    public function sendMailForAccountActivation($user_id)
    {
        $user_class = AppModule::getClass("user");
        $user = $user_class::getById($user_id);

        if (!$user)
            $this->_sendJsonResponse(403);

        //get user token
        $tokens_class = AppModule::getClass("user_token");
        $token = $tokens_class::newTokenIfExpired($user_id, "activation");

        if (!$token)
            $this->_sendJsonResponse(500);

        //create flux uri
        $uri = "auth/activation/".$token->encrypted;
        //set properties
        $this->mailer_conf["data_user"]  = $user;
        $this->mailer_conf["data_email"] = $user->email;
        $this->mailer_conf["data_url"]   = $this->_baseUrl($uri);

        //get HTML
        $html_raw = $this->_getInlineStyledHtml("activation", $this->mailer_conf);
        //set message properties
        $subject = $this->mailer_conf["trans"]["subject_activation"];
        $to      = $this->mailer_conf["data_email"];
        $tags    = ["account", "activation"];
        //sends async email
        return $this->_sendMessage($html_raw, $subject, $to, $tags);
    }

    /**
     * Async Handler - Sends mail for password recovery
     * Generates & sends a validation token
     * @param int $user_id - The user ID
     * @return json response
     */
    public function sendMailForPasswordRecovery($user_id)
    {
        $user_class = AppModule::getClass("user");
        $user = $user_class::getById($user_id);

        //if invalid user, send permission denied response
        if (!$user)
            $this->_sendJsonResponse(403);

        //get user token
        $tokens_class = AppModule::getClass("user_token");
        $token = $tokens_class::newTokenIfExpired($user_id, "pass");

        if (!$token)
            $this->_sendJsonResponse(500);

        //create flux uri
        $uri = "password/new/".$token->encrypted;
        //set rendered view
        $this->mailer_conf["data_user"]  = $user;
        $this->mailer_conf["data_email"] = $user->email;
        $this->mailer_conf["data_url"]   = $this->_baseUrl($uri);
        $this->mailer_conf["data_token_expiration"] = $tokens_class::$TOKEN_EXPIRES_THRESHOLD;

        //get HTML
        $html_raw = $this->_getInlineStyledHtml("passwordRecovery", $this->mailer_conf);
        //set message properties
        $subject = $this->mailer_conf["trans"]["subject_password"];
        $to      = $this->mailer_conf["data_email"];
        $tags    = array("account", "password", "recovery");
        //sends async email
        return $this->_sendMessage($html_raw, $subject, $to, $tags);
    }

	/**
     * Generates a new HTML styled with inline CSS as style attribute
     * DI dependency injector must have simpleView service
     * @param string $mail - The mail template
     * @param array $data - The view data
     * @return string
     */
    public function _getInlineStyledHtml($mail, $data)
    {
        //css file
        $css_file = $this->mailer_conf["css_file"];

        //append app var
        $data["app"] = $this->config->app;

        //get the style file
        $html = $this->simpleView->render("mails/$mail", $data);
        $css  = file_get_contents($css_file);

        $emogrifier = new Emogrifier($html, $css);
        $emogrifier->addExcludedSelector("head");
        $emogrifier->addExcludedSelector("meta");

        $html = $emogrifier->emogrify();

        return $html;
    }

    /**
     *  Sends a message through mandrill API
     * @param string $html_raw - The HTML raw string
     * @param string $subject - The mail subject
     * @param mixed(string|array) $recipients - The receiver emails
     * @param array $tags - Monitor tags
     * @param array $attachments - Array with sub-array(s) with content, type and name props
     * @param boolean $async - Async flag, defaults to true
     * @return string
     */
    public function _sendMessage($html_raw, $subject, $recipients, $tags = [], $attachments = [], $async = true)
    {
        //validation
        if (empty($html_raw) || empty($subject) || empty($recipients))
            throw new Exception("Mailer::_sendMessage -> Invalid params data for sending email");

        //parse recipients
        if (is_string($recipients))
            $recipients = array($recipients);

        $to = array();
        //create mandrill recipient data struct, push emails to array
        foreach ($recipients as $email)
            array_push($to, array("email" => $email)); //optional name (display name) & type (defaults "to").

        //set default subject
        if (empty($subject))
            $subject = $this->config->app->name;

        //Send message email!
        $message = [
            "html"       => $html_raw,
            "subject"    => $subject,
            "from_email" => $this->config->app->emails->sender,
            "from_name"  => $this->config->app->name,
            "to"         => $to,
            "tags"       => $tags
            //"inline_css" => true //same as __getInlineStyledHtml method. (generates more delay time)
        ];

        //append attachments
        if(!empty($attachments))
           $message["attachments"] = $attachments;

        //send email!
        $response = true;

        try {
        	//mandrill lib instance
        	$mandrill = new Mandrill($this->config->app->mandrill->accessKey);
            $response = $mandrill->messages->send($message, $async);
        }
        catch (Mandrill_Error $e) {

            $response = false;
            // Mandrill errors are thrown as exceptions
            $this->logger->error("Mailer::_sendMessage -> A mandrill error occurred sending a message (".get_class($e)."), trace: ".$e->getMessage());
        }

        return $response;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

}
