<?php
/**
 * Simple Email Service Trait
 * Requires a Frontend or Backend Module with CoreController
 * Requires Emogrifier & Mandrill class (composer)
 * Requires Users & UserTokens models
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Traits;

//imports
use Phalcon\Exception;
use Pelago\Emogrifier;
use Mandrill;

trait Ses
{
	/**
     * abstract required methods
     */
    abstract public function setConfigurations();

	/**
	 * Config var
	 * @var array
	 */
	public $sesConfig;

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Ajax Handler Action - Send the contact to message to our app
     * Requires email, name, message POST params
     */
    public function sendContactAction()
    {
        $data = $this->_handleRequestParams([
            'email'   => 'email',
            'name'    => 'string',
            'message' => 'string'
        ]);

        $data["subject"] = "Contacto ".$this->sesConfig['appName'];

        //send contact email
        $this->_sendMailMessage('sendSystemMail', $data);

        //send JSON response
        $this->_sendJsonResponse(200);
        return;
    }

	/**
     * Async Handler - Sends contact email (always used)
     * @param array $message_data Must contains keys 'name', 'email' & 'message'
     * @return json response
     */
    public function sendSystemMail($message_data)
    {
    	$this->_checkConfigurations();

    	if(empty($message_data))
    		return false;

        //set message properties
        $subject = isset($message_data["subject"]) ? $message_data["subject"] : $this->sesConfig['appName'];
        $to      = isset($message_data["to"]) ? $message_data["to"] : $this->sesConfig['contactEmail'];
        $tags    = array('contact', 'support');

        //add prefix "data" to each element in array
        $view_data = array_combine( array_map(function($k) { return 'data_'.$k; }, array_keys($message_data)), $message_data);

        //get HTML
        $html_raw = $this->_getInlineStyledHtml("contact", $view_data);

        //sends async email
        return $this->_sendMessage($html_raw, $subject, $to, $tags);
    }

    /**
     * Sends a system mail for exception alert
     * @param  object $exception An exception object
     * @param  object $data      Informative appended data
     */
    public function sendSystemMailForException($exception, $data = null)
    {
        //Error on success checkout task
        if(isset($this->logger))
            $this->logger->error("Ses::sendExceptionSystemMail -> something ocurred, err: ".$exception->getMessage());

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
     * Async Handler - Sends mail account-checker
     * Sends an activation message
     * @param int $user_id
     * @return json response
     */
    public function sendMailForAccountActivation($user_id)
    {
        $this->_checkConfigurations();

        $users_class = $this->_getModuleClass('users');
        $user = $users_class::getObjectById($user_id);
        if (!$user)
            $this->_sendJsonResponse(403);

        //get user token
        $tokens_class = $this->_getModuleClass('users_tokens');
        $token = $tokens_class::generateNewTokenIfExpired($user_id, 'activation');
        if (!$token) {
            $this->_sendJsonResponse(500);
        }

        //create flux uri
        $uri = "auth/activation/".$token->encrypted;
        //set properties
        $this->sesConfig["data_user"]  = $user;
        $this->sesConfig["data_email"] = $user->email;
        $this->sesConfig["data_url"]   = $this->_baseUrl($uri);

        //get HTML
        $html_raw = $this->_getInlineStyledHtml("activation", $this->sesConfig);
        //set message properties
        $subject = $this->sesConfig['trans']['subject_activation'];
        $to      = $this->sesConfig["data_email"];
        $tags    = array('account', 'activation');
        //sends async email
        return $this->_sendMessage($html_raw, $subject, $to, $tags);
    }

    /**
     * Async Handler - Sends mail pass-word recovery
     * Contiene la logica de generar y enviar un token
     * @param int $user_id
     * @return json response
     */
    public function sendMailForPasswordRecovery($user_id)
    {
        $this->_checkConfigurations();

        $users_class = $this->_getModuleClass('users');
        $user = $users_class::getObjectById($user_id);
        //if invalid user, send permission denied response
        if (!$user)
            $this->_sendJsonResponse(403);

        //get user token
        $tokens_class = $this->_getModuleClass('users_tokens');
        $token = $tokens_class::generateNewTokenIfExpired($user_id, 'pass');
        if (!$token) {
            $this->_sendJsonResponse(500);
        }

        //create flux uri
        $uri = "password/new/".$token->encrypted;
        //set rendered view
        $this->sesConfig["data_user"]  = $user;
        $this->sesConfig["data_email"] = $user->email;
        $this->sesConfig["data_url"]   = $this->_baseUrl($uri);
        $this->sesConfig["data_token_expiration"] = $tokens_class::$TOKEN_EXPIRES_THRESHOLD;

        //get HTML
        $html_raw = $this->_getInlineStyledHtml("passwordRecovery", $this->sesConfig);
        //set message properties
        $subject = $this->sesConfig['trans']['subject_password'];
        $to      = $this->sesConfig["data_email"];
        $tags    = array('account', 'password', 'recovery');
        //sends async email
        return $this->_sendMessage($html_raw, $subject, $to, $tags);
    }

	/**
     * Generates a new HTML styled with inline CSS as style attribute
     * DI dependency injector must have simpleView service
     * @param string $mail The mail template
     * @param array $data The view data
     * @return string
     */
    public function _getInlineStyledHtml($mail, $data)
    {
    	$this->_checkConfigurations();

        //css file
        $cssFile = $this->sesConfig['cssFile'];

        if(APP_ENVIRONMENT !== 'local')
            $cssFile = str_replace(".css", ".min.css", $cssFile);

        //get the style file
        $html = $this->simpleView->render("mails/$mail", $data);
        $css  = file_get_contents($cssFile);

        $emogrifier = new Emogrifier($html, $css);
        $html = $emogrifier->emogrify();

        return $html;
    }

    /**
     *  Sends a message through mandrill API
     * @param string $html_raw
     * @param string $subject
     * @param mixed(string|array) $recipients
     * @param array $tags
     * @param array $attachments Array with sub-array(s) with content, type and name props
     * @param boolean $async
     * @return string
     */
    public function _sendMessage($html_raw, $subject, $recipients, $tags = array(), $attachments = array(), $async = true)
    {
    	$this->_checkConfigurations();

        //validation
        if (empty($html_raw) || empty($subject) || empty($recipients))
            throw new Exception("Ses::_sendMessage -> Invalid params data for sending email");

        //parse recipients
        if (is_string($recipients))
            $recipients = array($recipients);

        $to = array();
        //create mandrill recipient data struct, push emails to array
        foreach ($recipients as $email)
            array_push($to, array('email' => $email)); //optional name (display name) & type (defaults 'to').

        //set default subject
        if (empty($subject))
            $subject = $this->sesConfig['appName'];

        //Send message email!
        $message = [
            'html'       => $html_raw,
            'subject'    => $subject,
            'from_email' => $this->sesConfig['senderEmail'],
            'from_name'  => $this->sesConfig['appName'],
            'to'         => $to,
            'tags'       => $tags
            //'inline_css' => true //same as __getInlineStyledHtml method. (generates more delay time)
        ];

        //append attachments
        if(!empty($attachments))
           $message['attachments'] = $attachments;

        //send email!
        $response = true;

        try {
        	//mandrill lib instance
        	$mandrill = new Mandrill($this->sesConfig['mandrillKey']);
            $response = $mandrill->messages->send($message, $async);
        }
        catch (Mandrill_Error $e) {
            $response = false;
            // Mandrill errors are thrown as exceptions
            $this->logger->error("Ses::_sendMessage -> A mandrill error occurred sending a message (" . get_class($e) . "), trace: " . $e->getMessage());
        }

        return $response;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Check configurations properties
     */
    private function _checkConfigurations()
    {
        if (!isset($this->sesConfig['appName']) || !isset($this->sesConfig['mandrillKey']) || !isset($this->sesConfig['cssFile']))
            throw new Exception("Ses::_checkConfigurations -> SES configuration properties are not defined. (appName, mandrillKey, cssFile)");

        if (!isset($this->sesConfig['senderEmail']) || !isset($this->sesConfig['contactEmail']))
        	throw new Exception("Ses::_checkConfigurations -> SES sender & contact emails are not defined.");
    }
}
