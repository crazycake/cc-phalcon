<?php
/**
 * Account Auth Trait, common actions for account authorization (login, register with activation)
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Account;

use Phalcon\Exception;

use CrazyCake\Phalcon\App;
use CrazyCake\Helpers\Forms;
use CrazyCake\Helpers\ReCaptcha;

/**
 * Account Authentication
 */
trait AccountAuth
{
	// traits
	use AccountToken;

	/**
	 * Event on user logged in (session)
	 * @param Object $user - The user object
	 */
	abstract public function newUserSession($user);

	/**
	 * Set response on logged in (session)
	 */
	abstract public function setResponseOnLoggedIn();

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
		$defaults = [
			"user_entity"     => "user",
			"user_key"        => "email",
			"login_uri"       => "signIn",
			"logout_uri"      => "signIn",
			"activation_uri"  => "auth/activation/",
			"recaptcha_login" => false
		];

		// merge confs
		$conf = array_merge($defaults, $conf);

		// append class prefix
		$conf["user_entity"] = App::getClass($conf["user_entity"]);

		if (empty($conf["trans"]))
			$conf["trans"] = \TranslationController::getCoreTranslations("account");

		// set configuration
		$this->account_auth_conf = $conf;
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Action - Logout
	 */
	public function logoutAction()
	{
		// handled by session controller
		$this->removeUserSession();

		if ($this->request->isAjax() || MODULE_NAME == "api")
			$this->jsonResponse(200);

		// redirect to given url, login as default
		$this->redirectTo($this->account_auth_conf["logout_uri"]);
	}

	/**
	 * Login user by email & pass (POST / XHR)
	 */
	public function loginAction()
	{
		// get model classes
		$entity = $this->account_auth_conf["user_entity"];
		$params = ["pass" => "string"];

		if ($this->account_auth_conf["user_key"] == "email")
			$params = ["email" => "email"];

		// validate and filter request params data, second params are the required fields
		$data = $this->handleRequest($params, "POST");

		// find user
		$user = $this->account_auth_conf["user_key"] == "email" ? $entity::getUserByEmail($data["email"]) : $this->getLoginUser($data);

		// recaptcha challenge?
		if ($this->account_auth_conf["recaptcha_login"]) {

			$recaptcher = new ReCaptcha($this->config->google->reCaptchaKey);
			$recaptcha  = $data["g-recaptcha-response"] ?? null;

			if (empty($recaptcha) || !$recaptcher->isValid($recaptcha))
				return $this->jsonResponse(400, $this->account_auth_conf["trans"]["RECAPTCHA_FAILED"]);
		}

		// check user & given hash with the one stored (wrong combination)
		if (!$user || !$this->security->checkHash($data["pass"], $user->pass ?? ''))
			$this->jsonResponse(400, $this->account_auth_conf["trans"]["AUTH_FAILED"]);

		// check user account flag
		if ($user->flag != "enabled") {

			// set message
			$namespace = "STATE_".strtoupper($user->flag);
			$message   = str_replace("{email}", $user->email, $this->account_auth_conf["trans"][$namespace]);

			// for API handle alerts & warning as errors,
			$this->jsonResponse(400, $message, "warning", $namespace);
		}

		// success login
		$this->newUserSession($user);

		// session controller, dispatch response
		$this->setResponseOnLoggedIn();
	}

	/**
	 * Mixed [Normal & XHR] - Register user by email
	 */
	public function registerAction()
	{
		$data = $this->handleRequest([
			"email" => "email",
			"pass"  => "string"
		], "POST");

		// lower case email
		$data["email"] = strtolower($data["email"]);

		// check valid email
		if (!filter_var($data["email"], FILTER_VALIDATE_EMAIL))
			$this->jsonResponse(400, $this->account_auth_conf["trans"]["INVALID_EMAIL"]);

		$entity = $this->account_auth_conf["user_entity"];

		// validate if user exists
		if ($entity::getUserByEmail($data["email"]))
			$this->jsonResponse(400, str_replace("{email}", $data["email"], $this->account_auth_conf["trans"]["EMAIL_EXISTS"]));

		// remove CSRF key
		unset($data[$this->client->csrfKey]);
		// set pending email confirmation status
		$data["flag"] = "pending";

		// event
		if (method_exists($this, "onBeforeRegisterUser"))
			$this->onBeforeRegisterUser($data);

		// insert user
		if (!$user = $entity::insert($data)) {

			$this->logger->error("AccountAuth::registerAction -> failed user insertion: ".json_encode(data));
			$this->jsonResponse(400);
		}

		// event
		if (method_exists($this, "onAfterRegisterUser"))
			$this->onAfterRegisterUser($user);

		// send activation mail message
		$this->sendActivationMailMessage($user);

		// set a flash message to show on account controller
		$message = str_replace("{email}", $user->email, $this->account_auth_conf["trans"]["ACTIVATION_PENDING"]);
		$this->flash->success($message);

		// redirect/response
		if (MODULE_NAME == "api")
			$this->jsonResponse(200, ["message" => $message]);

		$this->redirectTo($this->account_auth_conf["logout_uri"]);
	}

	/**
	 * Action - Activation handler, can dispatch to a view
	 * @param String $hash - The encrypted hash
	 */
	public function activationAction($hash = "")
	{
		// if user is already logged in redirect
		$this->redirectLoggedIn();

		try {

			$entity = $this->account_auth_conf["user_entity"];

			// handle the hash data with parent controller
			list($user_id, $token_type, $token) = self::validateHash($hash);

			// check user pending flag
			$user = $entity::getById($user_id);

			if (!$user || $user->flag != "pending")
				throw new Exception("invalid user or missing 'pending' flag, userID: $user->id");

			// save new account flag state
			$entity::updateProperty($user_id, "flag", "enabled");
			// remove activation token
			$this->deleteToken($user_id, "activation");

			// custom behavior event
			if (method_exists($this, "onActivationSuccess")) {

				$this->onActivationSuccess($user);
				return;
			}

			// set a flash message to show on account controller
			$this->flash->success($this->account_auth_conf["trans"]["ACTIVATION_SUCCESS"]);

			// success login
			$this->newUserSession($user);

			// redirect/response
			$this->setResponseOnLoggedIn();
		}
		catch (Exception $e) {

			$this->logger->error("AccountAuth::activationAction [$hash] -> exception: ".$e->getMessage());
			$this->dispatcher->forward(["controller" => "error", "action" => "expired"]);
		}
	}

	/**
	 * Sends activation mail message with recaptcha validation
	 * @param Object $user - The user object
	 */
	public function sendActivationMailMessage($user)
	{
		// hash data
		$token_chain = self::newTokenChainCrypt($user->id ?? (string)$user->_id, "activation");

		// send activation account email
		return $this->sendMailMessage("accountActivation", [
			"user"  => $user,
			"email" => $user->email,
			"url"   => $this->baseUrl($this->account_auth_conf["activation_uri"].$token_chain)
		]);
	}

	/**
	 * Sends activation mail message with email & recaptcha validation
	 * @param String $email - The user email
	 * @param String $recaptcha - The reCaptcha challenge
	 */
	public function sendActivationMailMessageWithRecaptcha($email, $recaptcha = "")
	{
		// google reCaptcha helper
		$recaptcher = new ReCaptcha($this->config->google->reCaptchaKey);

		// check valid reCaptcha
		if (empty($email) || !$recaptcher->isValid($recaptcha))
			return $this->jsonResponse(400, $this->account_auth_conf["trans"]["RECAPTCHA_FAILED"]);

		// get model classes
		$entity = $this->account_auth_conf["user_entity"];

		// check if user exists is a pending account
		if (!$user = $entity::getUserByEmail($email, "pending"))
			$this->jsonResponse(400, $this->account_auth_conf["trans"]["NOT_FOUND"]);

		// send activation mail message
		$this->sendActivationMailMessage($user);

		// set a flash message to show on account controller
		$message = str_replace("{email}", $user->email, $this->account_auth_conf["trans"]["ACTIVATION_PENDING"]);

		// redirect/response
		$this->jsonResponse(200, ["message" => $message]);
	}

	/**
	 * Access Token validation for API Auth
	 * @param String $token - The input token
	 * @return Object - The token ORM object
	 */
	protected function validateAccessToken($token = "")
	{
		try {

			$data = $this->handleRequest(["token" => "string"], "MIXED");

			$token = self::getToken($data["token"], "access");

			if (!$token)
				throw new Exception("Invalid token");

			return $token;
		}
		catch(Exception $e) {

			$this->jsonResponse(401, $e->getMessage());
		}
	}
}
