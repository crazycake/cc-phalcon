<?php
/**
 * Facebook Trait
 * This class has common actions for account facebook controllers
 * Requires a Frontend or Backend Module with CoreController and SessionTrait
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Traits;

//Facebook PHP SDK
use Facebook\FacebookSession;
use Facebook\FacebookRequest;
use Facebook\GraphUser;
use Facebook\FacebookRequestException;
use Facebook\FacebookRedirectLoginHelper;
use Facebook\FacebookJavaScriptLoginHelper;
//Guzzle for async requests
use GuzzleHttp\Client as GuzzleClient;
//CrazyCake Utils
use CrazyCake\Utils\DateHelper;

/**
 * Account Password Trait
 */
trait Facebook
{
    /**
     * abstract required methods
     */
    abstract public function setConfigurations();
    abstract public function onLoginRedirectionForSettings();

    /**
     * Config var
     * @var array
     */
    public $facebookConfig;

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
        FacebookSession::setDefaultApplication($this->config->app->facebookAppID, $this->config->app->facebookAppKey);
    }

    /**
     * Ajax - Login user by JS SDK.
     */
    public function loginAction()
    {
        //validate and filter request params data, second params are the required fields
        $data = $this->_handleRequestParams(array(
            'signed_request' => 'string'
        ));

        //check signed request
        if(!$this->__parseSignedRequest($data['signed_request']))
            $this->_sendJsonResponse(405);

        $helper  = new FacebookJavaScriptLoginHelper();
        $session = $helper->getSession();
        //check login
        $response = $this->__loginUserFacebook($session);

        //error response?
        if($response['fb_error'])
            $this->_sendJsonResponse(200, $response["fb_error"], true);

        //handle response
        $this->_handleResponseOnLoggedIn();
    }

    /**
     * Handler View - Login by redirect action via facebook login URL
     * @return json response
     */
    public function loginByRedirectAction()
    {
        $login_uri = $this->facebookConfig['controller_name']."/loginByRedirect/";
        //get helper object
        $helper  = new FacebookRedirectLoginHelper($this->_baseUrl($login_uri));
        $session = $helper->getSessionFromRedirect();
        //check login
        $response = $this->__loginUserFacebook($session);

        //send and error if session is NULL
        if($response['fb_error']) {
            $this->logger->error("Facebook::loginByRedirectAction -> An error ocurred: ".$response['fb_error']);
            //set message
            $this->view->setVar("error_message", $this->facebookConfig['text_oauth_redirected']);
            $this->dispatcher->forward(array("controller" => "errors", "action" => "internal"));
            return;
        }

        //handle facebook settings authentications
        $client = $this->session->get("client");
        //authenticated in settings controller
        if($client->requested_uri == $this->facebookConfig['settings_uri'])
            $this->onLoginRedirectionForSettings();

        //handle response
        $this->_handleResponseOnLoggedIn();
    }

    /**
     * Async - Extended Facebook Access Token (LongLive Token) -> Fac means Facebook Access Token
     * @param string $encrypted_data {user_id#fac}
     * @return json response
     */
    public function extendAccessTokenAction($encrypted_data = null)
    {
        if (is_null($encrypted_data))
            return $this->_sendJsonResponse(405); //method now allowed

        //get encrypted facebook user id and short live access token
        $data = $this->cryptify->decryptForGetResponse($encrypted_data, "#");
        //set vars
        list($fb_id, $short_live_fac) = $data;

        //find user on db
        $users_facebook_class = $this->getModuleClassName('users_facebook');
        $user_fb = $users_facebook_class::getObjectById($fb_id);

        if(!$user_fb)
            $this->_sendJsonResponse(400);

        //get facebook session with saved access token
        $fb_session = $this->__getUserFacebookSession($user_fb->fac);
        //if a session error ocurred, get a new long live access token
        $fac_obj = $this->__requestLongLiveAccessToken($user_fb, $short_live_fac, is_string($fb_session) ? null : $fb_session);

        //save new long-live access token?
        if ($fac_obj && $fac_obj->save) {
            $user_fb->fac        = $fac_obj->token;
            $user_fb->expires_at = $fac_obj->expires_at->format('Y-m-d H:i:s');
            //update in db
            $user_fb->update();
        }

        //set payload
        $payload = array("fb_id" => $fb_id);
        //send JSON response
        $this->_sendJsonResponse(200, $payload);
    }

    /**
     * Handler - Deauthorize a facebook user
     * @param int $user_id
     * @return mixed (boolean|array)
     */
    public function deauthorizeAction($user_id = 0)
    {
        //...
    }

    /**
     * Handler - Get user facebook properties from a already created session
     * @param string $fac Facebook AccesToken
     * @param int $user_id
     * @return mixed (boolean|array)
     */
    public function getUserFacebookSessionProperties($fac = null, $user_id = 0)
    {
        //get session using short access token or with saved access token
        $fb_session = $this->__getUserFacebookSession($fac, $user_id);

        if(is_string($fb_session))
            return false;

        // Get the graph-user object for the current user (validation)
        $fb_data = (new FacebookRequest($fb_session, 'GET', '/me'))->execute()->getGraphObject(GraphUser::className());

        if(!$fb_data)
            return false;

        //parse user fb session properties
        $properties = $this->__parseUserPropertiesForDatabase($fb_data, $fb_session);

        return $properties;
    }

    /**
     * Handler - Set user facebook publish perm
     * @param int $user_id
     * @param int $perm
     * @return boolean
     */
    public function setUserFacebookPublishPerm($user_id, $perm = 1)
    {
        $users_facebook_class = $this->getModuleClassName('users_facebook');

        //get user & update properties
        $user_fb = $users_facebook_class::getFacebookDataByUserId($user_id);
        $user_fb->publish_perm = $perm;

        return $user_fb->update();
    }

    /**
     * GetFacebookLogin URL
     * @param string $redirect_url The Redirect URL
     * @return string
     */
    public function loadFacebookLoginURL($redirect_url = false)
    {
        $login_uri = $this->facebookConfig['controller_name']."/loginByRedirect/";

        if(!$redirect_url)
            $redirect_url = $this->_baseUrl($login_uri);

        //get vars
        $app_id     = $this->config->app->facebookAppID;
        $app_secret = $this->config->app->facebookAppKey;
        $scope      = $this->config->app->facebookAppScope;

        $helper = new FacebookRedirectLoginHelper($redirect_url, $app_id, $app_secret);
        $params = array("scope" => $scope);
        //get the url
        $url = $helper->getLoginUrl($params);

        //set property to config
        $this->config->app->fb_login_url = $url;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Logins a User with facebook session data, if data has the signed_request property it will be checked
     * @param array $session The session object
     */
    private function __loginUserFacebook($session = null)
    {
        //get model classmap names
        $users_class          = $this->getModuleClassName('users');
        $users_facebook_class = $this->getModuleClassName('users_facebook');

        //the data response
        $login_data = array();

        try {

            if(is_null($session))
                throw new \Exception($this->facebookConfig['text_oauth_redirected']);

            //get session data
            $fac   = $session->getToken();
            $fb_id = $session->getSessionInfo()->getId();

            //get properties
            $properties = $this->getUserFacebookSessionProperties($fac);

            //validate fb session properties
            if(!$properties)
                throw new \Exception($this->facebookConfig['text_session_error']);

            //email validation
            if (empty($properties['email']) || !filter_var($properties['email'], FILTER_VALIDATE_EMAIL)) {
                $this->logger->error("Facebook::__loginUserFacebook() -> Facebook Session (" . $properties['fb_id'] . ") invalid email: " . $properties['email']);
                throw new \Exception(str_replace("{email}", $properties['email'], $this->facebookConfig['text_invalid_email']));
            }

            //OK, check if user exists in Users Facebook table
            $session_data = $this->_getUserSessionData();

            //check if user is logged in and is attempting to loggin to facebook with another account
            if ($session_data && $session_data["fb_id"] && $session_data["fb_id"] != $fb_id) {
                $this->logger->error("Facebook::__loginUserFacebook() -> App Session fb_id (" . $session_data["fb_id"]. ") & sdk session (" . $fb_id . ") data doesn't match.");
                throw new \Exception($this->facebookConfig['text_session_switched']);
            }

            //check if user has already a account registered by email
            $user = $users_class::getUserByEmail($properties['email']);
            //skip user insertion?
            if ($user) {
                //unset fields we won't wish to update (update ignores arbitrary keys)
                unset($properties['first_name'], $properties['last_name']);

                //update user flag if account was pending, or if account is disabled show a warning
                if ($user->account_flag == $users_class::$ACCOUNT_FLAGS['pending']) {
                    $properties['account_flag'] = $users_class::$ACCOUNT_FLAGS['enabled'];
                }
                else if ($user->account_flag == $users_class::$ACCOUNT_FLAGS['disabled']) {
                    throw new \Exception($this->facebookConfig['text_account_disabled']);
                }

                //update user ignoring arbitrary set keys
                $user->update($properties);
            }
            else {
                $user = new $users_class();
                //extend properties
                $properties['account_flag'] = $users_class::$ACCOUNT_FLAGS['enabled']; //set account flag as active
                //insert user
                if (!$user->save($properties)){
                    $this->_sendJsonResponse(200, $this->_parseOrmMessages($user), true);
                }
            }

            //get user facebook data
            $user_fb = $users_facebook_class::getObjectById($fb_id);
            //INSERT a new facebook user
            if (!$user_fb)
                $this->__saveNewUserFacebook($user->id, $fb_id, $fac, $properties['token_expiration']);

            //Guzzle Async operation to extend access token (append fb userID and short live access token)
            $encrypted_data = $this->cryptify->encryptForGetRequest($fb_id."#".$fac);
            //request async with promises
            $extend_token_uri = $this->facebookConfig['controller_name']."/extendAccessToken/";
            $response = (new GuzzleClient())->get($this->_baseUrl($extend_token_uri.$encrypted_data), ['future' => true]);
            //save response only for non production-environment
            $this->logGuzzleResponse($response, $extend_token_uri);
        }
        catch (\Exception $e) {
            //an error ocurred
            $login_data["fb_error"] = $e->getMessage();
            $login_data["properties"] = false;
        }

        //SAVES session if none error ocurred
        if (!isset($login_data["fb_error"])) {
            $login_data["fb_error"]   = false;
            $login_data["properties"] = $properties;
            //set php session as logged in
            $this->_setUserSessionAsLoggedIn($user->id);
        }

        return $login_data;
    }

    /**
     * Get user fb session by Facebook PHP SDK
     * @param string $fac (optional) Access token
     * @param int $user_id
     * @throws Exception
     * @return mixed (boolean|string|object)
     */
    private function __getUserFacebookSession($fac = null, $user_id = 0)
    {
        $users_facebook_class = $this->getModuleClassName('users_facebook');

        try {

            if(is_null($fac)) {
                //get user
                $user_fb = $users_facebook_class::getFacebookDataByUserId($user_id);
                //validates data
                if(!$user_fb)
                    throw new \Exception("invalid user id");

                //get access token
                $fac = $user_fb->fac;
            }

            //open a facebook PHP SDK Session with saved access token
            $session = new FacebookSession($fac);

            //validates session
            if (!$session->validate())
                throw new \Exception("invalid facebook session");

            return $session;
        }
        catch (FacebookRequestException $e) {
            //get the facebook sdk exception
            return $e->getMessage();
        }
        catch (\Exception $e) {
            //another error ocurred
            return $e->getMessage();
        }
    }

    /**
     * Facebook SDK call, get a Long Live Access Token, return a fac object
     * @param object $user_fb
     * @param string $short_live_fac
     * @param FacebookSession $session
     * @return json response
     */
    private function __requestLongLiveAccessToken($user_fb = null, $short_live_fac = null, $session = null)
    {
        if(!$user_fb) {
            $this->logger->log('Facebook::__requestLongLiveAccessToken -> Invalid ORM user facebook prama');
            return;
        }

        //create a fac object
        $fac_obj             = new \stdClass();
        $fac_obj->save       = false;
        $fac_obj->token      = $short_live_fac;
        $fac_obj->expires_at = 0;
        $fac_obj->days_left  = 0;

        try {
            //if session is null open a facebook session with a fresh short-live access token
            if (is_null($session)) {
                $session = $this->__getUserFacebookSession($short_live_fac);

                if(is_string($session))
                    throw new \Exception($session);
            }

            //check expiration of token
            $days_left = DateHelper::getTimePassedFromDate($session->getSessionInfo()->getExpiresAt());

            //check if access token is about to expire
            if ($days_left < $this->facebookConfig['access_token_expiration_threshold']) {
                $this->logger->log('Facebook::__requestLongLiveAccessToken -> Requested a new long live access token for user_fb_id: ' . $user_fb->id);

                $fac_obj->save = true;
                $session = $session->getLongLivedSession();
                //update expiration days left
                $days_left = DateHelper::getTimePassedFromDate($session->getSessionInfo()->getExpiresAt());
            }

            //set object properties
            $fac_obj->token      = $session->getToken();
            $fac_obj->expires_at = $session->getSessionInfo()->getExpiresAt();
            $fac_obj->days_left  = $days_left;
        }
        catch (FacebookRequestException $e) {
            $this->logger->error('Facebook::__requestLongLiveAccessToken -> Error opening session for user_fb_id' . $user_fb->id . ". Trace: " . $e->getMessage());
        }
        catch (\Exception $e) {
            $this->logger->error('Facebook::__requestLongLiveAccessToken -> Error opening session for user_fb_id' . $user_fb->id . ". Trace: " . $e->getMessage());
        }

        return $fac_obj;
    }

    /**
     * Parse given user facebook session data to save in Database
     * @param int $user_id
     * @param int $fb_id
     * @param string $fac
     * @param string $token_expiration
     * @return array
     */
    private function __saveNewUserFacebook($user_id, $fb_id, $fac, $token_expiration)
    {
        $users_class          = $this->getModuleClassName('users');
        $users_facebook_class = $this->getModuleClassName('users_facebook');

        //Creates a Facebook User
        $user_fb             = new $users_facebook_class();
        $user_fb->user_id    = $user_id;
        $user_fb->id         = $fb_id;
        $user_fb->fac        = $fac;
        $user_fb->expires_at = $token_expiration;

        if (!$user_fb->save()) {
            $this->logger->error("Facebook::__saveNewUserFacebook() -> Error Insertion User Facebook data. userId -> ".$user_id.", FBUserId -> ".$fb_id.", trace: ".$this->_parseOrmMessages($user_fb, true));
            $user = $users_class::getObjectById($user_id);
            $user->delete();
            throw new \Exception($this->facebookConfig['text_session_error']); //raise an error
        }

        return $user_fb;
    }

    /**
     * Parse given user facebook session data to save in Database
     * @param mixed $fb_data (object)
     * @param object $fb_session The session object
     * @return array
     */
    private function __parseUserPropertiesForDatabase($fb_data = null, $fb_session = null)
    {
        if(is_null($fb_data) || is_null($fb_session))
            return false;

        $properties = array();

        //parse from array (Javascript SDK)
        if(is_object($fb_data)) {
            $properties['fb_id']      = $fb_data->getId();
            $properties['email']      = $fb_data->getEmail();
            $properties['first_name'] = $fb_data->getFirstName();
            $properties['last_name']  = $fb_data->getLastName();
            //birthday
            $bday = $fb_data->getBirthday();
            $properties['bday'] = is_object($bday) ? $bday->format("Y-m-d") : null;
            //gender (get 1st char or undefined value)
            $gender = $fb_data->getProperty('gender');
            $properties['gender'] = $gender ? $gender[0] : 'u';
        }

        if(empty($properties))
            return false;

        //extend properties, image url & token_expiration
        $properties['image_url'] = str_replace("<size>", "square", str_replace("<fb_id>", $properties['fb_id'], self::$FB_USER_IMAGE_URI));
        $properties['token_expiration'] = $fb_session->getSessionInfo()->getExpiresAt()->format('Y-m-d H:i:s');

        return $properties;
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
        $fb_app_key = $this->config->app->facebookAppKey;

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
