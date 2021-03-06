<?php
/**
 * Session Trait, common actions for account session
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Account;

use CrazyCake\Phalcon\App;

/**
 * Account Session Handler
 */
trait AccountSession
{
	/**
	 * Config var
	 * @var Array
	 */
	public $SESSION_CONF;

	/**
	 * Stores user session as array for direct access
	 * @var Array
	 */
	protected $user_session;

	/**
	 * Initialize Trait
	 * @param Array $conf - The config array
	 */
	public function initAccountSession($conf = [])
	{
		$defaults = [
			"ignored_properties" => ["pass", "createdAt", "lastSession", "lastSid", "lastIp"]
		];

		// merge & set conf
		$this->SESSION_CONF = array_merge($defaults, $conf);

		// set session var
		$this->user_session = $this->getUserSession();

		// set user data for view, filter is passed to exclude some properties
		$this->_setSessionViewVars($this->user_session);
	}

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

		if (empty($user_session["id"]) || empty($user_session["auth"]))
			return false;

		// get entity
		$user_class = !empty($user_session["entity"]) ? App::getClass($user_session["entity"]) : false;

		// NOTE: compat with no entity saved
		if (!$user_class || !$user_class::findOne($user_session["id"]))
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
		if ($this->request->isAjax())
			$this->jsonResponse(401);

		// forward to logout
		$this->dispatcher->forward(["controller" => "auth", "action" => "logout"]);
		$this->dispatcher->dispatch();
	}

	/**
	 * Redirect to logged_in URI
	 * @param String $uri - The input uri to redirect
	 * @return Mixed
	 */
	protected function redirectLoggedIn($uri = "account")
	{
		return $this->isLoggedIn() ? $this->redirectTo($uri) : false;
	}

	/**
	 * Stores a new user session
	 * @param Object $user - User object
	 * @param String $entity - User entity
	 */
	protected function newUserSession($user, $entity)
	{
		// set user data
		$session           = json_decode(json_encode($user), true);
		$session["entity"] = strtolower(str_replace("\\", "", $entity));
		$session["auth"]   = true;

		// mongo ID special case
		if (!empty($session["_id"])) {

			$session["id"] = current($session["_id"]);
			unset($session["_id"]);
		}

		$filter = $this->SESSION_CONF["ignored_properties"];

		foreach ($filter as $key)
			unset($session[$key]);

		// call optional method
		if (method_exists($this, "onSessionSave"))
			$this->onSessionSave($user, $session);

		$this->user_session = $session;

		// save in session
		$this->session->set("user", $this->user_session);
	}

	/**
	 * Get logged in user session data
	 * @return Array - The session array
	 * @return Mixed
	 */
	protected function getUserSession()
	{
		return $this->isLoggedIn() ? $this->session->get("user") : false;
	}

	/**
	 * Update user session data
	 * @param Array $data - Input user data array
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
		// destroy session
		if ($this->session->has("user")) {

			$this->session->regenerateId();
			$this->session->destroy();
		}
	}

	/**
	 * Sets view vars
	 * @access private
	 * @param Array $user_session - The user session data
	 */
	private function _setSessionViewVars($user_session = [])
	{
		if ($this->request->isAjax() || !$this->di->has("view"))
			return;

		// filter some sensitive props?
		$filter = $this->SESSION_CONF["ignored_properties"];

		foreach ($filter as $key)
			unset($user_session[$key]);

		// load view data only for non-ajax requests, set user data var for view
		$this->view->setVar("user_session", $user_session);
	}
}
