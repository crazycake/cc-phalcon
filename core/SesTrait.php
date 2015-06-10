<?php
/**
 * Simple Email Service Trait
 * Requires a Frontend or Backend Module with CoreController
 * Requires Emogrifier & Mandrill class (composer)
 * Requires Users & UserTokens models
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

//imports
use Pelago\Emogrifier;
use Mandrill;

trait SesTrait
{
	/**
     * child required methods
     */
    abstract public function setConfigurations();

    /* consts */
    public static $URI_ACCOUNT_ACTIVATION = 'account/activation/';
    public static $URI_SET_NEW_PASSWORD   = 'password/new/';

	/**
	 * Config var for SES
	 * @var array
	 */
	public $ses;

    /* --------------------------------------------------- § -------------------------------------------------------- */

	/**
     * Async Handler - Sends contact email (always used)
     * @param array $message_data Must contains keys 'name', 'email' & 'message'
     * @return json response
     */
    public function sendMailForContact($message_data)
    {
    	$this->_checkConfigurations();

    	if(empty($message_data))
    		return false;

        //set message properties
        $subject = "Contacto ".$this->ses['appName'];
        $to      = $this->ses['contactEmail'];
        $tags    = array('contact');

        //get HTML
        $html_raw = $this->_getInlineStyledHtml("contact", $message_data);

        //sends async email
        return $this->_sendMessage($html_raw, $subject, $to, $tags);
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

        $users_class = $this->getModuleClassName('users');
        $user = $users_class::getObjectById($user_id);
        if (!$user)
            $this->_sendJsonResponse(403);

        //get user token
        $token_class = $this->getModuleClassName('users_tokens');
        $token = $token_class::generateNewTokenIfExpired($user_id, 'activation');
        if (!$token) {
            $this->_sendJsonResponse(500);
        }

        //set message properties
        $subject = $this->ses['subjectActivationAccount'];
        $to      = $user->email;
        $tags    = array('account', 'activation');

        //set link url
        $encrypted_data = $this->cryptify->encryptForGetRequest($token->user_id . "#" . $token->type . "#" . $token->token);

        //set rendered view
        $this->message_data["user"] = $user;
        $this->message_data["url"]  = $this->_baseUrl(self::$URI_ACCOUNT_ACTIVATION.$encrypted_data);
        //get HTML
        $html_raw = $this->_getInlineStyledHtml("activation", $this->message_data);

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

        $users_class = $this->getModuleClassName('users');
        $user = $users_class::getObjectById($user_id);
        //if invalid user, send permission denied response
        if (!$user)
            $this->_sendJsonResponse(403);

        //get user token
        $token_class = $this->getModuleClassName('users_tokens');
        $token = $token_class::generateNewTokenIfExpired($user_id, 'pass');
        if (!$token) {
            $this->_sendJsonResponse(500);
        }

        //set message properties
        $subject = $this->ses['subjectPasswordRecovery'];
        $to      = $user->email;
        $tags    = array('account', 'password', 'recovery');

        //set link url
        $encrypted_data = $this->cryptify->encryptForGetRequest($token->user_id . "#" . $token->type . "#" . $token->token);
        //set rendered view
        $this->message_data["user"] = $user;
        $this->message_data["url"]  = $this->_baseUrl(self::$URI_SET_NEW_PASSWORD.$encrypted_data);
        $this->message_data["token_expiration"] = $token_class::$TOKEN_EXPIRES_THRESHOLD;
        //get HTML
        $html_raw = $this->_getInlineStyledHtml("passwordRecovery", $this->message_data);

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

        //get the style file
        $html = $this->simpleView->render("mails/$mail", $data);
        $css  = file_get_contents($this->ses['cssFile']);

        $emogrifier = new Emogrifier($html, $css);
        $html       = $emogrifier->emogrify();

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
     * @throws Exception
     * @return string
     */
    public function _sendMessage($html_raw, $subject, $recipients, $tags = array(), $attachments = array(), $async = true)
    {
    	$this->_checkConfigurations();

        //validation
        if (empty($html_raw) || empty($subject) || empty($recipients))
            throw new \Exception("SesTrait::_sendMessage -> Invalid params data for sending email");

        //parse recipients
        if (is_string($recipients))
            $recipients = array($recipients);

        $to = array();
        //create mandrill recipient data struct, push emails to array
        foreach ($recipients as $email)
            array_push($to, array('email' => $email)); //optional name (display name) & type (defaults 'to').

        //set default subject
        if (empty($subject))
            $subject = $this->ses['appName'];

        //Send message email!
        $message = array(
            'html'       => $html_raw,
            'subject'    => $subject,
            'from_email' => $this->ses['senderEmail'],
            'from_name'  => $this->ses['appName'],
            'to'         => $to,
            'tags'       => $tags
            //'inline_css' => true //same as __getInlineStyledHtml method. (generates more delay time)
        );

        //append attachments
        if(!empty($attachments))
           $message['attachments'] = $attachments;

        //send email!
        $response = true;

        try {
        	//mandrill lib instance
        	$mandrill = new Mandrill($this->ses['mandrillKey']);
            $response = $mandrill->messages->send($message, $async);
        }
        catch (Mandrill_Error $e) {
            $response = false;
            // Mandrill errors are thrown as exceptions
            $this->logger->error("SesTrait::_sendMessage -> A mandrill error occurred sending a message (" . get_class($e) . "), trace: " . $e->getMessage());
        }

        return $response;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Check configurations properties
     */
    private function _checkConfigurations()
    {
        if (!isset($this->ses['appName']) || !isset($this->ses['mandrillKey']) || !isset($this->ses['cssFile']))
            throw new \Exception("SesTrait::_checkConfigurations -> SES configuration properties are not defined. (appName, mandrillKey, cssFile)");

        if (!isset($this->ses['senderEmail']) || !isset($this->ses['contactEmail']))
        	throw new \Exception("SesTrait::_checkConfigurations -> SES sender & contact emails are not defined.");
    }
}