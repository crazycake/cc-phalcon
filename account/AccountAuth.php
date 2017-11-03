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
	 * Set response on logged in
	 * @param boolean $session_redirection - enables session redirection
	 * @param array $payload - Optional data
	 */
	abstract public function setResponseOnLogin($session_redirection = true, $payload = null);

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
			"js_recaptcha" => false,
			"oauth"        => false,
			"logout_uri"   => "signIn",
			//entities
			"user_entity"  => "User"
		];

		//merge confs
		$conf = array_merge($defaults, $conf);
		//append class prefixes
		$conf["user_token_entity"] = App::getClass($conf["user_entity"])."Token";
		$conf["user_entity"]       = App::getClass($conf["user_entity"]);

		//set configuration
		$this->account_auth_conf = $conf;
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Handler - Activation link handler, can dispatch to a view
	 * @param string $encrypted_data - The encrypted data
	 */
	public function activationAction($encrypted_data = null)
	{
		//if user is already logged in redirect
		$this->redirectToAccount(true);

		//get decrypted data
		try {
			//get model classes
			$user_class  = $this->account_auth_conf["user_entity"];
			$token_class = $this->account_auth_conf["user_token_entity"];

			//handle the encrypted data with parent controller
			$data = $token_class::handleEncryptedValidation($encrypted_data);
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
			//session
			$this->setResponseOnLogin();
		}
		catch (Exception $e) {

			$data = $encrypted_data ? $this->cryptify->decryptData($encrypted_data) : "invalid hash";

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
			$this->jsonResponse(401, $this->account_auth_conf["trans"]["AUTH_FAILED"]);

		//check user account flag
		if ($user->account_flag != "enabled") {

			//set message
			$msg = $user->account_flag == "pending" ?
					$this->account_auth_conf["trans"]["ACCOUNT_PENDING"] :
					$this->account_auth_conf["trans"]["ACCOUNT_DISABLED"];

			//for API handle alerts & warning as errors,
			$this->jsonResponse(400, $msg); //browser custom handler
		}

		//set payload
		$payload = null;

		//for api oauth
		if($this->account_auth_conf["oauth"])
			$payload = $token_class::newTokenIfExpired($user->id, "access");

		//success login
		$this->onLogin($user->id);

		//session controller, dispatch response
		$this->setResponseOnLogin(false, $payload);
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
		$this->setResponseOnLogin();
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
