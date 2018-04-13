<?php
/**
 * Base Model User (Relational)
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Account;

/**
 * Base User Model
 */
class BaseUser extends \CrazyCake\Models\Base
{
	/* properties */

	/**
	 * The user primary email
	 * @var String
	 */
	public $email;

	/**
	 * For social networks auths this field is optional
	 * @var String
	 */
	public $pass;

	/**
	 * User first name
	 * @var String
	 */
	public $first_name;

	/**
	 * User last name
	 * @var String
	 */
	public $last_name;

	/**
	 * datetime
	 * @var String
	 */
	public $created_at;

	/**
	 * datetime
	 * @var String
	 */
	public $last_login;

	/**
	 * FLAGS array for possible values
	 * @var String
	 */
	public $flag;

	/* inclusion vars */

	/**
	 * Account Flags
	 * @var Array
	 */
	static $FLAGS = ["pending", "enabled", "disabled"];

	/**
	 * Before Validation Event [onCreate]
	 */
	public function beforeValidationOnCreate()
	{
		//set password hash
		if (!is_null($this->pass))
			$this->pass = $this->getDI()->getShared("security")->hash($this->pass);

		//set dates
		$this->last_login = date("Y-m-d H:i:s");
		$this->created_at = date("Y-m-d H:i:s");
	}

	/** ------------------------------------------- § --------------------------------------------------  **/

	/**
	 * Find User by email
	 * @param String $email - The user email
	 * @param String $flag - The account flag value in self defined array
	 * @return Object
	 */
	public static function getUserByEmail($email, $flag = null)
	{
		$conditions = "email = ?0"; //default condition
		$bind       = [$email];

		//filter by account flag?
		if ($flag) {

			$bind[] = $flag;
			$conditions .= " AND flag = ?1";
		}

		return self::findFirst([$conditions, "bind" => $bind]);
	}
}
