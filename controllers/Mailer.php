<?php
/**
 * Mailer Email Service Trait
 * Requires WebCore
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
			"user_entity"    => "User",
			"activation_uri" => "auth/activation/",
			"password_uri"   => "password/new/",
			"from_name"      => $this->config->name,
		];

		//merge confs
		$conf = array_merge($defaults, $conf);

		//append class prefixes
		$conf["user_token_entity"] = App::getClass($conf["user_entity"])."Token";
		$conf["user_entity"]       = App::getClass($conf["user_entity"]);

		if(empty($conf["trans"]))
			$conf["trans"] = \TranslationController::getCoreTranslations("mailer");

		$this->mailer_conf = $conf;
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Ajax Handler Action - Send the contact to message to our app
	 * Requires email, name, message POST params
	 */
	public function sendContactAction()
	{
		$this->onlyAjax();

		$data = $this->handleRequest([
			"email"    => "email",
			"name"     => "string",
			"@message" => "string"
		], "POST");

		$data["subject"] = "Contacto ".$this->config->name;
		$data["to"]      = $this->config->emails->support;

		//extend config
		$this->mailer_conf = array_merge($this->mailer_conf, $data);

		// call listener?
		if (method_exists($this, "onBeforeSendContact"))
			$this->onBeforeSendContact($data);

		//sends email
		$this->sendMessage("contact", $data["subject"], $data["to"]);

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

		//get user token
		$tokens_class = $this->mailer_conf["user_token_entity"];
		$token        = $tokens_class::newTokenIfExpired($user_id, "activation");

		if (!$user || !$token)
			$this->jsonResponse(500);

		//set properties
		$this->mailer_conf["user"]  = $user;
		$this->mailer_conf["email"] = $user->email;
		$this->mailer_conf["url"]   = $this->baseUrl($this->mailer_conf["activation_uri"].$token->encrypted);

		//set message properties
		$subject = $this->mailer_conf["trans"]["SUBJECT_ACTIVATION"];
		$to      = $this->mailer_conf["email"];

		//sends async email
		$this->sendMessage("activation", $subject, $to);
	}

	/**
	 * Async Handler - Sends mail for password recovery
	 * Generates & sends a validation token
	 * @param int $user_id - The user ID
	 */
	public function passwordRecovery($user_id = 0)
	{
		$user_class = $this->mailer_conf["user_entity"];
		$user       = $user_class::getById($user_id);

		//get user token
		$tokens_class = $this->mailer_conf["user_token_entity"];
		$token        = $tokens_class::newTokenIfExpired($user_id, "pass");

		if (!$user || !$token)
			$this->jsonResponse(500);

		//set rendered view
		$this->mailer_conf["user"]       = $user;
		$this->mailer_conf["email"]      = $user->email;
		$this->mailer_conf["url"]        = $this->baseUrl($this->mailer_conf["password_uri"].$token->encrypted);
		$this->mailer_conf["expiration"] = $tokens_class::$TOKEN_EXPIRES_THRESHOLD["pass"];

		//set message properties
		$subject = $this->mailer_conf["trans"]["SUBJECT_PASSWORD"];
		$to      = $this->mailer_conf["email"];
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
		//set mailer sub config
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
		$error = is_string($e) ? $e : $e->getMessage().". File: ".$e->getFile()." [".$e->getLine()."]";

		$admin_emails = !empty($this->config->emails->admins) ? (array)$this->config->emails->admins : $this->config->emails->support;

		if(!is_array($admin_emails))
			$admin_emails = [$admin_emails];

		$data["email"]   = $this->config->emails->sender;
		$data["name"]    = $this->config->name." ".MODULE_NAME;
		$data["message"] = "$error\nData:\n".(empty($data["edata"]) ? "n/a" : json_encode($data["edata"], JSON_UNESCAPED_SLASHES));

		//extend config
		$this->mailer_conf = array_merge($this->mailer_conf, $data);

		$this->logger->debug("Mailer::adminException -> sending exception: ".json_encode($this->mailer_conf, JSON_UNESCAPED_SLASHES));

		//sends the message
		$this->sendMessage("contact", "Admin message", $admin_emails);
	}

	/**
	 * Sends a message through sendgrid API
	 * @param string $template - The template name
	 * @param string $subject - The mail subject
	 * @param mixed(string|array) $recipients - The receiver emails
	 * @param array $attachments - Array with sub-array(s) with content, type and name props
	 * @return array - Resultset
	 */
	public function sendMessage($template, $subject, $recipients, $attachments = [])
	{
		//validation
		if (empty($template) || empty($recipients))
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

		$reply_to = $this->config->emails->support ?? $this->config->emails->sender;

		$html = $this->inlineHtml($template);

		//set message properties
		$message->setFrom($this->config->emails->sender)
				->setFromName($this->mailer_conf["from_name"])
				->setReplyTo($reply_to)
				->setSubject($subject)
				->setHtml($html);

		//add recipients
		foreach ($recipients as $email)
			$message->addTo($email);

		//parse attachments
		$this->_parseAttachments($attachments, $message);

		//send email
		$result = $sendgrid->send($message);
		//sd($sendgrid, $message, $result);

		$this->logger->debug("Mailer::sendMessage -> email message sent to: ".json_encode($recipients, JSON_UNESCAPED_SLASHES)." "
																			 .json_encode($result, JSON_UNESCAPED_SLASHES));
		return $result;
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

		//create dir if not exists
		if(!is_dir(self::$MAILER_CACHE_PATH))
			mkdir(self::$MAILER_CACHE_PATH, 0755);

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
