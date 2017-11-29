<?php
/**
 * Account Pass Trait
 * Common actions for account operations
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Account;

//imports
use CrazyCake\Phalcon\App;
use Phalcon\Exception;
use CrazyCake\Helpers\ReCaptcha;

/**
 * Account Password Recovery
 */
trait AccountPassRecovery
{
	/**
	 * Trait config
	 * @var array
	 */
	public $account_pass_conf;

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * After Execute Route
	 */
	public function afterExecuteRoute()
	{
		parent::afterExecuteRoute();
		//if loggedIn redirect to account
		$this->redirectToAccount(true);
	}

	/**
	 * Initialize Trait
	 * @param array $conf - The config array
	 */
	public function initAccountPassRecovery($conf = [])
	{
		//defaults
		$defaults = [
			"user_entity"           => "User",
			"redirection_uri"       => "signIn",
			"pass_min_length"       => 8,
			"js_modules"            => ["passRecovery" => null],
			"js_recaptcha_callback" => "recaptchaOnLoad"
		];

		//merge confs
		$conf = array_merge($defaults, $conf);
		//append class prefixes
		$conf["user_token_entity"] = App::getClass($conf["user_entity"])."Token";
		$conf["user_entity"]       = App::getClass($conf["user_entity"]);

		if(empty($conf["trans"]))
			$conf["trans"] = \TranslationController::getCoreTranslations("password");

		$this->account_pass_conf = $conf;
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * View - Set New password action (vista donde se crea una nueva contraseña)
	 * @param string $encrypted - The encrypted data
	 */
	public function newAction($encrypted = null)
	{
		try {
			//handle the encrypted data with parent controller
			$tokens_class = $this->account_pass_conf["user_token_entity"];
			$tokens_class::handleEncryptedValidation($encrypted);

			//view vars
			$this->view->setVars([
				"html_title" => $this->account_pass_conf["trans"]["CREATE_PASS"],
				"hash"       => $encrypted
			]);

			//load js modules
			$this->loadJsModules($this->account_pass_conf["js_modules"]);
		}
		catch (Exception $e) {

			$this->logger->error("AccountPass::newAction -> Error in account activation (".$encrypted."). Err: ".$e->getMessage());

			$this->dispatcher->forward(["controller" => "error", "action" => "expired"]);
			$this->dispatcher->dispatch();
		}
	}

	/**
	 * Send Recovery password email Message with further instructions
	 */
	public function sendRecoveryInstructions($email, $recaptcha = "")
	{
		//google reCaptcha helper
		$recaptcher = new ReCaptcha($this->config->google->reCaptchaKey);

		//check valid reCaptcha
		if (empty($email) || empty($recaptcha) || !$recaptcher->isValid($recaptcha))
			return $this->jsonResponse(400, $this->account_pass_conf["trans"]["RECAPTCHA_FAILED"]);

		//check if user exists is a active account
		$user_class = $this->account_pass_conf["user_entity"];
		$user       = $user_class::getUserByEmail($data["email"], "enabled");

		//if user not exists, send message
		if (!$user)
			$this->jsonResponse(400, $this->account_pass_conf["trans"]["ACCOUNT_NOT_FOUND"]);

		//send email message with password recovery steps
		$this->sendMailMessage("passwordRecovery", $user->id);

		//set a flash message to show on account controller
		$this->flash->success(str_replace("{email}", $data["email"], $this->account_pass_conf["trans"]["PASS_MAIL_SENT"]));

		//send JSON response
		$this->jsonResponse(200, ["redirect" => $this->account_pass_conf["redirection_uri"]]);
	}

	/**
	 * Saves a new password set by the user in the post-recovery password view
	 * @param string $hash - Encrypted token
	 * @param string $pass - The input password
	 */
	public function saveNewPassword($hash, $pass)
	{
		try {
			//get model classes
			$user_class   = $this->account_pass_conf["user_entity"];
			$tokens_class = $this->account_pass_conf["user_token_entity"];

			$decrypted = $tokens_class::handleEncryptedValidation($hash);
			list($user_id, $token_type, $token) = $decrypted;

			//get user
			$user = $user_class::getById($user_id);

			if (!$user)
				throw new Exception("got an invalid user (id:".$user_id.") when validating encrypted data.");

			//pass length
			if (strlen($data["pass"]) < $this->account_pass_conf["pass_min_length"])
				throw new Exception($this->account_pass_conf["trans"]["PASS_TOO_SHORT"]);

			//save new account flag state
			$user->update(["pass" => $this->security->hash($data["pass"])]);

			//get token object
			$token = $tokens_class::getTokenByUserAndValue($user_id, $token_type, $token);
			//delete user token
			$token->delete();

			//set a flash message to show on account controller
			$this->flash->success($this->account_pass_conf["trans"]["NEW_PASS_SAVED"]);

			//abstract parent controller
			$this->onLogin($user->id);

			// redirect response
			$this->jsonResponse(200, ["redirect" => $this->account_pass_conf["redirection_uri"]]);
		}
		catch (Exception $e) {

			$this->logger->error("AccountPass::saveNewPassword -> failed saving new password. Trace: ".$e->getMessage());

			$this->jsonResponse(400, $e->getMessage());
		}
	}
}
