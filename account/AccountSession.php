<?php
/**
 * Session Trait
 * Requires a Frontend or Backend Module
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Account;

//imports
use Phalcon\Exception;

//core
use CrazyCake\Phalcon\AppModule;
use CrazyCake\Helpers\Dates;

/**
 * Account Session Handler
 */
trait AccountSession
{
    /**
     * Listener - Append properties to user session
     * @param object $user - The user object reference
     */
    abstract protected function onUserLoggedIn($user);


    /** const **/
    protected static $DEFAULT_USER_PROPS_FILTER = ["id", "account_flag", "auth"];

    /**
     * Stores user session as array for direct access
     * @var array
     */
    protected $user_session;

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Phalcon Constructor Event
     */
    protected function onConstruct()
    {
        //always call parent constructor
        parent::onConstruct();

        //set session var
        $this->user_session = $this->getUserSession();
        //set user data for view, filter is passed to exclude some properties
        $this->_setUserDataForView(self::$DEFAULT_USER_PROPS_FILTER);
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

        $user_class = AppModule::getClass("user");

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
    protected function userHasLoggedIn($user_id)
    {
        //get user data from DB
        $user_class = AppModule::getClass("user");
        $user       = $user_class::getById($user_id);

        if (!$user)
            throw new Exception("User not found, cant set session auth");

        //call abstract method
        $user_data = $this->onUserLoggedIn($user);

        if (empty($user_data))
            $user_data = [];

        //set default proeprties
        $user_data["id"]   = $user_id;
        $user_data["auth"] = true;

        //save in session
        $this->session->set("user", $user_data);
    }

    /**
     * Handles response on logged in event, check for pending redirection. Default uri is 'account'.
     * @param string $uri - The URI to redirect after loggedIn
     * @param array $payload - Sends a payload response instead of redirection (optional)
     * @param boolean $auth_redirect - Flag to check session auth redirection (optional)
     */
    protected function dispatchOnUserLoggedIn($uri = "account", $payload = null, $auth_redirect = true)
    {
        //check if redirection is set in session
        if ($auth_redirect && $this->session->has("auth_redirect")) {
            //get redirection uri from session
            $uri = $this->session->get("auth_redirect");
            //remove from session
            $this->session->remove("auth_redirect");
        }

        //check for ajax request
        if ($this->request->isAjax()) {
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
    protected function setRedirectionOnUserLoggedIn($uri = null)
    {
        if (is_null($uri))
            $uri = $this->getRequestedUri();

        $this->session->set("auth_redirect", $uri);
    }

    /**
     * Removes pending session redirection
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
        $user_class   = AppModule::getClass("user");
        //get user
        $user = $user_class::getById($user_session["id"]);

        if (!$user)
            return false;

        foreach ($data as $key => $value) {

            if (isset($user_session[$key]))
                $user_session[$key] = $value;
        }

        //save in session
        $this->session->set("user", $user_session);
    }

    /**
     * Destroy user session data and redirect to home
     * @param string $uri - The URI to redirect
     */
    protected function destroyUserSessionAndRedirect($uri = "signIn")
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

    /**
     * Redirect to login/register
     */
    protected function redirectToLogin()
    {
        $this->redirectTo("signIn");
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Set user data object for view
     * @access private
     * @param array $filter - A string array of properties to filter
     */
    private function _setUserDataForView($filter = [])
    {
        //Load view data only for non-ajax requests, set user data var for view
        if (!$this->request->isAjax()) {
            $this->view->setVar("user_data", $this->getUserSession($filter));
        }
    }
}
