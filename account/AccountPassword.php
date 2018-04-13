<?php
/**
 * Account Password Trait
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Account;

//imports
use Phalcon\Exception;
use CrazyCake\Phalcon\App;
use CrazyCake\Helpers\ReCaptcha;

/**
 * Account Password
 */
trait AccountPassword
{
	// Traits
	use AccountToken;

	/**
	 * Trait config
	 * @var Array
	 */
	public $account_password_conf;

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * After Execute Route
	 */
	public function afterExecuteRoute()
	{
		parent::afterExecuteRoute();
		//if loggedIn redirect
		$this->redirectLoggedIn(true);
	}

	/**
	 * Initialize Trait
	 * @param Array $conf - The config array
	 */
	public function initAccountPassword($conf = [])
	{
		//defaults
		$defaults = [
			"user_entity"           => "User",
			"redirection_uri"       => "signIn",
			"password_uri"          => "password/new/",
			"password_min_length"   => 8,
			// javascript modules
			"js_modules"            => ["passRecovery" => null],
			"js_recaptcha_callback" => "recaptchaOnLoad"
		];

		//merge confs
		$conf = array_merge($defaults, $conf);

		$conf["user_entity"] = App::getClass($conf["user_entity"]);

		if(empty($conf["trans"]))
			$conf["trans"] = \TranslationController::getCoreTranslations("account");

		$this->account_password_conf = $conf;
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * View - Set New password action (vista donde se crea una nueva contraseña)
	 * @param String $encrypted - The encrypted data
	 */
	public function newAction($encrypted = null)
	{
		try {
			//handle the encrypted data with parent controller
			self::handleEncryptedValidation($encrypted);

			//view vars
			$this->view->setVars([
				"html_title" => $this->account_password_conf["trans"]["CREATE_PASS"],
				"hash"       => $encrypted
			]);

			//load js modules
			$this->loadJsModules($this->account_password_conf["js_modules"]);
		}
		catch (Exception $e) {

			$this->logger->error("AccountPassword::newAction -> Error in account activation [$encrypted]: ".$e->getMessage());

			$this->dispatcher->forward(["controller" => "error", "action" => "expired"]);
			$this->dispatcher->dispatch();
		}
	}

	/**
	 * Send Recovery password email message with further instructions
	 * @param String $email - The user email
	 * @param String $recaptcha - The reCaptcha challenge
	 */
	public function sendRecoveryInstructions($email, $recaptcha = "")
	{
		//google reCaptcha helper
		$recaptcher = new ReCaptcha($this->config->google->reCaptchaKey);

		//check valid reCaptcha
		if (empty($email) || empty($recaptcha) || !$recaptcher->isValid($recaptcha))
			return $this->jsonResponse(400, $this->account_password_conf["trans"]["RECAPTCHA_FAILED"]);

		//check if user exists with active account flag
		$entity = $this->account_password_conf["user_entity"];
		$user   = $entity::getUserByEmail($email, "enabled");

		//if user not exists, send message
		if (!$user)
			$this->jsonResponse(400, $this->account_password_conf["trans"]["ACCOUNT_NOT_FOUND"]);

		//hash sensitive data
		$token_chain = self::newTokenChainCrypt($user->id ?? (string)$user->_id, "pass");

		//sends the message
		$this->sendMailMessage("passwordRecovery", [
			"user"       => $user,
			"email"      => $user->email,
			"url"        => $this->baseUrl($this->account_password_conf["password_uri"].$token_chain),
			"expiration" => self::$TOKEN_EXPIRES["pass"]
		]);

		//set a flash message to show on account controller
		$this->flash->success(str_replace("{email}", $email, $this->account_password_conf["trans"]["PASS_MAIL_SENT"]));

		//send JSON response
		$this->jsonResponse(200, ["redirect" => $this->account_password_conf["redirection_uri"]]);
	}

	/**
	 * Saves a new password set by the user in the post-recovery password view
	 * @param String $hash - Encrypted token (hash)
	 * @param String $password - The input password
	 */
	public function saveNewPassword($hash, $password)
	{
		try {

			list($user_id, $token_type, $token) = self::handleEncryptedValidation($hash);

			//get user
			$entity = $this->account_password_conf["user_entity"];
			$user   = $entity::getById($user_id);

			if (!$user)
				throw new Exception("got an invalid user [$user_id] when validating encrypted data.");

			//pass length
			if (strlen($password) < $this->account_password_conf["password_min_length"])
				throw new Exception($this->account_password_conf["trans"]["PASS_TOO_SHORT"]);

			//saves new pass
			$entity::updateProperty($user_id, "pass", $this->security->hash($password));

			//new user session
			$this->newUserSession($user);

			//delete token
			$this->deleteToken($user_id, $token_type);

			//set a flash message to show on account controller
			$this->flash->success($this->account_password_conf["trans"]["NEW_PASS_SAVED"]);
			
			// redirect response
			$this->jsonResponse(200, ["redirect" => $this->account_password_conf["redirection_uri"]]);
		}
		catch (Exception $e) {

			$this->logger->error("AccountPassword::saveNewPassword -> failed saving new password: ".$e->getMessage());

			$this->jsonResponse(400, $e->getMessage());
		}
	}

	/**
	 * Updates user password
	 * @param String $new_pass - The new password
	 * @param String $current_pass - The current user password
	 */
	public function updatePassword($new_pass, $current_pass)
	{
		try {
			//get model class name & user
			$entity = $this->account_password_conf["user_entity"];
			$user   = $entity::getById($user_id);

			if (empty($new_pass) && empty($current_pass))
				return;
			
			if (!empty($new_pass) && empty($current_pass))
				throw new Exception($this->account_password_conf["trans"]["CURRENT_PASS_EMPTY"]);

			if (strlen($new_pass) < $this->account_password_conf["password_min_length"])
				throw new Exception($this->account_password_conf["trans"]["PASS_TOO_SHORT"]);

			//check current pass
			if (!$this->security->checkHash($current_pass, $user->pass))
				throw new Exception($this->account_password_conf["trans"]["PASS_DONT_MATCH"]);

			//check pass is different to current
			if ($this->security->checkHash($new_pass, $user->pass))
				throw new Exception($this->account_password_conf["trans"]["NEW_PASS_EQUALS"]);

			//saves new pass
			$entity::updateProperty($this->user_session["id"], "pass", $this->security->hash($new_pass));
		}
		catch (Exception $e) {

			$this->jsonResponse(400, $e->getMessage());
		}
	}
}