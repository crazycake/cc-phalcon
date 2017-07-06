<?php
/**
 * Session Trait
 * Common actions for account operations
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Account;

//imports
use Phalcon\Exception;

//core
use CrazyCake\Phalcon\App;

/**
 * Account Session Handler
 */
trait AccountSession
{
	/**
	 * Set user Session as logged in
	 * @param object $user - The user ORM object
	 * @return array
	 */
	abstract protected function onSessionSave($user);

	/**
	 * Config var
	 * @var array
	 */
	public $account_session_conf;

	/**
	 * Stores user session as array for direct access
	 * @var array
	 */
	protected $user_session;

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Initialize Trait
	 * @param array $conf - The config array
	 */
	public function initAccountSession($conf = [])
	{
		//defaults
		$defaults = [
			//entities
			"user_entity" => "User",
			//excluded user props to be saved in session
			"user_session_filter_view" => ["id", "account_flag", "auth"]
		];

		//merge confs
		$conf = array_merge($defaults, $conf);
		//append class prefixes
		$conf["user_entity"] = App::getClass($conf["user_entity"]);

		$this->account_session_conf = $conf;

		//set session var
		$this->user_session = $this->getUserSession();
		//set user data for view, filter is passed to exclude some properties
		$this->_setUserDataForView();
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Check that user is logged in
	 * @return boolean
	 */
	protected function isLoggedIn()
	{
		if (!$this->session->has("user"))
			return false;

		//get user session
		$user_session = $this->session->get("user");

		if (!is_array($user_session) || !isset($user_session["id"]) || !isset($user_session["auth"]))
			return false;

		$user_class = $this->account_session_conf["user_entity"];

		if ($user_class::getById($user_session["id"]) == false)
			return false;

		return $user_session["auth"] ? true : false;
	}

	/**
	 * Handle logged status, if user is not logged In kick him out!
	 */
	protected function requireLoggedIn()
	{
		//check if user is logged in, if not dispatch to auth/logout
		if ($this->isLoggedIn())
			return true;

		//for ajax request sends a forbidden warning
		if ($this->request->isAjax()) {
			$this->jsonResponse(403);
		}
		else {
			$this->dispatcher->forward(["controller" => "auth", "action" => "logout"]);
			$this->dispatcher->dispatch();
		}
	}

	/**
	 * Set user Session as logged in
	 * @param int $user_id - The user ID
	 */
	protected function onLogin($user_id)
	{
		//get user data from DB
		$user_class = $this->account_session_conf["user_entity"];
		$user       = $user_class::getById($user_id);

		if (!$user)
			throw new Exception("User not found, cant set session auth");

		//update user state
		$last_login = date("Y-m-d H:i:s");
		$user->update(["last_login" => $last_login]);

		//set user data
		$session_data = [
			"auth"         => true,
			"id"           => $user->id,
			"email"        => $user->email,
			"first_name"   => $user->first_name,
			"last_name"    => $user->last_name,
			"account_flag" => $user->account_flag,
			"last_login"   => $last_login
		];

		//optional props
		if(isset($user->role))
			$session_data["role"] = $user->role;

		//call abstract method
		$session_data = array_merge($session_data, $this->onSessionSave($user));
		//save in session
		$this->session->set("user", $session_data);
	}

	/**
	 * Handles response on logged in event, check for pending redirection. Default uri is 'account'.
	 * @param string $uri - The URI to redirect after loggedIn
	 * @param array $payload - Sends a payload response instead of redirection (optional)
	 */
	protected function onLoginDispatch($uri = "account", $payload = null)
	{
		//check if redirection is set in session
		if ($uri && $this->session->has("auth_redirect")) {
			//get redirection uri from session
			$uri = $this->session->get("auth_redirect");
			//remove from session
			$this->session->remove("auth_redirect");
		}

		if($uri === false)
			$uri = "account";

		//check for ajax request
		if ($this->request->isAjax() || MODULE_NAME == "api") {
			$this->jsonResponse(200, empty($payload) ? ["redirect" => $uri] : $payload);
		}
		else {
			$this->redirectTo($uri);
		}
	}

	/**
	 * Set redirection URL for after loggedIn event
	 * @param string $uri - The URL to be redirected
	 */
	protected function setRedirectionOnLogin($uri = "")
	{
		if (empty($uri))
			$uri = $this->getRequestedUri();

		$this->session->set("auth_redirect", $uri);
	}

	/**
	 * Removes pending session redirection
	 * @return boolean
	 */
	protected function removePendingRedirection()
	{
		if (!$this->session->has("auth_redirect"))
			return false;

		return $this->session->remove("auth_redirect");
	}

	/**
	 * Get logged in user session data
	 * @param array $filter - Filters sensitive data
	 * @return array - The session array
	 */
	protected function getUserSession($filter = [])
	{
		if (!$this->isLoggedIn())
			return false;

		//get user session
		$user_session = $this->session->get("user");

		//filter unwanted props
		if (!empty($filter)) {
			foreach ($filter as $key)
				unset($user_session[$key]);
		}

		//return session data
		return $user_session;
	}

	/**
	 * Update user session data
	 * @param array $data - Input user data array
	 * @return boolean
	 */
	protected function updateUserSession($data = [])
	{
		//get user session
		$user_session = $this->session->get("user");

		//update props
		foreach ($data as $key => $value) {

			if (isset($user_session[$key]))
				$user_session[$key] = $value;
		}

		//save in session
		$this->session->set("user", $user_session);
	}

	/**
	 * Event - Destroy user session data and redirect to home
	 * @param string $uri - The URI to redirect
	 */
	protected function onLogout($uri = "signIn")
	{
		//unset all user session data
		$this->session->remove("user");

		//redirect to given url, login as default
		$this->redirectTo($uri);
	}

	/**
	 * Add a new custom object to user session
	 * @param string $key - The key name of the session (required)
	 * @param string $obj - The object (required)
	 * @param string $index - The index of the array (optional)
	 * @return boolean
	 */
	protected function addSessionObject($key = "session_objects", $obj = null, $index = null)
	{
		if (is_null($obj))
			return false;

		//get array stored in session
		$objects = [];
		if ($this->session->has($key))
			$objects = $this->session->get($key);

		//push object to array
		if (is_null($index))
			array_push($objects, $obj);
		else
			$objects[$index] = $obj;

		//save in session
		$this->session->set($key, $objects);
		return true;
	}

	/**
	 * Get custom objects stored in session
	 * @param string $key - The key name of the session
	 * @return mixed [boolean|array]
	 */
	protected function getSessionObjects($key = "session_objects")
	{
		if (!$this->session->has($key))
			return false;

		return $this->session->get($key);
	}

	/**
	 * Removes custom session object
	 * @param string $key - The key name of the session
	 * @param string $index - The index in array to be removed
	 * @return boolean
	 */
	protected function removeSessionObject($key = "session_objects", $index = null)
	{
		if (!$this->session->has($key) || is_null($index))
			return false;

		$objects = $this->getSessionObjects($key);
		//unset
		unset($objects[$index]);
		//save again in session
		$this->session->set($key, $objects);
		return true;
	}

	/**
	 * Destroy session custom objects stored in session
	 * @param string $key - The key name of the session
	 * @return boolean
	 */
	protected function destroySessionObjects($key = "session_objects")
	{
		//check if data exists
		if (!$this->session->has($key))
			return false;

		$this->session->remove($key);
		return true;
	}

	/**
	 * Redirect to account controller, cames from a loggedIn
	 * @param boolean $check_logged_in - Checks if user is logged in, if not skips redirect
	 */
	protected function redirectToAccount($check_logged_in = false)
	{
		if ($check_logged_in && !$this->isLoggedIn())
			return;

		$this->redirectTo("account");
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Set user data object for view
	 * @access private
	 * @param array $filter - A string array of properties to filter
	 */
	private function _setUserDataForView()
	{
		$filter = $this->account_session_conf["user_session_filter_view"];

		//Load view data only for non-ajax requests, set user data var for view
		if (!$this->request->isAjax()) {
			$this->view->setVar("user_session", $this->getUserSession($filter));
		}
	}
}
