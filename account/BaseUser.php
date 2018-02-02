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
	 * @var string
	 */
	public $email;

	/**
	 * For social networks auths this field is optional
	 * @var string
	 */
	public $pass;

	/**
	 * User first name
	 * @var string
	 */
	public $first_name;

	/**
	 * User last name
	 * @var string
	 */
	public $last_name;

	/**
	 * datetime
	 * @var string
	 */
	public $created_at;

	/**
	 * datetime
	 * @var string
	 */
	public $last_login;

	/**
	 * see ACCOUNT_FLAGS array for possible values
	 * @var string
	 */
	public $account_flag;

	/* inclusion vars */

	/**
	 * Account Flags
	 * @static
	 * @var array
	 */
	static $ACCOUNT_FLAGS = ["pending", "enabled", "disabled", "unregistered"];

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
	 * @static
	 * @param string $email - The user email
	 * @param string $account_flag - The account flag value in self defined array
	 * @return object
	 */
	public static function getUserByEmail($email, $account_flag = null)
	{
		$conditions = "email = ?1"; //default condition
		$bind       = [1 => $email];

		//filter by account flag?
		if ($account_flag && in_array($account_flag, self::$ACCOUNT_FLAGS)) {

			$bind[2] = $account_flag;
			$conditions .= " AND account_flag = ?2";
		}

		return self::findFirst([$conditions, "bind" => $bind]);
	}
}
