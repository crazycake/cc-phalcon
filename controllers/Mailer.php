<?php
/**
 * Mailer Service Trait, requires WebCore
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Controllers;

//imports
use Phalcon\Exception;
use Pelago\Emogrifier;
//core
use CrazyCake\Phalcon\App;

/**
 * Mailer Trait
 */
trait Mailer
{
	/**
 	 * Mailing CSS file
	 * @var String
	 */
	protected static $MAILER_CSS_FILE = PROJECT_PATH."ui/volt/mailing/css/app.css";

	/**
	 * trait config
	 * @var Array
	 */
	public $mailer_conf;

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Initialize Trait
	 * @param Array $conf - The config array
	 */
	public function initMailer($conf = [])
	{
		//defaults
		$defaults = [
			"user_entity" => "user",
			"from_name"   => $this->config->name
		];

		//merge confs
		$conf = array_merge($defaults, $conf);

		//append class prefixes
		$conf["user_entity"] = App::getClass($conf["user_entity"]);

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
	 * @param Array $data - The array
	 */
	public function accountActivation($data = [])
	{
		//merge mailer conf
		$this->mailer_conf = array_merge($this->mailer_conf, $data);

		//set message properties
		$subject = $this->mailer_conf["trans"]["SUBJECT_ACTIVATION"];
		$to      = $this->mailer_conf["email"];
		
		//sends the message
		$this->sendMessage("activation", $subject, $to);
	}

	/**
	 * Async Handler - Sends mail for password recovery
	 * @param Array $data - The array
	 */
	public function passwordRecovery($data = [])
	{
		//merge mailer conf
		$this->mailer_conf = array_merge($this->mailer_conf, $data);

		//set message properties
		$subject = $this->mailer_conf["trans"]["SUBJECT_PASSWORD"];
		$to      = $this->mailer_conf["email"];
		
		//sends the message
		$this->sendMessage("passwordRecovery", $subject, $to);
	}

	/**
	 * Generates a new HTML styled with inline CSS as style attribute
	 * DI must have simpleView service
	 * @param String $template - The mail template view
	 * @return String
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
	 * @param Mixed $e - An exception object or string message
	 * @param Object $data - Informative appended data
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
	 * @param String $template - The template name
	 * @param String $subject - The mail subject
	 * @param Mixed $recipients - The receiver emails
	 * @param Array $attachments - Array with sub-array(s) with content, type and name props
	 * @return Resultset
	 */
	public function sendMessage($template, $subject, $recipients, $attachments = [])
	{
		//validation
		if (empty($template) || empty($recipients))
			throw new Exception("Mailer::sendMessage -> Invalid params data for sending mail");

		//parse recipients
		if (is_string($recipients))
			$recipients = explode(",", $recipients);

		//set default subject
		if (empty($subject))
			$subject = $this->config->name;

		//service instance
		$sendgrid = new \SendGrid($this->config->sendgrid->apiKey);
		//emails
		$from     = new \SendGrid\Email($this->mailer_conf["from_name"],  $this->config->emails->sender);
		$reply_to = new \SendGrid\ReplyTo($this->config->emails->support ?? $this->config->emails->sender, $this->mailer_conf["from_name"]);
		//content
		$content = new \SendGrid\Content("text/html", $this->inlineHtml($template));
		//mail object
		$mail = new \SendGrid\Mail($from, $subject, (new \SendGrid\Email(null, $recipients[0])), $content);
		$mail->setReplyTo($reply_to);
		
		//add recipients
		foreach ($recipients as $i => $email) {

			if(empty($i)) continue;

			$mail->personalization[0]->addTo(new \SendGrid\Email(null, $email));
		}

		//parse attachments
		$this->_parseAttachments($attachments, $mail);

		//send mail
		$result = $sendgrid->client->mail()->send()->post($mail);

		$body = json_encode($result->body() ?? "", JSON_UNESCAPED_SLASHES);

		$this->logger->debug("Mailer::sendMessage -> mail message sent: ".json_encode($recipients, JSON_UNESCAPED_SLASHES)." [$body]");
		return $result;
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Parse attachments to be sended to sendgrid API service
	 * @param Array $attachments - Attachments array
	 * @param Object $mail - The sendgrid mail object
	 * @return Array The parsed array data
	 */
	private function _parseAttachments($attachments = null, &$mail)
	{
		if(empty($attachments))
			return;

		foreach ($attachments as $attachment) {

			if(empty($attachment["name"]) || empty($attachment["binary"]))
				continue;

			//set attachment
			$att = new \SendGrid\Attachment();
			$att->setDisposition("attachment");
			$att->setContentId($attachment["id"] ?? uniqid());
			$att->setType($attachment["type"] ?? null);
			$att->setContent($attachment["binary"]);
			$att->setFilename($attachment["name"]);

			$mail->addAttachment($att);
		}
	}
}
