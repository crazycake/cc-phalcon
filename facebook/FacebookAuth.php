<?php
/**
 * Facebook Trait - Facebook php sdk v5.0
 * This class has common actions for account facebook controllers
 * Requires a Frontend or Backend Module with CoreController and Session Trait
 * Open Graph v2.4
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Facebook;

//imports
use Phalcon\Exception;
//Facebook PHP SDK
use Facebook\FacebookSession;
use Facebook\FacebookRequest;
use Facebook\Helpers\FacebookJavaScriptHelper;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Exceptions\FacebookResponseException;
//CrazyCake Utils
use CrazyCake\Utils\DateHelper;

/**
 * Facebook Authentication
 */
trait FacebookAuth
{
    /**
     * Set Trait configurations
     */
    abstract public function setConfigurations();

    /**
     * Listener - On settings Login Redirection
     */
    abstract public function onSuccessAuthRedirection($handler_uri = '', $response = null);
    abstract public function onAppDeauthorized($fb_user = null);

    /**
     * Config var
     * @var array
     */
    public $fbConfig;

    /**
     * Lib var
     * @var object
     */
    public $fb;

    /**
     * Facebook URI user image
     * @static
     * @var string
     */
    public static $FB_USER_IMAGE_URI = "graph.facebook.com/<fb_id>/picture?type=<size>";

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * This method must be call in constructor parent class
     */
    public function initFacebookSDK()
    {
        //set Facebook Object
        $this->fb = new \Facebook\Facebook([
            'app_id'     => $this->config->app->facebook->appID,
            'app_secret' => $this->config->app->facebook->appKey,
            //api version
            'default_graph_version' => 'v2.5'
        ]);
    }

    /**
     * Ajax - Login user by JS SDK.
     */
    public function loginAction()
    {
        //validate and filter request params data, second params are the required fields
        $data = $this->_handleRequestParams([
            'signed_request' => 'string',
            '@user_data'     => 'int',
            '@check_perms'   => 'string'
        ]);

        try {

            //check signed request
            if(!$this->__parseSignedRequest($data['signed_request']))
                return $this->_sendJsonResponse(405);

            //call js helper
            $helper = $this->fb->getJavaScriptHelper();
            $fac    = $helper->getAccessToken();
            //handle login
            $response = $this->__loginUserFacebook($fac);

            //check perms
            if(isset($data["check_perms"]) && $data["check_perms"])
                $response["perms"] = $this->__getAccesTokenPermissions($fac, 0, $data["check_perms"]);

            //handle response, session controller
            if(isset($data["user_data"]) && $data["user_data"])
                return $this->_handleResponseOnLoggedIn(null, $response, false);
            else
                return $this->_handleResponseOnLoggedIn();
        }
        catch (FacebookResponseException $e) { $exception = $e; }
        catch (FacebookSDKException $e)      { $exception = $e; }
        catch (Exception $e)                 { $exception = $e; }
        catch (\Exception $e)                { $exception = $e; }

        //an exception ocurred
        $msg = isset($exception) ? $exception->getMessage() : $this->fbConfig['trans']['oauth_redirected'];

        if($exception instanceof FacebookResponseException)
            $msg = $this->fbConfig['trans']['oauth_perms'];

        return $this->_sendJsonResponse(200, $msg, true);
    }

    /**
     * Handler View - Login by redirect action via facebook login URL
     * @param string $key - Act as key
     * @return json response
     */
    public function loginByRedirectAction($encrypted_data = "")
    {
        try {
            //get helper object
            $helper = $this->fb->getRedirectLoginHelper();
            $fac    = $helper->getAccessToken();
            //handle login
            $response = $this->__loginUserFacebook($fac);

            //get decrypted params
            $params = $this->cryptify->decryptForGetResponse($encrypted_data, true);

            //check perms
            if(isset($params->check_perms) && $params->check_perms)
                $response["perms"] = $this->__getAccesTokenPermissions($fac, 0, $params->check_perms);

            //handle response manually
            if(!empty($params->handler_uri))
                return $this->onSuccessAuthRedirection($params->handler_uri, $response);

            //handle response, session controller
            return $this->_handleResponseOnLoggedIn();
        }
        catch (FacebookResponseException $e) { $exception = $e; }
        catch (FacebookSDKException $e)      { $exception = $e; }
        catch (Exception $e)                 { $exception = $e; }
        catch (\Exception $e)                { $exception = $e; }

        $this->logger->error("Facebook::loginByRedirectAction -> An error ocurred: ".$exception->getMessage());

        if($this->request->isAjax())
            return $this->_sendJsonResponse(200, $exception->getMessage(), true);

        //set message
        $this->view->setVar("error_message", $this->fbConfig['trans']['oauth_redirected']);
        $this->dispatcher->forward(["controller" => "errors", "action" => "internal"]);
    }

    /**
     * Async (GET) - Extended Facebook Access Token (LongLive Token)
     * FAC => Facebook Access Token
     * @param string $encrypted_data {user_id#fac}
     * @return json response
     */
    public function extendAccessTokenAction($encrypted_data = "")
    {
        if (empty($encrypted_data))
            return $this->_sendJsonResponse(405); //method not allowed

        try {
            //get encrypted facebook user id and short live access token
            $data = $this->cryptify->decryptForGetResponse($encrypted_data, "#");
            //set vars
            list($fb_id, $short_live_fac) = $data;

            //find user on db
            $users_facebook_class = $this->_getModuleClass('users_facebook');
            $user_fb = $users_facebook_class::getObjectById($fb_id);

            if(!$user_fb || empty($short_live_fac))
                $this->_sendJsonResponse(400); //bad request

            //if a session error ocurred, get a new long live access token
            $this->logger->log("Facebook::extendAccessTokenAction -> Requesting a new long live access token for fb_id: $user_fb->id");

            //get new long live fac
            $client = $this->fb->getOAuth2Client();
            $fac    = $client->getLongLivedAccessToken($short_live_fac);

            //save new long-live access token?
            if ($fac) {
                //set new access token
                $data = [];
                $data['fac'] = $fac->getValue();

                if(is_object($fac->getExpiresAt()))
                    $data['expires_at'] = $fac->getExpiresAt()->format('Y-m-d H:i:s');

                //update in db
                $user_fb->update($data);
            }

            //send JSON response with payload
            return $this->_sendJsonResponse(200, ["fb_id" => $user_fb->id]);
        }
        catch (FacebookResponseException $e) { $exception = $e; }
        catch (FacebookSDKException $e)      { $exception = $e; }
        catch (Exception $e)                 { $exception = $e; }
        catch (\Exception $e)                { $exception = $e; }

        //exception
        $this->logger->error("Facebook::extendAccessToken -> Exception: ".$exception->getMessage().". fb_id: ".(isset($user_fb->id) ? $user_fb->id : "unknown"));
        $this->_sendJsonResponse(400);
    }

    /**
     * Handler - Deauthorize a facebook user
     * @return mixed (boolean|array)
     */
    public function deauthorizeAction($signed_request = '')
    {
        //must be implemented
        $this->onAppDeauthorized($signed_request);
    }

    /**
     * Ajax (GET) - Check if user facebook link is valid with scope perms
     * Requires be logged In
     */
    public function checkLinkAppStateAction()
    {
        //make sure is ajax request
        $this->_onlyAjax();

        //handle response, dispatch to auth/logout
        $this->_checkUserIsLoggedIn(true);

        try {
            //get fb user data
            $fb_data = $this->getUserData(null, $this->user_session['id']);

            if(!$fb_data)
                throw new Exception("Invalid Facebook Access Token");

            //validate permissions
            $perms = $this->__getAccesTokenPermissions(null,
                                                    $this->user_session['id'],
                                                    $this->config->app->facebook->appScope);

            if(!$perms)
                throw new Exception("App permissions not granted");

            $users_facebook_class = $this->_getModuleClass('users_facebook');

            $user_fb = $users_facebook_class::getObjectById($fb_data['fb_id']);

            //check publish perms
            if(empty($user_fb) || empty($user_fb->publish_perm))
                throw new Exception("UsersFacebook publish permission disabled");

            //set payload
            $payload = ["status" => true, "fb_id" => $fb_data['fb_id']];
        }
        catch (Exception $e) {
            $payload = ["status" => false, "exception" => $e->getMessage()];
        }

        //send JSON response
        $this->_sendJsonResponse(200, $payload);
    }

    /**
     * Ajax (POST) - set faceboook publish permission
     * Requires be logged In
     */
    public function setUserPublishPermAction()
    {
        //make sure is ajax request
        $this->_onlyAjax();

        //handle response, dispatch to auth/logout
        $this->_checkUserIsLoggedIn(true);

        //get post params
        $data = $this->_handleRequestParams([
            'perm' => 'int'
        ]);

        //validate facebook user
        if(!$this->user_session['fb_id'])
            $this->_sendJsonResponse(404);

        //update prop
        $this->_setUserPublishPerm($this->user_session['id'], $data['perm']);

        //set payload
        $payload = ["status" => (boolean)$data['perm']];
        //send JSON response
        $this->_sendJsonResponse(200, $payload);
    }

    /**
     * Handler - Get user facebook properties
     * @param object $fac The facebook access token
     * @param int $user_id The user id
     * @return array
     */
    public function getUserData($fac = null, $user_id = 0)
    {
        //get session using short access token or with saved access token
        $this->__setUserAccessToken($fac, $user_id);

        // Get the graph-user object for the current user (validation)
        try {
            $response = $this->fb->get('/me?fields=email,name,first_name,last_name,birthday,gender');

            if(!$response)
                throw new Exception("Invalid facebook data from /me?fields= request.");

            //parse user fb session properties
            $fb_data    = $response->getGraphNode();
            $properties = array();

            //parse from array (Javascript SDK)
            $properties['fb_id']      = $fb_data->getField('id');
            $properties['email']      = strtolower($fb_data->getField('email'));
            $properties['first_name'] = $fb_data->getField('first_name');
            $properties['last_name']  = $fb_data->getField('last_name');
            //birthday
            $birthday = $fb_data->getField('birthday');
            $properties['birthday'] = is_object($birthday) ? $birthday->format("Y-m-d") : null;
            //get gender
            $gender = $fb_data->getField('gender');
            $properties['gender'] = $gender ? $gender : 'undefined';

            if(empty($properties))
                throw new Exception("Invalid facebook parsed properties.");

            return $properties;
        }
        catch (FacebookResponseException $e) { $exception = $e; }
        catch (FacebookSDKException $e)      { $exception = $e; }
        catch (Exception $e)                 { $exception = $e; }
        catch (\Exception $e)                { $exception = $e; }

        //log exception
        $this->logger->error("Facebook::getUserData -> Exception: ".$exception->getMessage().", userID: $user_id ");
        return null;
    }

    /**
     * GetFacebookLogin URL
     * Requested uri is a simple hash param
     * @param mixed [boolean,string] $handler_uri - Custom handler uri
     * @param boolean [boolean] $check_perms - Validates access token app scope permissions
     * @return string
     */
    public function loadFacebookLoginURL($handler_uri = false, $check_perms = false)
    {
        if(!$handler_uri)
            $handler_uri = "0";
        else
            $handler_uri = is_string($handler_uri) ? $handler_uri : $this->_getRequestedUri();

        //append request uri as hashed string
        $scope  = $this->config->app->facebook->appScope;
        $params = [
            "handler_uri" => $handler_uri,
            "check_perms" => $check_perms ? $scope : false
        ];

        //encrypt data
        $params = $this->cryptify->encryptForGetRequest($params);

        $callback = $this->_baseUrl("facebook/loginByRedirect/".$params);

        //set helper
        $helper = $this->fb->getRedirectLoginHelper();
        //get the url
        $url = $helper->getLoginUrl($callback, explode(",", $scope));

        //set property to config
        $this->config->app->facebook->loginUrl = $url;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Handler - Set user facebook publish perm
     * @param int $user_id
     * @param int $perm
     * @return boolean
     */
    protected function _setUserPublishPerm($user_id, $perm = 1)
    {
        $users_facebook_class = $this->_getModuleClass('users_facebook');

        //get user & update properties
        $user_fb = $users_facebook_class::getFacebookDataByUserId($user_id);

        if(isset($user_fb->publish_perm))
            $user_fb->update(['publish_perm' => (int)$perm]);

        return $perm;
    }

    /**
     * Logins a User with facebook access token,
     * If data has the signed_request property it will be checked automatically
     * @param object $fac The access token object
     */
    private function __loginUserFacebook($fac = null)
    {
        //get model classmap names
        $users_class          = $this->_getModuleClass('users');
        $users_facebook_class = $this->_getModuleClass('users_facebook');

        //the data response
        $login_data = array();
        $exception  = false;

        try {
            //get properties
            $properties = $this->getUserData($fac);

            //validate fb session properties
            if(!$properties)
                throw new Exception($this->fbConfig['trans']['session_error']);
            //print_r($properties);exit;

            //email validation
            if (empty($properties['email']) || !filter_var($properties['email'], FILTER_VALIDATE_EMAIL)) {
                $this->logger->error("Facebook::__loginUserFacebook() -> Facebook Session (".$properties["fb_id"].") invalid email: ".$properties['email']);
                throw new Exception(str_replace("{email}", $properties['email'], $this->fbConfig['trans']['invalid_email']));
            }

            //OK, check if user exists in Users Facebook table & get session data
            $user_fb      = $users_facebook_class::getObjectById($properties["fb_id"]);
            $user_session = $this->_getUserSessionData(); //get app session

            //check if user is logged, have a FB user, and he is attempting to login facebook with another account
            if ($user_session && $user_session["fb_id"] && $user_session["fb_id"] != $properties["fb_id"]) {
                $this->logger->error("Facebook::__loginUserFacebook() -> App Session fb_id (".$user_session["fb_id"].") & sdk session (".$properties["fb_id"].") data doesn't match.");
                throw new Exception($this->fbConfig['trans']['session_switched']);
            }

            //check user is logged in, don't a have a FB user and the logged in user has another user id.
            if ($user_session && $user_fb && $user_fb->user_id != $user_session["id"]) {
                $this->logger->error("Facebook::__loginUserFacebook() -> App Session fb_id (".$user_session["fb_id"].") & sdk session (".$properties["fb_id"].") data doesn't match.");
                throw new Exception($this->fbConfig['trans']['account_switched']);
            }

            //check if user has already a account registered by email
            $user = $users_class::getUserByEmail($properties['email']);
            //skip user insertion?
            if ($user) {

                //disabled account
                if ($user->account_flag == 'disabled')
                    throw new Exception($this->fbConfig['trans']['account_disabled']);

                //update user flag if account was pending, or if account is disabled show a warning
                if ($user->account_flag == 'pending')
                    $properties['account_flag'] = 'enabled';

                //unset fields we won't wish to update
                unset($properties['first_name'], $properties['last_name']);
                //update user ignoring arbitrary set keys
                $user->update($properties);
            }
            else {
                $user = new $users_class();
                //extend properties
                $properties['account_flag'] = 'enabled'; //set account flag as active
                //insert user
                if (!$user->save($properties))
                    $this->_sendJsonResponse(200, $user->filterMessages(), true);
            }

            //INSERT a new facebook user
            if (!$user_fb)
                $this->__saveNewUserFacebook($user->id, $properties["fb_id"], $fac);

            //queues an async request, extend access token (append fb userID and short live access token)
            $this->_asyncRequest(
                ["facebook" => "extendAccessToken"],
                $properties["fb_id"]."#".$fac->getValue(),
                "GET",
                true
            );
        }
        catch (FacebookResponseException $e) { $exception = $e; }
        catch (FacebookSDKException $e)      { $exception = $e; }
        catch (Exception $e)                 { $exception = $e; }
        catch (\Exception $e)                { $exception = $e; }
        //throw one exception type
        if ($exception){
            $this->logger->error("Facebook::__loginUserFacebook -> Exception: ".$exception->getMessage().". fb_id: ".(isset($user_fb) ? $user_fb->id : "unknown"));
            throw new Exception($e->getMessage());
        }

        //SAVES session if none error ocurred
        $login_data["properties"] = $properties;
        //set php session as logged in
        $this->_setUserSessionAsLoggedIn($user->id);

        return $login_data;
    }

    /**
     * Get Access Token permissions
     * @param object $fac The facebook access token
     * @param int $user_id The user id
     * @param mixed[boolean, string, array] $scope If set validates also the granted perms
     * @return array
     */
    private function __getAccesTokenPermissions($fac = null, $user_id = 0, $scope = false)
    {
        //get session using short access token or with saved access token
        $this->__setUserAccessToken($fac, $user_id);

        try {
            $this->logger->debug("Facebook::__getAccesTokenPermissions -> checking fac perms for userID: $user_id,  scope: ".json_encode($scope));

            $response = $this->fb->get('/me/permissions');

            if(!$response)
                throw new Exception("Invalid facebook data from /me/permissions request.");

            //parse user fb session properties
            $fb_data = $response->getDecodedBody();

            if(!isset($fb_data["data"]) || empty($fb_data["data"]))
                throw new Exception("Invalid facebook data from /me/permissions request.");

            //get perms array
            $perms = $fb_data["data"];

            //validate scope permissions
            if($scope) {

                $scope = is_array($scope) ? $scope : explode(',', $scope);

                foreach ($perms as $p) {

                    //validates permission
                    if(in_array($p["permission"], $scope) && (!isset($p["status"]) || $p["status"] != "granted"))
                        throw new Exception("Facebook perm ".$p["permission"]." is not granted");
                }
            }

            return $perms;
        }
        catch (FacebookResponseException $e) { $exception = $e; }
        catch (FacebookSDKException $e)      { $exception = $e; }
        catch (Exception $e)                 { $exception = $e; }
        catch (\Exception $e)                { $exception = $e; }

        //log exception
        $this->logger->debug("Facebook::__getAccesTokenPermissions -> Exception: ".$exception->getMessage().", userID: $user_id ");
        return null;
    }

    /**
     * Set the facebook Access Token
     * @param string $fac (optional) Access token
     * @param int $user_id
     * @throws Exception
     * @return mixed (boolean|string|object)
     */
    private function __setUserAccessToken($fac = null, $user_id = 0)
    {
        $users_facebook_class = $this->_getModuleClass('users_facebook');

        //get stored fac if its null
        if(is_null($fac)) {
            //get user
            $user_fb = $users_facebook_class::getFacebookDataByUserId($user_id);
            //validates data
            if(!$user_fb)
                throw new Exception("invalid user id");

            //get access token
            $fac = $user_fb->fac;
        }
        //check for a fac object
        else if(is_object($fac)) {
            $fac = $fac->getValue();
        }

        //open a facebook PHP SDK Session with saved access token
        $this->fb->setDefaultAccessToken($fac);
    }

    /**
     * Parse given user facebook session data to save in Database
     * @param int $user_id
     * @param int $fb_id
     * @param object $fac The access token object
     * @return array
     */
    private function __saveNewUserFacebook($user_id = null, $fb_id = null, $fac = null)
    {
        $users_class          = $this->_getModuleClass('users');
        $users_facebook_class = $this->_getModuleClass('users_facebook');

        //Creates a Facebook User
        $user_fb             = new $users_facebook_class();
        $user_fb->user_id    = $user_id;
        $user_fb->id         = $fb_id;
        $user_fb->fac        = $fac->getValue();
        $user_fb->expires_at = is_object($fac->getExpiresAt()) ? $fac->getExpiresAt()->format("Y-m-d H:i:s") : null;

        if (!$user_fb->save()) {

            $this->logger->error("Facebook::__saveNewUserFacebook() -> Error Insertion User Facebook data. userId -> ".$user_id.",
                                  FBUserId -> ".$fb_id.", trace: ".$user_fb->filterMessages(true));

            $user = $users_class::getObjectById($user_id);
            $user->delete();
            //raise an error
            throw new Exception($this->fbConfig['trans']['session_error']);
        }

        return $user_fb;
    }

    /**
     * Parse facebook signed request got from Javascript SDK
     * This should be used
     * @link https://developers.facebook.com/docs/games/canvas/login
     * @param string $signed_request
     * @return mixed
     */
    private function __parseSignedRequest($signed_request = null)
    {
        if(is_null($signed_request))
            return false;

        //set facebook app secret
        $fb_app_key = $this->config->app->facebook->appKey;

        //set properties with list
        list($encoded_sig, $payload) = explode('.', $signed_request, 2);

        //anonymous function
        $base64_decode_url = function($str) {
            return base64_decode(strtr($str, '-_', '+/'));
        };

        //decode the data
        $data = json_decode($base64_decode_url($payload), true);
        //check algorithm
        if (strtoupper($data['algorithm']) !== 'HMAC-SHA256') {
            $this->logger->error('Facebook::__parseSignedRequest -> Unknown algorithm. Expected HMAC-SHA256');
            return false;
        }
        //adding the verification of the signed_request below
        $expected_sig = hash_hmac('sha256', $payload, $fb_app_key, $raw = true);
        $signature    = $base64_decode_url($encoded_sig);

        if ($signature !== $expected_sig) {
            $this->logger->error('Facebook::__parseSignedRequest -> Invalid JSON Signature!');
            return false;
        }

        return $data;
    }
}
