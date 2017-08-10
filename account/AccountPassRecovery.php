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
			"pass_min_length" => 8,
			"redirection_uri" => "signIn",
			//entities
			"user_entity" => "User"
		];

		//merge confs
		$conf = array_merge($defaults, $conf);
		//append class prefixes
		$conf["user_token_entity"] = App::getClass($conf["user_entity"])."Token";
		$conf["user_entity"]       = App::getClass($conf["user_entity"]);

		$this->account_pass_conf = $conf;
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * View - Password recovery Action (send instructions view)
	 */
	public function recoveryAction()
	{
		//view vars
		$this->view->setVar("html_title", $this->account_pass_conf["trans"]["TITLE_RECOVERY"]);
		$this->view->setVar("js_recaptcha", true); //load reCaptcha

		//load javascript modules
		$this->loadJsModules([
			"passRecovery" => null
		]);
	}

	/**
	 * View - Set New password action (vista donde se crea una nueva contraseña)
	 * @param string $encrypted_data - The encrypted data
	 */
	public function newAction($encrypted_data = null)
	{
		//get decrypted data
		try {
			//handle the encrypted data with parent controller
			$tokens_class = $this->account_pass_conf["user_token_entity"];
			$tokens_class::handleEncryptedValidation($encrypted_data);

			//view vars
			$this->view->setVar("html_title", $this->account_pass_conf["trans"]["TITLE_CREATE_PASS"]);
			$this->view->setVar("edata", $encrypted_data); //pass to view the encrypted data

			//load javascript modules
			$this->loadJsModules([
				"passRecovery" => null
			]);
		}
		catch (Exception $e) {
			$this->logger->error("AccountPass::newAction -> Error in account activation, encrypted data (" . $encrypted_data . "). Err: " . $e->getMessage());
			$this->dispatcher->forward(["controller" => "error", "action" => "expired"]);
		}
	}

	/**
	 * Ajax - Send Recovery password email Message with further instructions
	 */
	public function sendRecoveryInstructionsAction()
	{
		//validate and filter request params data, second params are the required fields
		$data = $this->handleRequest([
			"email"                 => "email",
			"@g-recaptcha-response" => "string",
		], "POST");

		//google reCaptcha helper
		$recaptcha = new ReCaptcha($this->config->google->reCaptchaKey);

		//check valid reCaptcha
		if (empty($data["g-recaptcha-response"]) || !$recaptcha->isValid($data["g-recaptcha-response"])) {
			//show error message
			return $this->jsonResponse(200, $this->account_pass_conf["trans"]["RECAPTCHA_FAILED"], "alert");
		}

		//check if user exists is a active account
		$user_class = $this->account_pass_conf["user_entity"];
		$user       = $user_class::getUserByEmail($data["email"], "enabled");

		//if user not exists, send message
		if (!$user)
			$this->jsonResponse(200, $this->account_pass_conf["trans"]["ACCOUNT_NOT_FOUND"], "alert");

		//send email message with password recovery steps
		$this->sendMailMessage("passwordRecovery", $user->id);

		//set a flash message to show on account controller
		$this->flash->success(str_replace("{email}", $data["email"], $this->account_pass_conf["trans"]["PASS_MAIL_SENT"]));

		//send JSON response
		$this->jsonResponse(200, ["redirect" => $this->account_pass_conf["redirection_uri"]]);
	}

	/**
	 * Ajax - Saves a new password set by the user in the post-recovery password view
	 */
	public function saveNewPasswordAction()
	{
		//validate and filter request params data, second params are the required fields
		$data = $this->handleRequest([
			"edata" => "string",
			"pass"  => "string"
		], "POST");

		//validate encrypted data
		$payload = false;

		try {
			//get model classes
			$user_class   = $this->account_pass_conf["user_entity"];
			$tokens_class = $this->account_pass_conf["user_token_entity"];

			$edata = $tokens_class::handleEncryptedValidation($data["edata"]);
			list($user_id, $token_type, $token) = $edata;

			//get user
			$user = $user_class::getById($user_id);

			if (!$user)
				throw new Exception("got an invalid user (id:" . $user_id . ") when validating encrypted data.");

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
		}
		catch (Exception $e) {
			$this->logger->error("AccountPass::saveNewPasswordAction -> Error saving new password. Trace: ".$e->getMessage());
			return $this->jsonResponse($e->getMessage());
		}

		//send JSON response
		$this->jsonResponse(200, ["redirect" => $this->account_pass_conf["redirection_uri"]]);
	}
}
