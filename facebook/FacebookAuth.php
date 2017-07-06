<?php
/**
 * Facebook Trait - Facebook php sdk v5.0
 * Requires a Frontend or Backend Module with CoreController and Session Trait
 * Open Graph v2.4
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Facebook;

//imports
use Phalcon\Exception;
//Facebook PHP SDK
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Exceptions\FacebookResponseException;
//CrazyCake Libs
use CrazyCake\Phalcon\App;

/**
 * Facebook Authentication
 */
trait FacebookAuth
{
	use FacebookAuthHelper;

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
	abstract public function onAppDeauthorized($data = []);

	/**
	 * trait config var
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
	 * @static
	 * @var string
	 */
	public static $FB_EMAIL_SETTINGS_URL = "https://www.facebook.com/settings?tab=account&section=email&view";

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Initialize Trait
	 * @param array $conf - The config array
	 */
	public function initFacebookAuth($conf = [])
	{
		$this->facebook_auth_conf = $conf;

		//set Facebook Object
		if (is_null($this->fb)) {

			$this->fb = new \Facebook\Facebook([
				"app_id"     => $this->config->facebook->appID,
				"app_secret" => $this->config->facebook->appKey,
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
		//validate and filter request params data
		$data = $this->handleRequest([
			"signed_request" => "string",
			"@user_data"     => "int",
			"@validation"    => "string"
		], "POST");

		try {
			//check signed request
			if (!$this->_parseSignedRequest($data["signed_request"]))
				return $this->jsonResponse(405);

			//call js helper
			$helper = $this->fb->getJavaScriptHelper();
			$fac    = $helper->getAccessToken();

			//handle login
			$response = $this->_loginUser($fac);
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
				return $this->onLoginDispatch(false, $response);
			else
				return $this->onLoginDispatch();
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
			$response = $this->_loginUser($fac);
			$response["perms"] = null;

			//check perms
			if (!empty($route["validation"]))
				$response["perms"] = $this->_getAccesTokenPermissions($fac, 0, $route["validation"]);

			//call listener
			$this->onSuccessAuth($route, $response);

			//handle response automatically
			if (empty($route["controller"]))
				return $this->onLoginDispatch();

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
	public function loadFacebookLoginURL($route = [], $scope = null, $validation = false)
	{
		//check link perms
		if (empty($scope)) {
			$scope = $this->config->facebook->appScope;
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
		$this->config->facebook->loginUrl = $url;
	}

	/**
	 * Async (GET) - Extended Facebook Access Token (LongLive Token)
	 * FAC means Facebook Access Token
	 * @param string $encrypted_data - The encrypted data, struct: {user_id#fac}
	 * @return json response
	 */
	public function extendAccessTokenAction($encrypted_data = "")
	{
		$this->logger->debug("Facebook::extendAccessTokenAction -> received encrypted_data: ".$encrypted_data);

		if (empty($encrypted_data))
			return $this->jsonResponse(405); //method not allowed

		try {
			//get encrypted facebook user id and short live access token
			$data = $this->cryptify->decryptData($encrypted_data, "#");
			//set vars
			list($fb_id, $short_live_fac) = $data;

			//find user on db
			$user_facebook_class = App::getClass("user_facebook");
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
		//$this->logger->debug("FacebookAuth::deauthorize:\n".print_r($data, true)." Headers: ".print_r($headers, true)." Body: ".print_r($body, true));

		try {
			/** 1.- User deleted tha app from his facebook account settings */
			if (!empty($data["signed_request"])) {

				$fb_data = $this->_parseSignedRequest($data["signed_request"]);

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
				if ($data["hub_verify_token"] != $this->config->facebook->webhookToken)
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
			$scope = empty($scope) ? $this->config->facebook->appScope : $scope;
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
			$this->_setUserAccessToken($fac, $user_id);

			//get the graph-user object for the current user (validation)
			$response = $this->fb->get("/me?fields=email,name,first_name,last_name,gender,birthday,age_range,locale");

			if (!$response)
				throw new Exception("Invalid facebook data from /me?fields= request.");

			//parse user fb session properties
			$fb_data    = $response->getGraphNode();
			$properties = [
				"fb_id"      => $fb_data->getField("id"),
				"email"      => strtolower($fb_data->getField("email")),
				"first_name" => $fb_data->getField("first_name"),
				"last_name"  => $fb_data->getField("last_name"),
				"locale"     => $fb_data->getField("locale"),
				"birthday"   => null
			];

			//get gender
			$gender = $fb_data->getField("gender");
			$properties["gender"] = $gender ?: null;

			//birthday
			$birthday = $fb_data->getField("birthday");
			$birthday = is_object($birthday) ? $birthday->format("Y-m-d") : $birthday;

			if(is_string($birthday)) {

				//is year YYYY format?
				if(strlen($birthday) == 4) { $birthday = $birthday."-00-00"; }
				//is MM/DD format?
				else if(strlen($birthday) == 5) { $birthday = "0000-".$birthday; }

				$properties["birthday"] = str_replace("/", "-", $birthday);
			}

			//age range
			$age_range = $fb_data->getField("age_range");
			$age_range = (isset($age_range["min"]) ? $age_range["min"] : "x")."-";
			$age_range .= (isset($age_range["max"]) ? $age_range["max"] : "x");
			$properties["age_range"] = $age_range;

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
}
