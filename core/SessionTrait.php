<?php
/**
 * Simple Email Service Trait, requires Emogrifier class (composer)
 * Requires a Frontend, Backend or CLI DI services
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

//CrazyCake Utils
use CrazyCake\Utils\DateHelper;

trait SessionTrait
{
	/**
     * child required methods
     */
    abstract public function getUserSessionData($session);
    abstract public function setUserSessionAsLoggedIn($user);

    /* --------------------------------------------------- § -------------------------------------------------------- */
	
    /**
     * Set user data object for view
     * @param array $filter A string array of properties to filter
     */
    protected function _setUserDataForView($filter = array())
    {
        //Load view data only for non-ajax requests
        if (!$this->request->isAjax()) {
            //set user data var for view
            $this->view->setVar("user_data", $this->_getUserSessionData($filter));
        }
    }

    /**
     * Check that user is logged in
     * @return boolean
     */
    protected function _checkUserIsLoggedIn()
    {
        if (!$this->session->has("user"))
            return false;

        //get user session
        $user_session = $this->session->get("user");

        if (!is_array($user_session) || !isset($user_session['id']) || !isset($user_session['auth']))
            return false;

        //make sure user exists (can be ommited)
        if (\Users::getObjectById($user_session['id']) == false)
            return false;

        return $user_session['auth'] ? true : false;
    }

    /**
     * Set user Session as logged in
     * @param int $user_id The user id
     */
    protected function _setUserSessionAsLoggedIn($user_id)
    {
        //get user data from DB
        $user = \Users::getObjectById($user_id);

        if (!$user)
            return;

        //set user data, call child method
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
     * Get logged in user session data
     * @param array $filter Filters sensitive data
     * @return array The session array
     */
    protected function _getUserSessionData($filter = array())
    {
        if (!$this->_checkUserIsLoggedIn())
            return false;

        //get user session
        $user_session = $this->session->get("user");

        //call child method to extend properties,  
        $new_session = $this->getUserSessionData($user_session);

        //save again session?
        if($new_session) {
            $user_session = $new_session;
            $this->session->set("user", $user_session);
        }

        //filter unwanted props
        if(!empty($filter)) {
            foreach ($filter as $key => $value)
                unset($user_session[$key]);
        }

        //return session data
        return $user_session;
    }

    /**
     * Update user session data
     * @param array $data
     * @return boolean
     */
    protected function _updateUserSessionData($data)
    {
        //get user session
        $user_session = $this->session->get("user");
        //assumed that users use BaseModel
        $user = \Users::getObjectById($user_session['id']);

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
     * @param string $uri The URI to redirect
     */
    protected function _destroyUserSessionAndRedirect($uri = "signIn")
    {
        //unset user data
        if ($this->session->has("user"))
            $this->session->remove("user");

        //redirect to login
        $this->_redirectToLogin();
    }

    /**
     * Add a new custom object to user session
     * @param string $key The key name of the object
     * @param string $obj The object
     * @param string $id_property The ID keyholder of the object
     * @return boolean
     */
    protected function _addSessionObject($key = 'custom', $obj = null, $id_property = 'id')
    {
        if(is_null($obj))
            return false;

        //set user-event data
        $objects = array();
        if ($this->session->has($key))
            $objects = $this->session->get($key);

        //set as key value
        $objects[$id_property] = $obj;
        //save in session
        $this->session->set($key, $objects);
    }

    /**
     * Get custom objects stored in session
     * @param string $key The key name of the object
     */
    protected function _getSessionObjects($key)
    {
        if (!$this->session->has($key))
            return false;

        return $this->session->get($key);
    }

    /**
     * Removes custom session object
     * @param string $key The key name of the object
     * @param string $id_property The ID keyholder of the object
     */
    protected function _removeSessionObject($key = 'custom', $id_property = 'id')
    {
        if (!$this->session->has($key))
            return;

        $objects = $this->_getSessionObjects($key);
        //unset
        unset($objects[$id_property]);
        //save again in session
        $this->session->set($key, $objects);
    }

    /**
     * Destroy session custom objects stored in session
     */
    protected function _destroySessionObjects($key)
    {
        //unset data
        if ($this->session->has($key))
            $this->session->remove($key);
    }

    /**
     * Redirect to profile controller if user is logged in
     */
    protected function _redirectToProfileControllerIfLoggedIn()
    {
        if ($this->_checkUserIsLoggedIn())
            $this->_redirectToProfileController();
    }

    /**
     * Redirect to profile controller, cames from a loggedIn
     */
    protected function _redirectToProfileController()
    {
        $this->_redirectTo("profile");
    }

    /**
     * Redirect to login/register
     */
    protected function _redirectToLogin()
    {
        $this->_redirectTo("signIn");
    }

    /**
     * Redirect to given uri
     * @param string $uri The URI to redirect
     */
    protected function _redirectTo($uri = "")
    {
        $this->response->redirect($this->_baseUrl($uri), true);
        $this->response->send();
        die();
    }

    /**
     * Validates user & temp-token data. Input data is encrypted with cryptify lib. Returns decrypted data.
     * DI dependency injector must have cryptify service
     * UserTokens must be set in models
     * @param string $encrypted_data
     * @param int $expiration_threshold The expiration Threshold number (days)
     * @throws \Exception
     * @return array
     */
    protected function _handleUserTokenValidation($encrypted_data = null, $expiration_threshold = 2)
    {
        if (is_null($encrypted_data))
            throw new \Exception("sent input null encrypted_data");

        $data = explode("#", $this->cryptify->decryptForGetResponse($encrypted_data));

        //validate data (user_id, token_type and token)
        if (count($data) != 3)
            throw new \Exception("decrypted data is not a 2 dimension array.");

        //set vars values
        list($user_id, $token_type, $token) = $data;

        //search for user and token combination
        $token = \UsersTokens::getTokenByUserAndValue($user_id, $token, $token_type);

        if (!$token)
            throw new \Exception("temporal token dont exists.");

        //get days passed
        $days_passed = DateHelper::getTimePassedFromDate($token->created_at);

        if ($days_passed > $expiration_threshold)
            throw new \Exception("temporal token (id: " . $token->id . ") has expired (" . $days_passed . " days passed)");

        return $data;
    }
}