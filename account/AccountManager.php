<?php
/**
 * Account Manager Trait
 * This class has common actions for account auth controllers
 * Requires a Frontend or Backend Module
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Account;

//imports
use Phalcon\Exception;

/**
 * Account Manager for already loggedIn users
 */
trait AccountManager
{
    /**
     * Before update user profile Listener
     * @param object $user - The user object
     * @param array $data - The data to be updated
     */
    abstract public function beforeUpdateProfile($user, $data); //returns an array

    /**
     * Config var
     * @var array
     */
    public $account_manager_conf;

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * This method must be call in constructor parent class
     * @param array $conf - The config array
     */
    public function initAccountManager($conf = array())
    {
        $this->account_manager_conf = $conf;
    }

    /**
     * Phalcon Initializer Event
     */
    protected function initialize()
    {
        parent::initialize();

        //if not logged In, set this URI to redirected after logIn
        if(!$this->_checkUserIsLoggedIn())
            $this->_setSessionRedirectionOnLoggedIn();

        //check if user is logged in, if not dispatch to auth/logout
        $this->_checkUserIsLoggedIn(true);

        //for auth required pages disable robots
        $this->view->setVar("html_disallow_robots", true);
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Ajax (POST) - update account settings
     */
    public function updateProfileAction()
    {
        //get post params
        $default_params = [
            'first_name'    => 'string',
            'last_name'     => 'string',
            '@current_pass' => 'string',
            '@pass'         => 'string'
        ];

        //get model class name
        $user_class = $this->_getModuleClass('user');
        //get user
        $user = $user_class::getById($this->user_session['id']);
        //validate user
        if(!$user)
            $this->_sendJsonResponse(404);

        //get settings params
        $setting_params = isset($this->account_manager_conf['profile_request_params']) ? $this->account_manager_conf['profile_request_params'] : array();
        //validate and filter request params data, second params are the required fields
        $data = $this->_handleRequestParams(array_merge($default_params, $setting_params));

        try {

            //check if profile changed and save new data
            $updating_data = [];

            //check for password
            if(!empty($data['pass']) && empty($data['current_pass']))
                throw new Exception($this->account_manager_conf['trans']['current_pass_empty']);

            //changed pass validation
            if(!empty($data['pass']) && !empty($data['current_pass'])) {

                if(strlen($data['pass']) < $this->account_manager_conf['profile_pass_min_length'])
                    throw new Exception($this->account_manager_conf['trans']['pass_too_short']);

                //check current pass
                if(!$this->security->checkHash($data['current_pass'], $user->pass))
                    throw new Exception($this->account_manager_conf['trans']['pass_dont_match']);

                //check pass is diffetent to current
                if($this->security->checkHash($data['pass'], $user->pass))
                    throw new Exception($this->account_manager_conf['trans']['new_pass_equals']);

                //ok, update pass
                $updating_data["pass"] = $this->security->hash($data['pass']);
            }

            //check first & last name
            if(strlen($data['first_name']) >= 2 && $data['first_name'] != $user->first_name) {

                //validate name
                if(strcspn($data['first_name'], '0123456789') != strlen($data['first_name']))
                    throw new Exception($this->account_manager_conf['trans']['invalid_names']);

                //format to capitalized name
                $updating_data["first_name"] = mb_convert_case($data["first_name"], MB_CASE_TITLE, 'UTF-8');
            }

            if(strlen($data['last_name']) >= 2 && $data['last_name'] != $user->last_name) {

                //validate name
                if(strcspn($data['last_name'], '0123456789') != strlen($data['last_name']))
                    throw new Exception($this->account_manager_conf['trans']['invalid_names']);

                //format to capitalized name
                $updating_data["last_name"] = mb_convert_case($data["last_name"], MB_CASE_TITLE, 'UTF-8');
            }

            //call abstract method to do further updates
            $new_updates = $this->beforeUpdateProfile($user, $data);
            //merge updates
            if(is_array($new_updates))
                $updating_data = array_merge($updating_data, $new_updates);

            //update data?
            if(!empty($updating_data)) {

               $user->update($updating_data);
               //update session data
               $this->_updateUserSessionData($updating_data);
               //update full name
               $updating_data['name'] = $user->first_name." ".$user->last_name;

               //check if pass set
               if(isset($updating_data["pass"]))
                    $updating_data["pass"] = true;
            }

            //send response
            $this->_sendJsonResponse(200, [
                "user" => $updating_data,
                "msg"  => $this->account_manager_conf['trans']['profile_saved']
            ]);
        }
        catch (Exception $e) {
            $this->_sendJsonResponse(200, $e->getMessage(), "warning");
        }
    }
}
