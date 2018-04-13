<?php
/**
 * Account Auth Trait, common actions for account authorization (login, register with activation)
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
	// Traits
	use AccountToken;

	/**
	 * Event on user logged in (session)
	 * @param Object $user - The user object
	 */
	abstract public function newUserSession($user);

	/**
	 * Set response on logged in (session)
	 * @param Array $payload - Optional data
	 */
	abstract public function setResponseOnLoggedIn($payload = null);

	/**
	 * Session Destructor with autoredirection (session)
	 */
	abstract public function removeUserSession();

	/**
	 * trait config
	 * @var Array
	 */
	public $account_auth_conf;

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Initialize Trait
	 * @param Array $conf - The config array
	 */
	public function initAccountAuth($conf = [])
	{
		//defaults
		$defaults = [
			"login_uri"      => "signIn",
			"logout_uri"     => "signIn",
			"activation_uri" => "auth/activation/",
			//entities
			"user_entity" => "User",
			"user_key"    => "email"
		];

		//merge confs
		$conf = array_merge($defaults, $conf);

		//append class prefix
		$conf["user_entity"] = App::getClass($conf["user_entity"]);

		if(empty($conf["trans"]))
			$conf["trans"] = \TranslationController::getCoreTranslations("account");

		//set configuration
		$this->account_auth_conf = $conf;
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Handler - Activation link handler, can dispatch to a view
	 * @param String $encrypted - The encrypted data
	 */
	public function activationAction($encrypted)
	{
		//if user is already logged in redirect
		$this->redirectLoggedIn(true);

		try {
			//get user entity
			$entity = $this->account_auth_conf["user_entity"];

			//handle the encrypted data with parent controller
			list($user_id, $token_type, $token) = self::handleEncryptedValidation($encrypted);

			//check user pending flag
			$user = $entity::getById($user_id);

			if (!$user || $user->flag != "pending")
				throw new Exception("missing 'pending' flag for user [$user->id].");

			//save new account flag state
			$entity::updateProperty($user_id, "flag", "enabled");
			//remove activation token
			$this->deleteToken($user_id, "activation");

			//set a flash message to show on account controller
			$this->flash->success($this->account_auth_conf["trans"]["ACTIVATION_SUCCESS"]);

			//success login
			$this->newUserSession($user);
			//redirect/response
			$this->setResponseOnLoggedIn();
		}
		catch (Exception $e) {

			$data = $encrypted ? $this->cryptify->decryptData($encrypted) : "invalid hash";

			$this->logger->error("AccountAuth::activationAction -> Error in account activation, decrypted data [$data]: ".$e->getMessage());
			$this->dispatcher->forward(["controller" => "error", "action" => "expired"]);
		}
	}

	/**
	 * Handler - Logout
	 */
	public function logoutAction()
	{
		//handled by session controller
		$this->removeUserSession();

		if ($this->request->isAjax() || MODULE_NAME == "api")
			$this->jsonResponse(200);

		//redirect to given url, login as default
		$this->redirectTo($this->account_auth_conf["logout_uri"]);
	}

	/**
	 * Login user by email & pass (POST / XHR)
	 */
	public function loginAction()
	{
		//get model classes
		$entity = $this->account_auth_conf["user_entity"];
		$params = ["pass" => "string"];

		if($this->account_auth_conf["user_key"] == "email") 
			$params = ["email" => "email"];

		//validate and filter request params data, second params are the required fields
		$data = $this->handleRequest($params, "POST");

		//find this user
		if($this->account_auth_conf["user_key"] == "email")
			$user = $entity::getUserByEmail($data["email"]);
		else
			$user = $this->getLoginUser($data); //must implement

		//check user & given hash with the one stored (wrong combination)
		if (!$user || !$this->security->checkHash($data["pass"], $user->pass))
			$this->jsonResponse(400, $this->account_auth_conf["trans"]["AUTH_FAILED"]);

		//check user account flag
		if ($user->flag != "enabled") {

			//set message
			$namespace = "STATE_".strtoupper($user->flag);
			$message   = $this->account_auth_conf["trans"][$namespace];

			//for API handle alerts & warning as errors,
			$this->jsonResponse(400, $message, "warning", $namespace);
		}

		//success login
		$this->newUserSession($user);

		//session controller, dispatch response
		$this->setResponseOnLoggedIn();
	}

	/**
	 * Mixed [Normal & XHR] - Register user by email
	 */
	public function registerAction()
	{
		//params
		$data = $this->handleRequest([
			"email" => "email",
			"pass"  => "string"
		], "POST");

		//get user entity
		$entity = $this->account_auth_conf["user_entity"];

		// validate if user exists
		if($entity::getUserByEmail($data["email"])) {

			$msg = str_replace("{email}", $data["email"], $this->account_auth_conf["trans"]["EMAIL_EXISTS"]);
			$msg = str_replace("{link}", $this->account_auth_conf["login_uri"], $msg);

			$this->jsonResponse(400, $msg);
		}

		//remove CSRF key
		unset($data[$this->client->tokenKey]);
		//set pending email confirmation status
		$data["flag"] = "pending";

		//event trigger
		if(method_exists($this, "beforeRegisterUser"))
			$this->beforeRegisterUser($data);
		
		//insert user
		$user = $entity::insert($data);

		//if user not exists, show error message
		if (!$user) {

			$this->logger->error("AccountAuth::registerAction -> failed inserting user ".json_encode(data));
			$this->jsonResponse(400);
		}

		//set a flash message to show on account controller
		$this->flash->success(str_replace("{email}", $user->email, $this->account_auth_conf["trans"]["ACTIVATION_PENDING"]));

		//hash sensitive data
		$token_chain = self::newTokenChainCrypt($user->id ?? (string)$user->_id, "activation");

		//send activation account email
		$this->sendMailMessage("accountActivation", [
			"user"  => $user,
			"email" => $user->email,
			"url"   => $this->baseUrl($this->account_pass_conf["activation_uri"].$token_chain)
		]);

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
	 * @param String $email - The user email
	 * @param String $recaptcha - The reCaptcha challenge
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
		$entity = $this->account_auth_conf["user_entity"];
		$user   = $entity::getUserByEmail($email, "pending");

		//check if user exists is a pending account
		if (!$user)
			$this->jsonResponse(400, $this->account_auth_conf["trans"]["ACCOUNT_NOT_FOUND"]);

		//hash sensitive data
		$token_chain = self::newTokenChainCrypt($user->id ?? (string)$user->_id, "activation");

		//send activation account email
		$this->sendMailMessage("accountActivation", [
			"user"  => $user,
			"email" => $user->email,
			"url"   => $this->baseUrl($this->account_pass_conf["activation_uri"].$token_chain)
		]);

		//set payload
		$payload = str_replace("{email}", $email, $this->account_auth_conf["trans"]["ACTIVATION_PENDING"]);

		//send JSON response
		$this->jsonResponse(200, $payload);
	}

	/**
	 * Access Token validation for API Auth
	 * @param String $token - The input token
	 * @return Object - The token ORM object
	 */
	protected function validateAccessToken($token = "")
	{
		try {
			//get token
			$data = $this->handleRequest([
				"token" => "string"
			], "MIXED");

			$token = self::getToken($data["token"], "access");

			if(!$token)
				throw new Exception("Invalid token");

			return $token;
		}
		catch(Exception $e) {

			$this->jsonResponse(401, $e->getMessage());
		}
	}
}
