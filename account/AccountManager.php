<?php
/**
 * Account Manager Trait
 * Common actions for account operations
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Account;

//imports
use Phalcon\Exception;
//core
use CrazyCake\Phalcon\App;

/**
 * Account Manager for already loggedIn users
 */
trait AccountManager
{
	/**
	 * Before update user profile Listener
	 * @param object $user - The user object
	 * @param array $data - The data to be updated
	 * @return array
	 */
	abstract public function beforeUpdateProfile($user, $data);

	/**
	 * Config var
	 * @var array
	 */
	public $account_manager_conf;

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * After Execute Route
	 */
	protected function afterExecuteRoute()
	{
		parent::afterExecuteRoute();
		//for auth required pages disable robots
		$this->view->setVar("html_disallow_robots", true);
	}

	/**
	 * Initialize Trait
	 * @param array $conf - The config array
	 */
	public function initAccountManager($conf = [])
	{
		//defaults
		$defaults = [
			"pass_min_length" => 8,
			"required_fields" => [], //aditional user fields
			//entities
			"user_entity" => "User"
		];

		//merge confs
		$conf = array_merge($defaults, $conf);
		//append class prefixes
		$conf["user_entity"] = App::getClass($conf["user_entity"]);

		//set configuration
		$this->account_manager_conf = $conf;
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Ajax (POST) - update account settings
	 */
	public function updateProfileAction()
	{
		//get post params
		$default_params = [
			"@first_name"   => "string",
			"@last_name"    => "string",
			"@current_pass" => "string",
			"@pass"         => "string"
		];

		//get model class name
		$user_class = $this->account_manager_conf["user_entity"];
		//get user
		$user = $user_class::getById($this->user_session["id"]);
		//validate user
		if (!$user)
			$this->jsonResponse(404);

		//get settings params
		$setting_params = isset($this->account_manager_conf["required_fields"]) ? $this->account_manager_conf["required_fields"] : [];
		//validate and filter request params data, second params are the required fields
		$data = $this->handleRequest(array_merge($default_params, $setting_params), "POST");

		try {

			//check if profile changed and save new data
			$updating_data = [];

			//check for password
			if (!empty($data["pass"]) && empty($data["current_pass"]))
				throw new Exception($this->account_manager_conf["trans"]["CURRENT_PASS_EMPTY"]);

			//changed pass validation
			if (!empty($data["pass"]) && !empty($data["current_pass"])) {

				if (strlen($data["pass"]) < $this->account_manager_conf["pass_min_length"])
					throw new Exception($this->account_manager_conf["trans"]["PASS_TOO_SHORT"]);

				//check current pass
				if (!$this->security->checkHash($data["current_pass"], $user->pass))
					throw new Exception($this->account_manager_conf["trans"]["PASS_DONT_MATCH"]);

				//check pass is diffetent to current
				if ($this->security->checkHash($data["pass"], $user->pass))
					throw new Exception($this->account_manager_conf["trans"]["NEW_PASS_EQUALS"]);

				//ok, update pass
				$updating_data["pass"] = $this->security->hash($data["pass"]);
			}

			//check first & last name
			if (strlen($data["first_name"]) < 3 || strlen($data["last_name"]) < 3)
				throw new Exception($this->account_manager_conf["trans"]["INVALID_NAMES"]);

			//check for a change
			if ($data["first_name"] != $user->first_name) {

				//validate name
				if (strcspn($data["first_name"], "0123456789") != strlen($data["first_name"]))
					throw new Exception($this->account_manager_conf["trans"]["INVALID_NAMES"]);

				//format to capitalized name
				$updating_data["first_name"] = mb_convert_case($data["first_name"], MB_CASE_TITLE, "UTF-8");
			}

			if ($data["last_name"] != $user->last_name) {

				//validate name
				if (strcspn($data["last_name"], "0123456789") != strlen($data["last_name"]))
					throw new Exception($this->account_manager_conf["trans"]["INVALID_NAMES"]);

				//format to capitalized name
				$updating_data["last_name"] = mb_convert_case($data["last_name"], MB_CASE_TITLE, "UTF-8");
			}

			//call abstract method to do further updates
			$new_updates = $this->beforeUpdateProfile($user, $data);
			//merge updates
			if (is_array($new_updates))
				$updating_data = array_merge($updating_data, $new_updates);

			//update data?
			if (!empty($updating_data)) {

				$user->update($updating_data);
				//update session data
				$this->updateUserSession($updating_data);
				//update full name
				$updating_data["name"] = $user->first_name." ".$user->last_name;

				//check if pass set
				if (isset($updating_data["pass"]))
					$updating_data["pass"] = true;
			}

			//send response
			$this->jsonResponse(200, [
				"user" => $updating_data,
				"msg"  => $this->account_manager_conf["trans"]["PROFILE_SAVED"]
			]);
		}
		catch (Exception $e) {
			$this->jsonResponse(200, $e->getMessage(), "warning");
		}
	}
}
