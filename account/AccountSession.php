<?php
/**
 * Session Trait, common actions for account session
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Account;

use Phalcon\Exception;

use CrazyCake\Phalcon\App;

/**
 * Account Session Handler
 */
trait AccountSession
{
	/**
	 * Set user Session as logged in
	 * @param Object $user - A user object
	 * @return Array
	 */
	abstract protected function onSessionSave($user);

	/**
	 * Config var
	 * @var Array
	 */
	public $account_session_conf;

	/**
	 * Stores user session as array for direct access
	 * @var Array
	 */
	protected $user_session;

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Initialize Trait
	 * @param Array $conf - The config array
	 */
	public function initAccountSession($conf = [])
	{
		$defaults = [
			"user_entity"        => "user",
			"logged_in_uri"      => "account",
			"ignored_properties" => ["pass"]
		];

		// merge confs
		$conf = array_merge($defaults, $conf);
		// append class prefixes
		$conf["user_entity"] = App::getClass($conf["user_entity"]);

		$this->account_session_conf = $conf;

		// set session var
		$this->user_session = $this->getUserSession();

		// set user data for view, filter is passed to exclude some properties
		$this->_setUserSessionForView($this->user_session);
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Check that user is logged in
	 * @return Boolean
	 */
	protected function isLoggedIn()
	{
		if (!$this->session->has("user"))
			return false;

		// get user session
		$user_session = $this->session->get("user");

		if (empty($user_session) || empty($user_session["id"]) || empty($user_session["auth"]))
			return false;

		$user_class = $this->account_session_conf["user_entity"];

		if (!$user_class::getById($user_session["id"]))
			return false;

		return true;
	}

	/**
	 * Handle logged status, if user is not logged In kick him out!
	 */
	protected function requireLoggedIn()
	{
		// check if user is logged in, if not dispatch to auth/logout
		if ($this->isLoggedIn())
			return true;

		// for ajax request or API mode sends a forbidden warning
		if ($this->request->isAjax() || MODULE_NAME == "api")
			$this->jsonResponse(401);

		// forward to logout
		$this->dispatcher->forward(["controller" => "auth", "action" => "logout"]);
		$this->dispatcher->dispatch();
		die();
	}

	/**
	 * Stores a new user session
	 * @param Object $user - User object
	 */
	protected function newUserSession($user)
	{
		// set user data
		$user_session         = json_decode(json_encode($user), true);
		$user_session["auth"] = true;

		// mongo ID special case
		if (!empty($user_session["_id"])) {

			$user_session["id"] = current($user_session["_id"]);
			unset($user_session["_id"]);
		}

		$filter = $this->account_session_conf["ignored_properties"];

		foreach ($filter as $key)
			unset($user_session[$key]);

		// call abstract method
		$user_session = array_merge($user_session, $this->onSessionSave($user));

		// save in session
		$this->session->set("user", $user_session);

		$this->user_session = $user_session;
	}

	/**
	 * Get logged in user session data
	 * @return Array - The session array
	 */
	protected function getUserSession()
	{
		return $this->isLoggedIn() ? $this->session->get("user") : false;
	}

	/**
	 * Update user session data
	 * @param Array $data - Input user data array
	 * @return Boolean
	 */
	protected function updateUserSession($data = [])
	{
		// get user session
		$user_session = $this->session->get("user");

		// update props
		foreach ($data as $key => $value)
			$user_session[$key] = $value;

		// save in session
		$this->session->set("user", $user_session);

		$this->user_session = $this->getUserSession();
	}

	/**
	 * Event - Destroy user session data
	 */
	protected function removeUserSession()
	{
		// unset all user session data
		$this->session->remove("user");
	}

	/**
	 * Handles response on login event, check for pending redirection.
	 */
	protected function setResponseOnLoggedIn()
	{
		$uri = $this->account_session_conf["logged_in_uri"]; //default logged in uri

		// check if redirection is set in session
		if ($this->session->has("auth_redirect")) {

			// get redirection uri from session & remove from session
			$uri = $this->session->get("auth_redirect");
			$this->session->remove("auth_redirect");
		}

		$this->redirectTo($uri);
	}

	/**
	 * Set redirection URL for after loggedIn event
	 * @param String $uri - The URL to be redirected
	 */
	protected function setRedirectionOnLoggedIn($uri = "")
	{
		if (empty($uri))
			$uri = $this->getRequestedUri();

		$this->session->set("auth_redirect", $uri);
	}

	/**
	 * Removes pending session redirection
	 * @return Boolean
	 */
	protected function removeRedirectionOnLoggedIn()
	{
		if (!$this->session->has("auth_redirect"))
			return false;

		return $this->session->remove("auth_redirect");
	}

	/**
	 * Redirect to logged_in URI
	 * @param Boolean $check_logged_in - Checks if user is logged in, if not skips redirect
	 */
	protected function redirectLoggedIn($check_logged_in = true)
	{
		// skip redirect?
		if ($check_logged_in && !$this->isLoggedIn())
			return;

		$this->redirectTo($this->account_session_conf["logged_in_uri"]);
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Forwards user session to view
	 * @access private
	 * @param Array $filter - A string array of properties to filter
	 */
	private function _setUserSessionForView($user_session = [])
	{
		if ($this->request->isAjax() || !$this->di->has("view"))
			return;

		// filter some sensitive props?
		$filter = $this->account_session_conf["ignored_properties"];

		foreach ($filter as $key)
			unset($user_session[$key]);

		// load view data only for non-ajax requests, set user data var for view
		$this->view->setVar("user_session", $user_session);
	}
}
