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
//CrazyCake Libs
use CrazyCake\Phalcon\AppModule;
use CrazyCake\Helpers\Dates;

/**
 * Facebook Authentication
 */
trait FacebookAuth
{
    /**
     * Listener - On success auth
     * @param object $route - The app route object
     * @param object $response - The received response
     */
    abstract public function onSuccessAuth(&$route, $response);

    /**
     * Listener - On app deauthorized
     * @param object $data - The response data
     */
    abstract public function onAppDeauthorized($data = array());

    /**
     * Config var
     * @var array
     */
    public $facebook_auth_conf;

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

    /**
     * Facebook Email settings URL
     * @var string
     */
    public static $FB_EMAIL_SETTINGS_URL = "https://www.facebook.com/settings?tab=account&section=email&view";

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * This method must be call in constructor parent class
     * @param array $conf - The config array
     */
    public function initFacebookAuth($conf = [])
    {
        $this->facebook_auth_conf = $conf;

        //set Facebook Object
        if (is_null($this->fb)) {

            $this->fb = new \Facebook\Facebook([
                "app_id"     => $this->config->app->facebook->appID,
                "app_secret" => $this->config->app->facebook->appKey,
                //api version
                "default_graph_version" => "v2.5"
            ]);
        }
    }

    /**
     * Ajax - Login user by JS SDK.
     */
    public function loginAction()
    {
        //validate and filter request params data, second params are the required fields
        $data = $this->handleRequest([
            "signed_request" => "string",
            "@user_data"     => "int",
            "@validation"    => "string"
        ]);

        try {
            //check signed request
            if (!$this->__parseSignedRequest($data["signed_request"]))
                return $this->jsonResponse(405);

            //call js helper
            $helper = $this->fb->getJavaScriptHelper();
            $fac    = $helper->getAccessToken();

            //handle login
            $response = $this->__loginUser($fac);
            $response["perms"] = null;

            //check perms
            if (isset($data["validation"]) && $data["validation"])
                $response["perms"] = $this->_getAccesTokenPermissions($fac, 0, $data["validation"]);

            //route object
            $route = [
                "controller" => $this->router->getControllerName(),
                "action"     => $this->router->getActionName(),
                "strategy"   => "js-sdk"
            ];

            //call listener
            $this->onSuccessAuth($route, $response);

            //handle response, session controller
            if (isset($data["user_data"]) && $data["user_data"])
                return $this->dispatchOnUserLoggedIn("account", $response, false);
            else
                return $this->dispatchOnUserLoggedIn();
        }
        catch (FacebookResponseException $e) { $exception = $e; }
        catch (FacebookSDKException $e)      { $exception = $e; }
        catch (Exception $e)                 { $exception = $e; }
        catch (\Exception $e)                { $exception = $e; }

        //an exception ocurred
        $msg = isset($exception) ? $exception->getMessage() : $this->facebook_auth_conf["trans"]["OAUTH_REDIRECTED"];

        if ($exception instanceof FacebookResponseException)
            $msg = $this->facebook_auth_conf["trans"]["OAUTH_PERMS"];

        return $this->jsonResponse(200, $msg, "notice");
    }

    /**
     * Handler - Login by redirect action via facebook login URL
     * @param string $encrypted_data - The encrypted route data
     * @return json response
     */
    public function loginByRedirectAction($encrypted_data = "")
    {
        try {
            //get helper object
            $helper = $this->fb->getRedirectLoginHelper();
            $fac    = $helper->getAccessToken();

            //get decrypted params
            $route = (array)$this->cryptify->decryptData($encrypted_data, true);

            if (empty($route))
                throw new Exception($this->facebook_auth_conf["trans"]["OAUTH_REDIRECTED"]);

            //handle login
            $response = $this->__loginUser($fac);
            $response["perms"] = null;

            //check perms
            if (!empty($route["validation"]))
                $response["perms"] = $this->_getAccesTokenPermissions($fac, 0, $route["validation"]);

            //call listener
            $this->onSuccessAuth($route, $response);

            //handle response automatically
            if (empty($route["controller"]))
                return $this->dispatchOnUserLoggedIn();

            //Redirect
            $uri = $route["controller"]."/".$route["action"]."/".(empty($route["payload"]) ? "" : implode("/", $route["payload"]));
            return $this->redirectTo($uri);
        }
        catch (FacebookResponseException $e) { $exception = $e; }
        catch (FacebookSDKException $e)      { $exception = $e; }
        catch (Exception $e)                 { $exception = $e; }
        catch (\Exception $e)                { $exception = $e; }

        $this->logger->error("Facebook::loginByRedirectAction -> An error ocurred: ".$exception->getMessage());

        //debug
        if ($this->request->isAjax())
            return $this->jsonResponse(200, $exception->getMessage(), "alert");

        //set message
        $msg = $this->facebook_auth_conf["trans"]["OAUTH_REDIRECTED"]."\n".$e->getMessage();
        $this->view->setVar("error_message", $msg);

        $this->dispatcher->forward(["controller" => "error", "action" => "internal"]);
    }

    /**
     * GetFacebookLogin URL
     * @param array $route - Custom Route (optional)
     * @param string $scope - Custom scope
     * @param boolean $validation - Validates given scope
     * @return string
     */
    public function loadFacebookLoginURL($route = array(), $scope = null, $validation = false)
    {
        //check link perms
        if (empty($scope)) {
            $scope = $this->config->app->facebook->appScope;
        }

        $route = [
            "controller" => isset($route["controller"]) ? $route["controller"] : null,
            "action"     => isset($route["action"]) ? $route["action"] : null,
            "payload"    => isset($route["payload"]) ? $route["payload"] : null,
            "strategy"   => "redirection",
            "validation" => $validation ? $scope : false
        ];

        //encrypt data
        $route = $this->cryptify->encryptData($route);
        //set callback
        $callback = $this->baseUrl("facebook/loginByRedirect/".$route);
        //set helper
        $helper = $this->fb->getRedirectLoginHelper();
        //get the url
        $url = $helper->getLoginUrl($callback, explode(",", $scope));
        //set property to config
        $this->config->app->facebook->loginUrl = $url;
    }

    /**
     * Async (GET) - Extended Facebook Access Token (LongLive Token)
     * FAC means Facebook Access Token
     * @param string $encrypted_data - The encrypted data, struct: {user_id#fac}
     * @return json response
     */
    public function extendAccessTokenAction($encrypted_data = "")
    {
        if (empty($encrypted_data))
            return $this->jsonResponse(405); //method not allowed

        try {
            //get encrypted facebook user id and short live access token
            $data = $this->cryptify->decryptData($encrypted_data, "#");
            //set vars
            list($fb_id, $short_live_fac) = $data;

            //find user on db
            $user_facebook_class = AppModule::getClass("user_facebook");
            $user_fb = $user_facebook_class::getById($fb_id);

            if (!$user_fb || empty($short_live_fac))
                $this->jsonResponse(400); //bad request

            //if a session error ocurred, get a new long live access token
            $this->logger->log("Facebook::extendAccessTokenAction -> Requesting a new long live access token for fb_id: $user_fb->id");

            //get new long live fac
            $client = $this->fb->getOAuth2Client();
            $fac    = $client->getLongLivedAccessToken($short_live_fac);

            //save new long-live access token?
            if ($fac) {
                //set new access token
                $data = ["fac" => $fac->getValue()];

                if (is_object($fac->getExpiresAt()))
                    $data["expires_at"] = $fac->getExpiresAt()->format("Y-m-d H:i:s");

                //update in db
                $user_fb->update($data);
            }

            //send JSON response with payload
            return $this->jsonResponse(200, ["fb_id" => $user_fb->id]);
        }
        catch (FacebookResponseException $e) { $exception = $e; }
        catch (FacebookSDKException $e)      { $exception = $e; }
        catch (Exception $e)                 { $exception = $e; }
        catch (\Exception $e)                { $exception = $e; }

        //exception
        $this->logger->error("Facebook::extendAccessToken -> Exception: ".$exception->getMessage().". fb_id: ".(isset($user_fb->id) ? $user_fb->id : "unknown"));
        $this->jsonResponse(400);
    }

    /**
     * WebHook - Deauthorize a facebook user
     * If a valid signed request is given fb user will be removed
     * @param string $params - Params
     * @return mixed [boolean|array]
     */
    public function deauthorizeAction()
    {
        //get or post params
        $data = $this->handleRequest([
            "@signed_request"   => "string",
            "@hub_mode"         => "string",
            "@hub_challenge"    => "string",
            "@hub_verify_token" => "string"
        ], "MIXED", false);

        //get headers & json raw body if set
        $headers = $this->request->getHeaders();
        $body    = $this->request->getJsonRawBody();
        //$this->logger->debug("FacebookAuth::deauthorize:\n".print_r($data,true)." Headers: ".print_r($headers, true)." Body: ".print_r($body, true));

        try {
            /** 1.- User deleted tha app from his facebook account settings */
            if (!empty($data["signed_request"])) {

                $fb_data = $this->__parseSignedRequest($data["signed_request"]);

                if (!$fb_data)
                    throw new Exception("invalid Facebook Signed Request: ".json_encode($fb_data));

                //invalidate user
                $this->_invalidateAccessToken($fb_data["fb_id"]);
                //set data Response
                $data = $fb_data["fb_id"];
            }
            /** 2.- An app permission field changed in user facebook account app settings */
            else if (isset($body) && is_array($body->entry) && !is_null($body->entry[0])) {

                //validate signature
                if (!isset($headers["X-Hub-Signature"]))
                    throw new Exception("Invalid Facebook Hub Signature");

                //parse body
                $data["fb_id"]          = $body->entry[0]->id;
                $data["time"]           = $body->entry[0]->time;
                $data["changed_fields"] = $body->entry[0]->changed_fields;
                //append user data

                //listener, must be implemented
                $this->onAppDeauthorized($data);
            }
            /** 3.- Validates a FB webhook [Handling Verification Requests, DEV only] **/
            else if (!empty($data["hub_challenge"]) && !empty($data["hub_verify_token"])) {

                //throw Exception for a invalid token
                if ($data["hub_verify_token"] != $this->config->app->facebook->webhookToken)
                    throw new Exception("Invalid Facebook Hub Token");

                //send hub_challenge value
                $data = $data["hub_challenge"];
            }
            else {
                throw new Exception("Empty parameters request");
            }
        }
        catch (Exception $e) {

            $this->logger->error("FacebookAuth::facebook deauthorize webhook exception:\n".$e->getMessage());
            $data = $e->getMessage();
        }
        finally {
            //send data as text
            $this->textResponse($data);
        }
    }

    /**
     * Ajax (GET) - Check if user facebook link is valid with scope perms
     * Requires be logged In
     * @param boolean $scope Optional for custom scope
     */
    public function checkLinkAppStateAction($scope = null)
    {
        //make sure is ajax request
        $this->onlyAjax();
        //handle response, dispatch to auth/logout
        $this->requireLoggedIn();

        try {
            //get fb user data
            $fb_data = $this->getUserData(null, $this->user_session["id"]);

            if (!$fb_data)
                throw new Exception("Invalid Facebook Access Token");

            //validate permissions
            $scope = empty($scope) ? $this->config->app->facebook->appScope : $scope;
            $perms = $this->_getAccesTokenPermissions(null, $this->user_session["id"], $scope);

            if (!$perms)
                throw new Exception("App permissions not granted");

            //set payload
            $payload = ["status" => true, "fb_id" => $fb_data["fb_id"], "perms" => $perms];
        }
        catch (Exception $e) {

            $payload = ["status" => false, "exception" => $e->getMessage()];
        }

        //send JSON response
        $this->jsonResponse(200, $payload);
    }

    /**
     * Ajax (POST) - unlinks a facebook user
     * Requires be logged In
     */
    public function unlinkUserAction()
    {
        //make sure is ajax request
        $this->onlyAjax();
        //handle response, dispatch to auth/logout
        $this->requireLoggedIn();

        //validate facebook user
        if (!$this->user_session["fb_id"])
            $this->jsonResponse(404);

        //invalidate user
        $this->_invalidateAccessToken($this->user_session["fb_id"]);
        //send JSON response
        $this->jsonResponse(200);
    }

    /**
     * Handler - Get user facebook properties
     * @param object $fac - The facebook access token
     * @param int $user_id - The user ID
     * @return array
     */
    public function getUserData($fac = null, $user_id = 0)
    {
        try {

            //get session using short access token or with saved access token
            $this->__setUserAccessToken($fac, $user_id);

            //get the graph-user object for the current user (validation)
            $response = $this->fb->get("/me?fields=email,name,first_name,last_name,birthday,gender");

            if (!$response)
                throw new Exception("Invalid facebook data from /me?fields= request.");

            //parse user fb session properties
            $fb_data    = $response->getGraphNode();
            $properties = [
                "fb_id"      => $fb_data->getField("id"),
                "email"      => strtolower($fb_data->getField("email")),
                "first_name" => $fb_data->getField("first_name"),
                "last_name"  => $fb_data->getField("last_name")
            ];

            //birthday
            $birthday = $fb_data->getField("birthday");
            $birthday = is_object($birthday) ? $birthday->format("Y-m-d") : $birthday;

            if(is_string($birthday)) {

                //is year YYYY format?
                if(strlen($birthday) == 4) {
                    $properties["birthday"] = $birthday."-00-00";
                }
                //is MM/DD format?
                else if(strlen($birthday) == 5) {
                    $properties["birthday"] = "0000/".$birthday;
                }
            }
            else {
                $properties["birthday"] = null;
            }

            //get gender
            $gender = $fb_data->getField("gender");
            $properties["gender"] = $gender ? $gender : "undefined";

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

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Logins a User with facebook access token,
     * If data has the signed_request property it will be checked automatically
     * @param object $fac - The access token object
     * @param mixed[string|array] $validation - Validates user fields
     */
    private function __loginUser($fac = null)
    {
        //get model classmap names
        $user_class          = AppModule::getClass("user");
        $user_facebook_class = AppModule::getClass("user_facebook");

        //the data response
        $login_data = [];
        $exception  = false;

        try {
            //get properties
            $properties = $this->getUserData($fac);

            //validate fb session properties
            if (!$properties)
                throw new Exception($this->facebook_auth_conf["trans"]["SESSION_ERROR"]);
            //print_r($properties);exit;

            //OK, check if user exists in user_facebook table & get session data
            $user_session = $this->getUserSession(); //get app session
            $user_fb      = $user_facebook_class::getById($properties["fb_id"]);

            //check if user is already logged In, dont a have a FB, and he is attempting to login facebook with another account
            if ($user_session && $user_session["fb_id"] && $user_session["fb_id"] != $properties["fb_id"]) {

                $this->logger->error("Facebook::__loginUser() -> App Session fb_id (".$user_session["fb_id"].") & sdk session (".$properties["fb_id"].") data not match.");
                throw new Exception($this->facebook_auth_conf["trans"]["SESSION_SWITCHED"]);
            }

            //check if user is already logged In, have a FB user and is linked to another user
            if ($user_session && $user_fb && $user_fb->user_id != $user_session["id"]) {

                $this->logger->error("Facebook::__loginUser() -> App Session fb_id (".$user_session["fb_id"].") & sdk session (".$properties["fb_id"].") data not match.");
                throw new Exception($this->facebook_auth_conf["trans"]["ACCOUNT_SWITCHED"]);
            }

            //check if user has already a account registered by email
            $user = $user_fb ? $user_fb->user : null;
            //var_dump($properties["fb_id"], $user_fb);exit;

            //if user has already a facebook link....
            if ($user) {

                //disabled account
                if ($user->account_flag == "disabled")
                    throw new Exception($this->facebook_auth_conf["trans"]["ACCOUNT_DISABLED"]);

                //update user flag if account was pending, or if account is disabled show a warning
                if ($user->account_flag == "pending")
                    $user->update(["account_flag" => "enabled"]);

                //set auth state
                $login_data["auth"] = "existing_user";
            }
            //user dont have facebook link...
            else {

                //set account flag as active & auth state
                $properties["account_flag"] = "enabled";
                $login_data["auth"]         = "new_user";

                //check if user is already logged in
                if ($user_session) {

                    $user = $user_class::getById($user_session["id"]);
                }
                //first time logged in
                else {

                    //user dont have an account, checking facebook email...
                    if (!$this->__filterEmail($properties["email"], $properties["fb_id"]))
                        throw new Exception($this->facebook_auth_conf["trans"]["INVALID_EMAIL"]);

                    //check existing user with input email
                    $user = $user_class::getUserByEmail($properties["email"]);

                    if (!$user) {

                        //insert user
                        $user = new $user_class();
                        if (!$user->save($properties))
                            $this->jsonResponse(200, $user->allMessages(), "alert");
                    }
                }
            }

            //INSERT a new facebook user
            if (!$user_fb)
                $this->__saveUser($user->id, $properties["fb_id"], $fac);

            //queues an async request, extend access token (append fb userID and short live access token)
            $this->asyncRequest([
                "controller" => "facebook",
                "action"     => "extendAccessToken",
                "socket"     => true,
                "payload"    => $properties["fb_id"]."#".$fac->getValue()
            ]);
        }
        catch (FacebookResponseException $e) { $exception = $e; }
        catch (FacebookSDKException $e)      { $exception = $e; }
        catch (Exception $e)                 { $exception = $e; }
        catch (\Exception $e)                { $exception = $e; }

        //throw one exception type
        if ($exception) {

            $fb_id = (isset($properties) && is_array($properties)) ? $properties["fb_id"] : "undefined";
            $this->logger->error("Facebook::__loginUser -> Exception: ".$exception->getMessage().". fb_id: ".$fb_id);
            throw new Exception($e->getMessage());
        }

        //SAVES session if none error ocurred
        $login_data["properties"] = $properties;
        //set php session as logged in
        $this->userHasLoggedIn($user->id);

        return $login_data;
    }

    /**
     * Invalidates a facebook user
     * @param int $fb_id - The Facebook user ID
     */
    protected function _invalidateAccessToken($fb_id = 0)
    {
        //get object class
        $user_facebook_class = AppModule::getClass("user_facebook");
        //get user & update properties
        $user_fb = $user_facebook_class::getById($fb_id);

        if (!$user_fb)
            return false;

        $user_data = $user_fb->toArray();
        //extend properties
        $user_data["action"] = "deleted";
        //call listener
        $this->onAppDeauthorized($user_data);
        //remove fac & expiration date
        $user_fb->update([
            "fac"        => null,
            "expires_at" => null
        ]);
    }

    /**
     * Get Access Token permissions
     * @param object $fac - The facebook access token
     * @param int $user_id - The user ID
     * @param mixed [boolean, string, array] $scope - If value is set then validates also the granted perms
     * @return mixed [boolean|array] - False if perms validation fail
     */
    protected function _getAccesTokenPermissions($fac = null, $user_id = 0, $scope = false)
    {
        //get session using short access token or with saved access token
        $this->__setUserAccessToken($fac, $user_id);

        try {
            $this->logger->debug("Facebook::_getAccesTokenPermissions -> checking fac perms for userID: $user_id,  scope: ".json_encode($scope));

            $response = $this->fb->get("/me/permissions");

            if (!$response)
                throw new Exception("Invalid facebook data from /me/permissions request.");

            //parse user fb session properties
            $fb_data = $response->getDecodedBody();

            if (!isset($fb_data["data"]) || empty($fb_data["data"]))
                throw new Exception("Invalid facebook data from /me/permissions request.");

            //get perms array
            $perms = $fb_data["data"];
            //print_r($fb_data);exit;

            //validate scope permissions
            if ($scope) {

                $scope = is_array($scope) ? $scope : explode(",", $scope);

                //se valida desde los permisos entregados por facebook
                foreach ($perms as $p) {

                    //validates declined permissions
                    if (in_array($p["permission"], $scope) && (!isset($p["status"]) || $p["status"] != "granted"))
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
        $this->logger->debug("Facebook::_getAccesTokenPermissions -> Exception: ".$exception->getMessage().", userID: $user_id ");
        return false;
    }

    /**
     * Set the facebook Access Token
     * @param object $fac - The facebook access token object (optional)
     * @param int $user_id - The user ID
     * @return mixed [boolean|string|object]
     */
    private function __setUserAccessToken($fac = null, $user_id = 0)
    {
        $user_facebook_class = AppModule::getClass("user_facebook");

        //get stored fac if its null
        if (empty($fac)) {
            //get user
            $user_fb = $user_facebook_class::findFirstByUserId($user_id);
            //validates data
            if (!$user_fb)
                throw new Exception("invalid user id");

            //get access token
            $fac = $user_fb->fac;
        }
        //check for a fac object
        else if (is_object($fac)) {
            $fac = $fac->getValue();
        }

        //open a facebook PHP SDK Session with saved access token
        $this->fb->setDefaultAccessToken($fac);
    }

    /**
     * Parse given user facebook session data to save in Database
     * @param int $user_id - The user ID
     * @param int $fb_id - The facebook user ID
     * @param object $fac - The access token object
     * @return array
     */
    private function __saveUser($user_id = null, $fb_id = null, $fac = null)
    {
        $user_class          = AppModule::getClass("user");
        $user_facebook_class = AppModule::getClass("user_facebook");

        //Creates a Facebook User
        $user_fb             = new $user_facebook_class();
        $user_fb->user_id    = $user_id;
        $user_fb->id         = $fb_id;
        $user_fb->fac        = $fac->getValue();
        $user_fb->expires_at = is_object($fac->getExpiresAt()) ? $fac->getExpiresAt()->format("Y-m-d H:i:s") : null;

        if (!$user_fb->save()) {

            $this->logger->error("Facebook::__saveUser() -> Error Insertion User Facebook data. userId -> ".$user_id.",
                                  FBUserId -> ".$fb_id.", trace: ".$user_fb->allMessages(true));

            $user = $user_class::getById($user_id);
            $user->delete();
            //raise an error
            throw new Exception($this->facebook_auth_conf["trans"]["SESSION_ERROR"]);
        }

        return $user_fb;
    }

    /**
     * Validate Facebook Email
     * @param string $email - The props email
     * @param int $fb_id - Facebook Id Optional
     * @return [type] [description]
     */
    private function __filterEmail($email = "", $fb_id = 0)
    {
        //email validation
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
            return true;

        $this->logger->error("Facebook::__loginUser() -> Facebook Session (".$fb_id.") invalid email: ".$email);

        //invalidate email user
        $request = $this->fb->delete("/me/permissions");

        return false;
    }

    /**
     * Parse facebook signed request got from Javascript SDK
     * @link https://developers.facebook.com/docs/games/canvas/login
     * @param string $signed_request - Signed request received from Facebook API
     * @return mixed
     */
    private function __parseSignedRequest($signed_request = null)
    {
        if (is_null($signed_request))
            return false;

        //set facebook app secret
        $fb_app_key = $this->config->app->facebook->appKey;

        //set properties with list
        list($encoded_sig, $payload) = explode(".", $signed_request, 2);

        //anonymous function
        $base64_decode_url = function($str) {
            return base64_decode(strtr($str, "-_", "+/"));
        };

        //decode the data
        $data = json_decode($base64_decode_url($payload), true);
        //check algorithm
        if (strtoupper($data["algorithm"]) !== "HMAC-SHA256") {
            $this->logger->error("Facebook::__parseSignedRequest -> Unknown algorithm. Expected HMAC-SHA256");
            return false;
        }
        //adding the verification of the signed_request below
        $expected_sig = hash_hmac("sha256", $payload, $fb_app_key, $raw = true);
        $signature    = $base64_decode_url($encoded_sig);

        if ($signature !== $expected_sig) {
            $this->logger->error("Facebook::__parseSignedRequest -> Invalid JSON Signature!");
            return false;
        }

        //parse data to avoid var mistakes
        if (isset($data["user_id"])) {

            $data["fb_id"] = $data["user_id"];
            unset($data["user_id"]);
        }

        return $data;
    }
}
