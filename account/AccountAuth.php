<?php
/**
 * Account Manager Trait
 * Common actions for account operations
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Account;

//imports
use Phalcon\Exception;

//core
use CrazyCake\Phalcon\App;
use CrazyCake\Helpers\Forms;
use CrazyCake\Helpers\ReCaptcha;

/**
 * Account Authentication
 */
trait AccountAuth
{
	/**
	 * Event on user logged in
	 * @param int $user_id - The user id logged in
	 */
	abstract public function onLogin($user_id);

	/**
	 * Set response on logged in (for session implementation)
	 * @param array $payload - Optional data
	 */
	abstract public function setResponseOnLogin($payload = null);

	/**
	 * Session Destructor with Autoredirection (logout)
	 * @param string $uri - The post redirection URI
	 */
	abstract public function onLogout($uri = "");

	/**
	 * trait config
	 * @var array
	 */
	public $account_auth_conf;

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Initialize Trait
	 * @param array $conf - The config array
	 */
	public function initAccountAuth($conf = [])
	{
		//defaults
		$defaults = [
			"oauth"      => false,
			"logout_uri" => "signIn",
			//entities
			"user_entity" => "User"
		];

		//merge confs
		$conf = array_merge($defaults, $conf);
		//append class prefixes
		$conf["user_token_entity"] = App::getClass($conf["user_entity"])."Token";
		$conf["user_entity"]       = App::getClass($conf["user_entity"]);

		if(empty($conf["trans"]))
			$conf["trans"] = \TranslationController::getCoreTranslations("auth");

		//set configuration
		$this->account_auth_conf = $conf;
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Handler - Activation link handler, can dispatch to a view
	 * @param string $encrypted - The encrypted data
	 */
	public function activationAction($encrypted = null)
	{
		//if user is already logged in redirect
		$this->redirectToAccount(true);

		//get decrypted data
		try {
			//get model classes
			$user_class  = $this->account_auth_conf["user_entity"];
			$token_class = $this->account_auth_conf["user_token_entity"];

			//handle the encrypted data with parent controller
			$data = $token_class::handleEncryptedValidation($encrypted);
			//assign values
			list($user_id, $token_type, $token) = $data;

			//check user-flag if is really pending
			$user = $user_class::getById($user_id);

			if (!$user || $user->account_flag != "pending")
				throw new Exception("user (id: ".$user->id.") dont have a pending account flag.");

			//save new account flag state
			$user->update(["account_flag" => "enabled"]);

			//get token object and remove it
			$token = $token_class::getTokenByUserAndValue($user_id, $token_type, $token);
			//delete user token
			$token->delete();

			//set a flash message to show on account controller
			$this->flash->success($this->account_auth_conf["trans"]["ACTIVATION_SUCCESS"]);

			//success login
			$this->onLogin($user_id);
			//redirect/response
			$this->setResponseOnLogin();
		}
		catch (Exception $e) {

			$data = $encrypted ? $this->cryptify->decryptData($encrypted) : "invalid hash";

			$this->logger->error("AccountAuth::activationAction -> Error in account activation, decrypted data (".$data."). Msg: ".$e->getMessage());
			$this->dispatcher->forward(["controller" => "error", "action" => "expired"]);
		}
	}

	/**
	 * Handler - Logout
	 */
	public function logoutAction()
	{
		//handled by session controller
		$this->onLogout($this->account_auth_conf["logout_uri"]);
	}

	/**
	 * Mixed [Normal & XHR] - Login user by email & pass
	 */
	public function loginAction()
	{
		//validate and filter request params data, second params are the required fields
		$data = $this->handleRequest([
			"email" => "email",
			"pass"  => "string"
		], "POST");

		//get model classes
		$user_class  = $this->account_auth_conf["user_entity"];
		$token_class = $this->account_auth_conf["user_token_entity"];

		//find this user
		$user = $user_class::getUserByEmail($data["email"]);

		//check user & given hash with the one stored (wrong combination)
		if (!$user || !$this->security->checkHash($data["pass"], $user->pass))
			$this->jsonResponse(400, $this->account_auth_conf["trans"]["AUTH_FAILED"]);

		//check user account flag
		if ($user->account_flag != "enabled") {

			//set message
			$msg = $user->account_flag == "pending" ?
					$this->account_auth_conf["trans"]["ACCOUNT_PENDING"] :
					$this->account_auth_conf["trans"]["ACCOUNT_DISABLED"];

			//for API handle alerts & warning as errors,
			$this->jsonResponse(400, $msg, "warning", "ACCOUNT_".strtoupper($user->account_flag));
		}

		//set payload
		$payload = null;

		//for api oauth
		if($this->account_auth_conf["oauth"])
			$payload = $token_class::newTokenIfExpired($user->id, "access");

		//success login
		$this->onLogin($user->id);

		//session controller, dispatch response
		$this->setResponseOnLogin($payload);
	}

	/**
	 * Mixed [Normal & XHR] - Register user by email
	 */
	public function registerAction()
	{
		$default_params = [
			"email"      => "email",
			"pass"       => "string",
			"first_name" => "string",
			"last_name"  => "string"
		];

		$setting_params = $this->account_auth_conf["required_fields"] ?? [];

		//validate and filter request params data, second params are the required fields
		$data = $this->handleRequest(array_merge($default_params, $setting_params), "POST");

		//check data
		if(empty($data["email"]) || empty($data["first_name"]) || empty($data["last_name"]))
			$this->jsonResponse(404);

		//validate names
		$nums = "0123456789";
		if (strcspn($data["first_name"], $nums) != strlen($data["first_name"]) ||
			strcspn($data["last_name"], $nums) != strlen($data["last_name"])) {

			$this->jsonResponse(400, $this->account_auth_conf["trans"]["INVALID_NAME"]);
		}

		//format to capitalized name
		$data["first_name"] = mb_convert_case($data["first_name"], MB_CASE_TITLE, "UTF-8");
		$data["last_name"]  = mb_convert_case($data["last_name"], MB_CASE_TITLE, "UTF-8");

		//get model classes
		$user_class = $this->account_auth_conf["user_entity"];
		//set pending email confirmation status
		$data["account_flag"] = "pending";

		//Save user, validations are applied in model
		$user = new $user_class();

		//call abstract method
		if(method_exists($this, "beforeRegisterUser"))
			$this->beforeRegisterUser($user, $data);

		//if user dont exists, show error message
		if (!$user->save($data))
			$this->jsonResponse(400, $user->messages());

		//set a flash message to show on account controller
		$this->flash->success(str_replace("{email}", $user->email, $this->account_auth_conf["trans"]["ACTIVATION_PENDING"]));

		//send activation account email
		$this->sendMailMessage("accountActivation", $user->id);

		//set response
		//redirect/response
		if (MODULE_NAME == "api")
			$this->jsonResponse(200, ["message" => $this->account_auth_conf["trans"]["ACTIVATION_PENDING"]]);

		else if($this->request->isAjax())
			$this->jsonResponse(200, ["redirect" => $this->account_auth_conf["logout_uri"]]);

		// default behaviour
		$this->redirectTo($this->account_auth_conf["logout_uri"]);
	}

	/**
	 * Sends activation mail message with recaptcha validation
	 */
	public function sendActivationMailMessage($email, $recaptcha)
	{
		//google reCaptcha helper
		$recaptcher = new \CrazyCake\Helpers\ReCaptcha($this->config->google->reCaptchaKey);

		//check valid reCaptcha
		if (empty($email) || empty($recaptcha) || !$recaptcher->isValid($recaptcha)) {
			//show error message
			return $this->jsonResponse(400, $this->account_auth_conf["trans"]["RECAPTCHA_FAILED"]);
		}

		//get model classes
		$user_class = $this->account_auth_conf["user_entity"];
		$user       = $user_class::getUserByEmail($email, "pending");

		//check if user exists is a pending account
		if (!$user)
			$this->jsonResponse(400, $this->account_auth_conf["trans"]["ACCOUNT_NOT_FOUND"]);

		//send email message with password recovery steps
		$this->sendMailMessage("accountActivation", $user->id);

		//set payload
		$payload = str_replace("{email}", $email, $this->account_auth_conf["trans"]["ACTIVATION_PENDING"]);

		//send JSON response
		$this->jsonResponse(200, $payload);
	}

	/**
	 * Access Token validation for API Auth
	 * @param string $token - The input token
	 * @return object - The token ORM object
	 */
	protected function validateAccessToken($token = "")
	{
		try {
			//get token
			$data = $this->handleRequest([
				"token" => "string"
			], "MIXED");

			$token_class = $this->account_auth_conf["user_token_entity"];
			$token       = $token_class::getTokenByValue($data["token"], "access");

			if(!$token)
				throw new Exception("Invalid token");

			return $token;
		}
		catch(Exception $e) {
			$this->jsonResponse(401, $e->getMessage());
		}
	}
}
