<?php
/**
 * Mailer Service Trait, requires WebCore
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Controllers;

use Phalcon\Exception;
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
	public $MAILER_CONF;

	/**
	 * Initialize Trait
	 * @param Array $conf - The config array
	 */
	public function initMailer($conf = [])
	{
		$defaults = [
			"from_name" => $this->config->name
		];

		// merge confs
		$conf = array_merge($defaults, $conf);

		$this->MAILER_CONF = $conf;
	}

	/**
	 * Ajax Handler Action - Send the contact to message to our app
	 * Requires email, name, message POST params
	 */
	public function sendContact($data = [])
	{
		// extend config
		$this->MAILER_CONF = array_merge($this->MAILER_CONF, $data);

		$subject = "Contact - ".$this->config->name;
		$to      = $this->config->emails->support;

		// sends email
		$this->sendMessage("contact", $subject, $to);
	}

	/**
	 * Sends a system mail for exception alert
	 * @param Mixed $e - An exception object or string message
	 * @param Object $data - Informative appended data
	 */
	public function adminException($e = "", $data = [])
	{
		$data["name"]  = $this->config->name." - ".MODULE_NAME;
		$data["email"] = $this->config->emails->sender;

		$data["message"] = is_string($e) ? $e : $e->getMessage().". File: ".$e->getFile()." [".$e->getLine()."]";

		if (!empty($data["trace"]))
			$data["message"] .= "\nTrace:\n".json_encode($data["trace"], JSON_UNESCAPED_SLASHES);

		// extend config
		$this->MAILER_CONF = array_merge($this->MAILER_CONF, $data);

		// sends the message
		$this->sendMessage("contact", "Admin Exception", (array)$this->config->emails->admins);
	}

	/**
	 * Generates a new HTML styled with inline CSS as style attribute
	 * DI must have simpleView service
	 * @param String $template - The mail template view
	 * @return String
	 */
	public function inlineHtml($template = "")
	{
		// set mailer sub config
		$this->MAILER_CONF["config"] = ["name" => $this->config->name, "emails" => $this->config->emails]; // logs security

		// get the view in mailing folder
		$html = $this->simpleView->render("mailing/$template", $this->MAILER_CONF);

		// apply a HTML inliner if a stylesheet is present
		if (is_file(self::$MAILER_CSS_FILE)) {

			$emogrifier = new \Pelago\Emogrifier($html, file_get_contents(self::$MAILER_CSS_FILE));
			$emogrifier->addExcludedSelector("head");
			$emogrifier->addExcludedSelector("meta");

			$html = $emogrifier->emogrify(); //inline styles
		}

		return $html;
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
		// validation
		if (empty($template) || empty($recipients))
			throw new Exception("Mailer::sendMessage -> Invalid params data for sending mail");

		// parse recipients
		if (is_string($recipients))
			$recipients = explode(",", $recipients);

		$recipients = array_unique($recipients); // unique filter

		// set default subject
		if (empty($subject))
			$subject = $this->config->name;

		$sendgrid = new \SendGrid($this->config->sendgrid->apiKey);

		$from     = new \SendGrid\Email($this->MAILER_CONF["from_name"],  $this->config->emails->sender);
		$reply_to = new \SendGrid\ReplyTo($this->config->emails->support ?? $this->config->emails->sender, $this->MAILER_CONF["from_name"]);

		$content = new \SendGrid\Content("text/html", $this->inlineHtml($template));

		$mail = new \SendGrid\Mail($from, $subject, (new \SendGrid\Email(null, $recipients[0])), $content);
		$mail->setReplyTo($reply_to);

		// add recipients
		foreach ($recipients as $i => $email) {

			if (empty($i)) continue;

			$mail->personalization[0]->addTo(new \SendGrid\Email(null, $email));
		}

		// parse attachments
		$this->_parseAttachments($attachments, $mail);

		// send!
		$result = $sendgrid->client->mail()->send()->post($mail);

		$body = json_encode($result->body() ?? "", JSON_UNESCAPED_SLASHES);

		$this->logger->debug("Mailer::sendMessage -> mail message SENT to: ".json_encode($recipients)." [$body]");
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
		if (empty($attachments)) return;

		foreach ($attachments as $attachment) {

			if (empty($attachment["name"]) || empty($attachment["binary"])) continue;

			// attachment
			$att = new \SendGrid\Attachment();
			$att->setDisposition("attachment");
			$att->setContentId($attachment["id"] ?? uniqid());
			$att->setType($attachment["type"] ?? null);
			$att->setContent(base64_encode($attachment["binary"]));
			$att->setFilename($attachment["name"]);

			$mail->addAttachment($att);
		}
	}
}
