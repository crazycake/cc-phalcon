<?php
/**
 * Session Trait
 * Requires a Frontend or Backend Module
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Account;

//core
use CrazyCake\Phalcon\AppLoader;
use CrazyCake\Utils\DateHelper;

/**
 * Account Session Handler
 */
trait AccountSession
{
    /**
     * Set user session data
     * @param object $session - The session object reference
     */
    abstract protected function getUserSessionData($session);

    /**
     * Listener - Append properties to user session
     * @param object $user - The user object reference
     */
    abstract protected function setUserSessionAsLoggedIn($user);


    /** const **/
    protected static $DEFAULT_USER_PROPS_FILTER = ['id', 'account_flag', 'auth'];

    /**
     * Stores user session as array for direct access
     * @var array
     */
    protected $user_session;

    /**
     * Init constructor
     */
    protected function _init()
    {
        //exclude api controller includes
        if(MODULE_NAME == "api")
            return;

        //check enable SSL option
        $enableSSL = AppLoader::getModuleConfigProp("enableSSL");

        //if enabledSSL, force redirect for non-https request
        if( APP_ENVIRONMENT === 'production'
            && isset($_SERVER["HTTP_HOST"])
            && $enableSSL
            && !$this->request->isSecureRequest()) {

            $url = "https://".$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
            $this->response->redirect($url);
            return false;
        }

        //set session var
        $this->user_session = $this->_getUserSessionData();
        //set user data for view, filter is passed to exclude some properties
        $this->_setUserDataForView(self::$DEFAULT_USER_PROPS_FILTER);
    }

    /**
     * Set user data object for view
     * @param array $filter - A string array of properties to filter
     */
    protected function _setUserDataForView($filter = array())
    {
        //Load view data only for non-ajax requests, set user data var for view
        if (!$this->request->isAjax()) {
            $this->view->setVar("user_data", $this->_getUserSessionData($filter));
        }
    }

    /**
     * Check that user is logged in
     * @param boolean $dispatch_logout - Handles the response for loggedOut status
     * @return boolean
     */
    protected function _checkUserIsLoggedIn($dispatch_logout = false)
    {
        //anonymous function
        $loggedIn = function() {

            if (!$this->session->has("user"))
                return false;

            //get user session
            $user_session = $this->session->get("user");

            if (!is_array($user_session) || !isset($user_session['id']) || !isset($user_session['auth']))
                return false;

            $users_class = $this->_getModuleClass('users');
            if ($users_class::getObjectById($user_session['id']) == false)
                return false;

            return $user_session['auth'] ? true : false;
        };

        if(!$dispatch_logout) {
            return $loggedIn();
        }

        //check if user is logged in, if not dispatch to auth/logout
        if ($loggedIn())
            return true;

        if ($this->request->isAjax()) {
            $this->_sendJsonResponse(403);
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
    protected function _setUserSessionAsLoggedIn($user_id)
    {
        //get user data from DB
        $users_class = $this->_getModuleClass('users');
        $user = $users_class::getObjectById($user_id);

        if (!$user)
            return;

        //call abstract method
        $user_data = $this->setUserSessionAsLoggedIn($user);

        if(empty($user_data))
            $user_data = array();

        //set default proeprties
        $user_data['id']   = $user_id;
        $user_data['auth'] = true;

        //save in session
        $this->session->set("user", $user_data);
    }

    /**
     * Handles response on logged in event, check for pending redirection
     * Defaults uri => account
     * @param string $uri - The URI to redirect after loggedIn
     * @param array $payload - Sends a payload response instead of redirection (optional)
     * @param boolean $auth_redirect - Flag to check session auth redirection (optional)
     */
    protected function _handleResponseOnLoggedIn($uri = "account", $payload = null, $auth_redirect = true)
    {
        //check if redirection is set in session
        if($auth_redirect && $this->session->has("auth_redirect")) {
            //get redirection uri from session
            $uri = $this->session->get("auth_redirect");
            //remove from session
            $this->session->remove("auth_redirect");
        }

        //check for ajax request
        if($this->request->isAjax()) {
            $this->_sendJsonResponse(200, empty($payload) ? ["redirect" => $uri] : $payload);
        }
        else {
            $this->_redirectTo($uri);
        }
    }

    /**
     * Set redirection URL for after loggedIn event
     * @param string $uri - The URL to be redirected
     */
    protected function _setSessionRedirectionOnLoggedIn($uri = null)
    {
        if(is_null($uri))
            $uri = $this->_getRequestedUri();

        $this->session->set("auth_redirect", $uri);
    }

    /**
     * Clean session redirection
     */
    protected function _cleanSessionRedirection()
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
    protected function _getUserSessionData($filter = array())
    {
        if (!$this->_checkUserIsLoggedIn())
            return false;

        //get user session
        $user_session = $this->session->get("user");

        //call abstract method
        $new_session = $this->getUserSessionData($user_session);

        //save again session?
        if($new_session != $user_session) {
            $user_session = $new_session;
            $this->session->set("user", $user_session);
        }

        //filter unwanted props
        if(!empty($filter)) {
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
    protected function _updateUserSessionData($data = array())
    {
        //get user session
        $user_session = $this->session->get("user");
        $users_class  = $this->_getModuleClass('users');
        //get user
        $user = $users_class::getObjectById($user_session['id']);

        if (!$user)
            return false;

        foreach ($data as $key => $value) {

            if(isset($user_session[$key]))
                $user_session[$key] = $value;
        }
        //save in session
        $this->session->set("user", $user_session);
    }

    /**
     * Destroy user session data and redirect to home
     * @param string $uri - The URI to redirect
     */
    protected function _destroyUserSessionAndRedirect($uri = "signIn")
    {
        //unset all user session data
        $this->session->remove("user");

        //redirect to given url, login as default
        $this->_redirectTo($uri);
    }

    /**
     * Add a new custom object to user session
     * @param string $key - The key name of the session (required)
     * @param string $obj - The object (required)
     * @param string $index - The index of the array (optional)
     * @return boolean
     */
    protected function _addSessionObject($key = "session_objects", $obj = null, $index = null)
    {
        if(is_null($obj))
            return false;

        //get array stored in session
        $objects = array();
        if ($this->session->has($key))
            $objects = $this->session->get($key);

        //push object to array
        if(is_null($index))
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
    protected function _getSessionObjects($key = "session_objects")
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
    protected function _removeSessionObject($key = "session_objects", $index = null)
    {
        if (!$this->session->has($key) || is_null($index))
            return false;

        $objects = $this->_getSessionObjects($key);
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
    protected function _destroySessionObjects($key = "session_objects")
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
    protected function _redirectToAccount($check_logged_in = false)
    {
        if ($check_logged_in && !$this->_checkUserIsLoggedIn())
            return;

        $this->_redirectTo("account");
    }

    /**
     * Redirect to login/register
     */
    protected function _redirectToLogin()
    {
        $this->_redirectTo("signIn");
    }
}
