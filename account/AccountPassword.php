<?php
/**
 * Account Password Trait
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Account;

use Phalcon\Exception;
use CrazyCake\Phalcon\App;
use CrazyCake\Helpers\ReCaptcha;

/**
 * Account Password
 */
trait AccountPassword
{
	use AccountToken;

	/**
	 * Trait config
	 * @var Array
	 */
	public $PASSWORD_CONF;

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Initialize Trait
	 * @param Array $conf - The config array
	 */
	public function initAccountPassword($conf = [])
	{
		$defaults = [
			"user_entity"         => "user",
			"password_uri"        => "password/create/{hash}",
			"password_min_length" => 8,
			"recaptcha"           => false
		];

		// merge confs
		$conf = array_merge($defaults, $conf);

		$conf["user_entity"] = App::getClass($conf["user_entity"]);

		if (empty($conf["trans"]))
			$conf["trans"] = \TranslationController::getCoreTranslations("account");

		$this->PASSWORD_CONF = $conf;
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Send Recovery password email message with further instructions
	 * @param String $email - The user email
	 */
	public function sendRecoveryInstructions($email = "")
	{
		// recaptcha validation
		if ($this->PASSWORD_CONF["recaptcha"]) {

			$data = $this->handleRequest([], "POST");

			$recaptcher = new ReCaptcha($this->config->google->reCaptchaKey);

			if (!$recaptcher->isValid($data["recaptcha"] ?? null, "session", 0.1))
				return $this->jsonResponse(400, $this->PASSWORD_CONF["trans"]["NOT_HUMAN"]);
		}

		$entity = $this->PASSWORD_CONF["user_entity"];
		$user   = $entity::getByProperties(["email" => $email, "flag" => "enabled"]);

		// if user not exists, send message
		if (!$user)
			$this->jsonResponse(400, $this->PASSWORD_CONF["trans"]["NOT_FOUND"]);

		// hash sensitive data
		$token_chain  = self::newTokenChainCrypt((string)$user->_id, "pass");
		$password_uri = str_replace("{hash}", $token_chain, $this->PASSWORD_CONF["password_uri"]);

		// sends the message
		$this->sendMailMessage("passwordRecovery", [
			"user"       => $user,
			"email"      => $user->email,
			"url"        => $this->baseUrl($password_uri),
			"expiration" => self::$TOKEN_EXPIRES["pass"]
		]);
	}

	/**
	 * View - Set New password action (vista donde se crea una nueva contraseña)
	 * @param String $hash - The encrypted data as hash
	 */
	public function newPasswordView($hash = null)
	{
		try {

			if ($this->isLoggedIn())
				throw new Exception("user is already logged in");

			// handle the encrypted data with parent controller
			list($user_id, $token_type, $token) = self::validateHash($hash);

			// saves hash in session
			$this->session->set("passwordHash", $hash);

			return $user_id;
		}
		catch (Exception $e) {

			$this->logger->error("AccountPassword::newPasswordView -> exception in new password view [$hash]: ".$e->getMessage());

			$this->redirectTo("error/expired");
		}
	}

	/**
	 * Saves a new password set by the user in the post-recovery password view
	 * @param String $password - The input password
	 */
	public function saveNewPassword($password)
	{
		try {

			$hash = $this->session->get("passwordHash");

			list($user_id, $token_type, $token) = self::validateHash($hash);

			// get user
			$entity = $this->PASSWORD_CONF["user_entity"];
			$user   = $entity::getById($user_id);

			if (!$user)
				throw new Exception("got an invalid user [$user_id] when validating hash.");

			// pass length
			if (strlen($password) < $this->PASSWORD_CONF["password_min_length"])
				throw new Exception($this->PASSWORD_CONF["trans"]["PASS_TOO_SHORT"]);

			// saves new pass
			$entity::updateProperties($user_id, ["pass" => $this->security->hash($password)]);

			// new user session
			$this->newUserSession($user);

			// delete token
			$this->deleteToken($user_id, $token_type);

			return true;
		}
		catch (Exception $e) {

			$this->logger->error("AccountPassword::saveNewPassword -> failed saving new password: ".$e->getMessage());

			$this->jsonResponse(400, $e->getMessage());
		}
	}

	/**
	 * Updates user password with validation
	 * @param String $new_pass - The new password
	 * @param String $current_pass - The current user password (input verification)
	 */
	public function updatePassword($new_pass, $current_pass)
	{
		try {
			// get model class name & user
			$entity = $this->PASSWORD_CONF["user_entity"];
			$user   = $entity::getById($this->user_session["id"]);

			if (empty($user) || empty($new_pass) || empty($current_pass))
				return;

			if (strlen($new_pass) < $this->PASSWORD_CONF["password_min_length"])
				throw new Exception($this->PASSWORD_CONF["trans"]["PASS_TOO_SHORT"]);

			if (!empty($new_pass) && empty($current_pass))
				throw new Exception($this->PASSWORD_CONF["trans"]["CURRENT_PASS_EMPTY"]);

			if (strlen($new_pass) < $this->PASSWORD_CONF["password_min_length"])
				throw new Exception($this->PASSWORD_CONF["trans"]["PASS_TOO_SHORT"]);

			// check current pass input
			if (empty($user->pass) || !$this->security->checkHash($current_pass, $user->pass))
				throw new Exception($this->PASSWORD_CONF["trans"]["PASS_DONT_MATCH"]);

			// check pass is different to current
			if ($this->security->checkHash($new_pass, $user->pass))
				throw new Exception($this->PASSWORD_CONF["trans"]["NEW_PASS_EQUALS"]);

			// saves new pass
			$entity::updateProperties($this->user_session["id"], ["pass" => $this->security->hash($new_pass)]);

			return true;
		}
		catch (Exception $e) { $this->jsonResponse(400, $e->getMessage()); }
	}
}
