<?php
/**
 * Account Auth Trait, common actions for account authorization (login, register with activation)
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
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
	use AccountToken;

	/**
	 * Event on user logged in (session)
	 * @param Object $user - The user object
	 */
	abstract public function newUserSession($user);

	/**
	 * Session Destructor with autoredirection (session)
	 */
	abstract public function removeUserSession();

	/**
	 * trait config
	 * @var Array
	 */
	public $AUTH_CONF;

	/**
	 * Account Flags
	 * @var Array
	 */
	public static $FLAGS = ["pending", "enabled", "disabled"];

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Initialize Trait
	 * @param Array $conf - The config array
	 */
	public function initAccountAuth($conf = [])
	{
		$defaults = [
			"user_entity"          => "user",
			"user_key"             => "email",
			"logged_in_uri"        => "account",
			"logged_out_uri"       => "signIn",
			"activation_uri"       => "auth/activation/",
			"csrf"                 => true,
			"recaptcha"            => false,
			"login_attempts"       => 8,
			"login_allowed_states" => ["enabled"]
		];

		// merge confs
		$conf = array_merge($defaults, $conf);

		// append class prefix
		$conf["user_entity"] = App::getClass($conf["user_entity"]);

		if (empty($conf["trans"]))
			$conf["trans"] = \TranslationController::defaultCoreTranslations("account");

		// set configuration
		$this->AUTH_CONF = $conf;
	}

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
		$this->redirectTo($this->AUTH_CONF["logged_out_uri"]);
	}

	/**
	 * Login user by email & pass (POST / XHR)
	 */
	public function loginAction()
	{
		// get model classes
		$entity = $this->AUTH_CONF["user_entity"];
		$params = ["pass" => "string"];

		// user key
		if ($this->AUTH_CONF["user_key"] == "email")
			$params["email"] = "email";
		else
			$params[$this->AUTH_CONF["user_key"]] = "string";

		// validate and filter request params data, second params are the required fields
		$data = $this->handleRequest($params, "POST", $this->AUTH_CONF["csrf"]);

		// find user
		$user = $entity::getByProperties([$this->AUTH_CONF["user_key"] => $data["email"] ?? $data[$this->AUTH_CONF["user_key"]]]);

		if (!$user)
			$this->jsonResponse(400, $this->AUTH_CONF["trans"]["AUTH_FAILED"]);

		// recaptcha validation
		if ($this->AUTH_CONF["recaptcha"] && $this->getLoginAttempts($user->_id) > 4) {

			$recaptcher = new ReCaptcha($this->config->google->reCaptchaKey);

			if (!$recaptcher->isValid($data["recaptcha"] ?? null, "session", 0.1))
				return $this->jsonResponse(400, $this->AUTH_CONF["trans"]["NOT_HUMAN"]);
		}

		// password hash validation
		if (!$this->security->checkHash($data["pass"], $user->pass ?? '')) {

			$this->saveLoginAttempt($user->_id);

			// basic attempts security
			if ($this->getLoginAttempts($user->_id) > $this->AUTH_CONF["login_attempts"])
				$this->jsonResponse(400, $this->AUTH_CONF["trans"]["AUTH_BLOCKED"]);

			$this->jsonResponse(400, $this->AUTH_CONF["trans"]["AUTH_FAILED"]);
		}

		// check user account flag
		if (!in_array($user->flag, $this->AUTH_CONF["login_allowed_states"]))
			$this->jsonResponse(400, str_replace("{email}", $user->email, $this->AUTH_CONF["trans"]["STATE_".strtoupper($user->flag)]));

		// success login
		$this->newUserSession($user);

		// event (can interrupt flux)
		$this->onAfterLoginUser("login");
	}

	/**
	 * Mixed [Normal & XHR] - Register user by email
	 */
	public function registerAction()
	{
		$data = $this->handleRequest([
			"email" => "email",
			"@pass" => "string"
		], "POST", $this->AUTH_CONF["csrf"]);

		// lower case email
		$data["email"] = strtolower(trim($data["email"]));

		// check valid email
		if (!filter_var($data["email"], FILTER_VALIDATE_EMAIL))
			$this->jsonResponse(400, $this->AUTH_CONF["trans"]["INVALID_EMAIL"]);

		$entity = $this->AUTH_CONF["user_entity"];

		// validate if user exists
		if ($entity::getByProperties(["email" => $data["email"]]))
			$this->jsonResponse(400, str_replace("{email}", $data["email"], $this->AUTH_CONF["trans"]["EMAIL_EXISTS"]));

		// remove CSRF key
		unset($data[$this->client->csrfKey]);

		// set properties
		$data["pass"]      = empty($data["pass"]) ? null : $this->security->hash($data["pass"]);
		$data["flag"]      = "pending";
		$data["createdAt"] = $entity::toIsoDate();

		// event
		if (method_exists($this, "onBeforeRegisterUser"))
			$this->onBeforeRegisterUser($data);

		// insert user
		if (!$user = $entity::insert($data)) {

			$this->logger->error("AccountAuth::registerAction -> failed user insertion: ".json_encode($data, JSON_UNESCAPED_SLASHES));
			$this->jsonResponse(500);
		}

		// event (can interrupt flux)
		if (method_exists($this, "onAfterRegisterUser"))
			$this->onAfterRegisterUser($user);

		// send activation mail message
		$this->sendActivationMailMessage($user);

		// set a flash message to show on account controller
		$message = str_replace("{email}", $user->email, $this->AUTH_CONF["trans"]["ACTIVATION_PENDING"]);
		$this->flash->success($message);

		// redirect/response
		if (MODULE_NAME == "api")
			$this->jsonResponse(200, ["message" => $message]);

		$this->redirectTo($this->AUTH_CONF["logged_out_uri"]);
	}

	/**
	 * Action - Activation handler, can dispatch to a view
	 * @param String $hash - The encrypted hash
	 */
	public function activationAction($hash = "")
	{
		try {

			$entity = $this->AUTH_CONF["user_entity"];

			// handle the hash data with parent controller
			list($user_id, $token_type, $token) = self::validateHash($hash);

			// check user pending flag
			$user = $entity::getById($user_id);

			if (!$user || $user->flag == "disabled")
				throw new Exception("invalid user or missing 'pending' flag, userID: $user->id");

			// save new account flag state
			$entity::updateProperties($user_id, ["flag" => "enabled"]);

			// event (can interrupt flux)
			if (method_exists($this, "onActivationSuccess"))
				$this->onActivationSuccess($user);

			// set a flash message to show on account controller
			$this->flash->success($this->AUTH_CONF["trans"]["ACTIVATION_SUCCESS"]);

			// success login
			$this->newUserSession($user);

			// redirect/response
			$this->onAfterLoginUser("activation");
		}
		catch (Exception $e) {

			$this->view->setVar("error_message", $this->trans->_("Tu cuenta ya está activada."));

			$this->logger->error("AccountAuth::activationAction [$hash] -> exception: ".$e->getMessage());
			$this->dispatcher->forward(["controller" => "error", "action" => "expired"]);
		}
	}

	/**
	 * Handles response after login user.
	 * @param String $from - The name of the current flow
	 */
	public function onAfterLoginUser($from = "")
	{
		$uri = $this->AUTH_CONF["logged_in_uri"];

		// check if redirection is set in session
		if ($this->session->has("auth_redirect")) {

			// get redirection uri from session & remove from session
			$uri = $this->session->get("auth_redirect");
			$this->session->remove("auth_redirect");
		}

		$this->redirectTo($uri);
	}

	/**
	 * Sets auth redirect session
	 * @param String $uri - The URL to be redirected
	 */
	public static function setAuthRedirection($uri = "")
	{
		$session = (\Phalcon\DI::getDefault())->getShared("session");

		$session->set("auth_redirect", $uri);
	}

	/**
	 * Sends activation mail message with recaptcha validation
	 * @param Object $user - The user object
	 */
	public function sendActivationMailMessage($user)
	{
		// hash data
		$token_chain = self::newTokenChainCrypt((string)$user->_id, "activation");

		// send activation account email
		return $this->sendMailMessage("accountActivation", [
			"user"  => $user,
			"email" => $user->email,
			"url"   => $this->baseUrl($this->AUTH_CONF["activation_uri"].$token_chain)
		]);
	}

	/**
	 * Access Token validation for API Auth
	 * @param String $token - The input token
	 * @return Object - The token object
	 */
	protected function validateAccessToken($token = "")
	{
		try {

			$token = self::getToken($token, "access");

			if (!$token) throw new Exception("Invalid token");

			return $token;
		}
		catch (Exception $e) { $this->jsonResponse(401, $e->getMessage()); }
	}

	/**
	 * Saves in redis a new login attempt
	 * @param String $user_id
	 */
	protected function saveLoginAttempt($user_id)
	{
		$redis = new \Redis();
		$redis->connect(getenv("REDIS_HOST") ?: "redis");

		$key = "LOGIN_".$user_id;

		$attempts = ($redis->exists($key) ? $redis->get($key) : 0) + 1;

		$redis->set($key, $attempts);
		$redis->expire($key, 3600*6); // hours
		$redis->close();
	}

	/**
	 * Get stored login attempts
	 * @param String $user_id
	 */
	protected function getLoginAttempts($user_id)
	{
		$redis = new \Redis();
		$redis->connect(getenv("REDIS_HOST") ?: "redis");

		$key = "LOGIN_".$user_id;

		$attempts = $redis->exists($key) ? $redis->get($key) : 0;

		$redis->close();

		return $attempts;
	}
}
