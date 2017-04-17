<?php
/**
 * Base Model User
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Account;

//imports
use Phalcon\Validation;
use Phalcon\Validation\Validator\Email;
use Phalcon\Validation\Validator\InclusionIn;
use Phalcon\Validation\Validator\Uniqueness;

/**
 * Base User Model
 */
abstract class BaseUser extends \CrazyCake\Models\Base
{
    /**
     * Gets Model Message
     * @param string $key - The validation message key
     */
    abstract protected function getMessage($key);

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
     * @var string
     */
    public $first_name;

    /**
     * @var string
     */
    public $last_name;

    /**
     * @var string
     */
    public $created_at;

    /**
     * @var string
     */
    public $last_login;

    /**
     * values in ACCOUNT_FLAGS array
     * @var string
     */
    public $account_flag;

    /* inclusion vars */

    /**
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

    /**
     * Before Validation Event [onUpdate]
     */
    public function beforeValidationOnUpdate()
    {
        parent::beforeValidationOnUpdate();

        //set last login
        $this->last_login = date("Y-m-d H:i:s");
    }

    /**
     * Validations
     */
    public function validation()
    {
        $validator = new Validation();

        //email required
        $validator->add("email", new Email([
            "message"  => $this->getMessage("email_required")
        ]));

        //email unique
        $validator->add("email", new Uniqueness([
            "message" => $this->getMessage("email_uniqueness")
        ]));

        //account flag
        $validator->add("account_flag", new InclusionIn([
            "domain"  => self::$ACCOUNT_FLAGS,
            "message" => 'Invalid user account flag. Flags supported: '.implode(", ", self::$ACCOUNT_FLAGS)
        ]));

        return $this->validate($validator);
    }

    /** ------------------------------------------- § --------------------------------------------------  **/

    /**
     * Find User by email
     * @static
     * @param string $email - The user email
     * @param string $account_flag - The account flag value in self defined array
     * @return Users
     */
    public static function getUserByEmail($email, $account_flag = null)
    {
        $bind = [1 => $email];
        $conditions = "email = ?1"; //default condition

        //filter by account flag?
        if (!is_null($account_flag) && in_array($account_flag, self::$ACCOUNT_FLAGS)) {

            $bind[2] = $account_flag;
            $conditions .= " AND account_flag = ?2";
        }

        return self::findFirst([$conditions, "bind" => $bind]);
    }
}
