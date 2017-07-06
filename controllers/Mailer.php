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
use CrazyCake\Phalcon\App;

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
	protected static $MAILER_CSS_FILE = PROJECT_PATH."ui/volt/mailing/css/app.css";

	/**
	 * Temporal path
	 * @var string
	 */
	protected static $MAILER_CACHE_PATH = STORAGE_PATH."cache/mailer/";

	/**
	 * trait config
	 * @var array
	 */
	public $mailer_conf;

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Initialize Trait
	 * @param array $conf - The config array
	 */
	public function initMailer($conf = [])
	{
		//defaults
		$defaults = [
			"user_entity" => "User"
		];

		//merge confs
		$conf = array_merge($defaults, $conf);
		//append class prefixes
		$conf["user_token_entity"] = App::getClass($conf["user_entity"])."Token";
		$conf["user_entity"]       = App::getClass($conf["user_entity"]);

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
			"email"   => "email", //user-sender
			"name"    => "string",
			"message" => "string"
		], "POST");

		$data["subject"] = "Contacto ".$this->config->name;
		$data["to"]      = $this->config->emails->contact;

		// call listener?
		if (method_exists($this, "onBeforeSendContact"))
			$this->onBeforeSendContact($data);

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
	public function accountActivation($user_id = 0)
	{
		$user_class = $this->mailer_conf["user_entity"];
		$user       = $user_class::getById($user_id);

		if (!$user)
			$this->jsonResponse(403);

		//get user token
		$tokens_class = $this->mailer_conf["user_token_entity"];
		$token        = $tokens_class::newTokenIfExpired($user_id, "activation");

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
	public function passwordRecovery($user_id = 0)
	{
		$user_class = $this->mailer_conf["user_entity"];
		$user       = $user_class::getById($user_id);

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
		$this->mailer_conf["config"] = $this->config;

		//get the view in mailing folder
		$html = $this->simpleView->render("mailing/$template", $this->mailer_conf);

		//apply a HTML inliner if a stylesheet is present
		if(is_file(self::$MAILER_CSS_FILE)) {

			$emogrifier = new Emogrifier($html, file_get_contents(self::$MAILER_CSS_FILE));
			$emogrifier->addExcludedSelector("head");
			$emogrifier->addExcludedSelector("meta");
			//inliner
			$html = $emogrifier->emogrify();
		}

		return $html;
	}

	/**
	 * Sends a system mail for exception alert
	 * @param mixed[object, string] $e - An exception object or string message
	 * @param object $data - Informative appended data
	 */
	public function adminException($e = "", $data = [])
	{
		$error = is_string($e) ? $e : $e->getMessage().". File: ".$e->getFile()." (".$e->getLine().")";

		$this->logger->debug("Mailer::adminException -> sending exception: $error");

		$log = file_get_contents($this->logger->log(\Phalcon\Logger::DEBUG)->getPath());

		if($log) {
			$lines       = explode("\n", $log);
			$log_history = implode("\n", array_slice($lines, -8)); // last x lines
		}

		//Sending a warning to admin users!
		$this->sendAdminMessage(array_merge([
			"subject" => "Exception Notification",
			"to"      => $this->config->emails->support,
			"email"   => $this->config->emails->sender, //user-sender
			"name"    => $this->config->name." webapp",
			"message" => "$error\nLog History:\n".$log_history
		], $data));
	}

	/**
	 * Async Handler - Sends contact email (always used)
	 * @param array $data - Must contains keys "name", "email" & "message"
	 * @return json response
	 */
	public function sendAdminMessage($data = [])
	{
		//set message properties
		$subject = isset($data["subject"]) ? $data["subject"] : $this->config->name;
		$to      = isset($data["to"]) ? $data["to"] : $this->config->emails->support;

		//add prefix "data" to each element in array
		$this->mailer_conf = array_combine( array_map(function($k) { return "data_".$k; }, array_keys($data)), $data);

		//sends async email
		$this->sendMessage("contact", $subject, $to);
	}

	/**
	 *  Sends a message through sendgrid API
	 * @param string $template - The template name
	 * @param string $subject - The mail subject
	 * @param mixed(string|array) $recipients - The receiver emails
	 * @param array $attachments - Array with sub-array(s) with content, type and name props
	 * @return array - Resultset
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
			$subject = $this->config->name;

		//service instance
		$sendgrid = new \SendGrid($this->config->sendgrid->apiKey);
		$message  = new \SendGrid\Email();

		$support_email = !empty($this->config->emails->support) ? $this->config->emails->support : $this->config->emails->sender;

		$message->setFrom($this->config->emails->sender)
				->setFromName($this->config->name)
				->setReplyTo($support_email)
				->setSubject($subject)
				->setHtml($this->inlineHtml($template));

		//add recipients
		foreach ($recipients as $email)
			$message->addTo($email);

		//parse attachments
		$this->_parseAttachments($attachments, $message);
		//s($sendgrid, $message);exit;

		//send email
		$result = $sendgrid->send($message);
		//s($result);

		return $result;
	}

	/**
	 * View for debuging - Renders a mail message or a template
	 * @param string $view - The input view
	 */
	public function previewAction($view = null)
	{
		if (APP_ENV == "production" || empty($view))
			$this->redirectToNotFound();

		$user_class = $this->mailer_conf["user_entity"];

		$user = $user_class::findFirst();

		//set rendered view vars
		$this->mailer_conf["data_url"]  = $this->baseUrl('fake/path');
		$this->mailer_conf["data_user"] = $user;

		//for contact template
		$this->mailer_conf["data_name"]    = $user ? $user->first_name." ".$user->last_name : "";
		$this->mailer_conf["data_email"]   = $user ? $user->email : "caker@crazycake.tech";
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
