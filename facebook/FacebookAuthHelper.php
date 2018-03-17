<?php
/**
 * Facebook Helper Trait - Facebook php sdk v5.0
 * Requires AccountSession
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
 * FacebookAuthHelper
 */
trait FacebookAuthHelper
{
	/**
	 * Invalidates a facebook user
	 * @param int $fb_id - The Facebook user ID
	 */
	protected function _invalidateAccessToken($fb_id = 0)
	{
		//get object class
		$user_facebook_class = App::getClass("user_facebook");
		//get user & update properties
		$user_fb = $user_facebook_class::getById($fb_id);

		if (!$user_fb)
			return false;

		//remove fac & expiration date
		$user_fb->update([
			"fac"        => null,
			"expires_at" => null
		]);

		//get as array
		$user_data = $user_fb->toArray();
		//extend properties
		$user_data["action"] = "deauth";
		//call listener
		$this->onAppDeauthorized($user_data);
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
		$this->_setUserAccessToken($fac, $user_id);

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
		catch (FacebookResponseException | FacebookSDKException $e) { $exception = $e; }
		catch (\Exception | Exception $e) { $exception = $e; }

		//log exception
		$this->logger->debug("Facebook::_getAccesTokenPermissions -> Exception: ".$exception->getMessage().", userID: $user_id ");
		return false;
	}

	/**
	 * Logins a User with facebook access token,
	 * If data has the signed_request property it will be checked automatically
	 * @param object $fac - The access token object
	 * @param mixed[string|array] $validation - Validates user fields
	 */
	protected function _loginUser($fac = null)
	{
		//get model classmap names
		$user_class          = App::getClass("user");
		$user_facebook_class = App::getClass("user_facebook");

		//the data response
		$login_data = [];
		$exception  = false;

		try {
			//get properties
			$properties = $this->getUserData($fac);

			//validate fb session properties
			if (!$properties)
				throw new Exception($this->facebook_auth_conf["trans"]["SESSION_ERROR"]);

			//OK, check if user exists in user_facebook table & get session data
			$user_session = $this->getUserSession(); //get app session
			$user_fb      = $user_facebook_class::getById($properties["fb_id"]);

			//check if user is already logged In, dont a have a FB, and he is attempting to login facebook with another account
			if ($user_session && $user_session["fb_id"] && $user_session["fb_id"] != $properties["fb_id"]) {

				$this->logger->error("Facebook::_loginUser() -> App Session fb_id (".$user_session["fb_id"].") & sdk session (".$properties["fb_id"].") data not match.");
				throw new Exception($this->facebook_auth_conf["trans"]["SESSION_SWITCHED"]);
			}

			//check if user is already logged In, have a FB user and is linked to another user
			if ($user_session && $user_fb && $user_fb->user_id != $user_session["id"]) {

				$this->logger->error("Facebook::_loginUser() -> App Session fb_id (".$user_session["fb_id"].") & sdk session (".$properties["fb_id"].") data not match.");
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

				//update user flag if account
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
					if (!$this->_filterEmail($properties["email"], $properties["fb_id"]))
						throw new Exception($this->facebook_auth_conf["trans"]["INVALID_EMAIL"]);

					//check existing user with input email
					$user = $user_class::getUserByEmail($properties["email"]);

					if (!$user) {

						//insert user
						$user = new $user_class();
						if (!$user->save($properties))
							$this->jsonResponse(200, $user->messages(), "alert");
					}
				}
			}

			//INSERT a new facebook user
			if (!$user_fb)
				$this->_saveUser($user->id, $properties["fb_id"], $fac);

			//queues an async request, extend access token (append fb userID and short live access token)
			$this->coreRequest([
				"base_url" => "http://localhost/",
				"uri" 	   => "facebook/extendAccessToken/",
				"payload"  => $properties["fb_id"]."#".$fac->getValue(),
				"socket"   => true,
				"encrypt"  => true
			]);
		}
		catch (FacebookResponseException | FacebookSDKException $e) { $exception = $e; }
		catch (\Exception | Exception $e) { $exception = $e; }

		//throw one exception type
		if ($exception) {

			$fb_id = (isset($properties) && is_array($properties)) ? $properties["fb_id"] : "undefined";
			$this->logger->error("Facebook::_loginUser -> Exception: ".$exception->getMessage().". fb_id: ".$fb_id);
			throw new Exception($e->getMessage());
		}

		//mark user as Logged In
		$this->onLogin($user->id);

		//SAVES session if none error ocurred
		$login_data["properties"] = $properties;

		return $login_data;
	}

	/**
	 * Set the facebook Access Token
	 * @param object $fac - The facebook access token object (optional)
	 * @param int $user_id - The user ID
	 * @return mixed [boolean|string|object]
	 */
	protected function _setUserAccessToken($fac = null, $user_id = 0)
	{
		$user_facebook_class = App::getClass("user_facebook");

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
	protected function _saveUser($user_id = null, $fb_id = null, $fac = null)
	{
		$user_class          = App::getClass("user");
		$user_facebook_class = App::getClass("user_facebook");

		//Creates a Facebook User
		$user_fb             = new $user_facebook_class();
		$user_fb->user_id    = $user_id;
		$user_fb->id         = $fb_id;
		$user_fb->fac        = $fac->getValue();
		$user_fb->expires_at = is_object($fac->getExpiresAt()) ? $fac->getExpiresAt()->format("Y-m-d H:i:s") : null;

		if (!$user_fb->save()) {

			$this->logger->error("Facebook::_saveUser() -> Error Insertion User Facebook data. userId -> ".$user_id.",
								  FBUserId -> ".$fb_id.", trace: ".$user_fb->messages(true));

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
	protected function _filterEmail($email = "", $fb_id = 0)
	{
		//email validation
		if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
			return true;

		$this->logger->error("Facebook::_loginUser() -> Facebook Session (".$fb_id.") invalid email: ".$email);

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
	protected function _parseSignedRequest($signed_request = null)
	{
		if (is_null($signed_request))
			return false;

		//set facebook app secret
		$fb_app_key = $this->config->facebook->appKey;

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
			$this->logger->error("Facebook::_parseSignedRequest -> Unknown algorithm. Expected HMAC-SHA256");
			return false;
		}
		//adding the verification of the signed_request below
		$expected_sig = hash_hmac("sha256", $payload, $fb_app_key, $raw = true);
		$signature    = $base64_decode_url($encoded_sig);

		if ($signature !== $expected_sig) {
			$this->logger->error("Facebook::_parseSignedRequest -> Invalid JSON Signature!");
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
