<?php
/**
 * Base Model Users
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Account;

//imports
use Phalcon\Mvc\Model\Validator\Email;
use Phalcon\Mvc\Model\Validator\InclusionIn;
use Phalcon\Mvc\Model\Validator\Uniqueness;

/**
 * Base User Model
 */
abstract class BaseUsers extends \CrazyCake\Models\Base
{
    /**
     * Gets Model Message
     */
    abstract protected function getModelMessage($key);

    /* properties */

    /**
     * @var string
     */
    public $email;

    /**
     * for social networks auths this field is optional
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
    static $ACCOUNT_FLAGS = ['pending', 'enabled', 'disabled'];

    /**
     * Initializer
     */
    public function initialize()
    {
        //Skips fields/columns on both INSERT/UPDATE operations
        $this->skipAttributes(['created_at']);
    }

    /**
     * After Fetch Event
     */
    public function afterFetch()
    {
        //hashed ticket id?
        if(isset($this->id))
            $this->id_hashed = $this->getDI()->getShared('cryptify')->encryptHashId($this->id);
    }

    /**
     * Before Validation Event [onCreate]
     */
    public function beforeValidationOnCreate()
    {
        //set password hash
        if(!is_null($this->pass))
            $this->pass = $this->getDI()->getShared('security')->hash( $this->pass );

        //set last login
        $this->last_login = date('Y-m-d H:i:s');
    }

    /**
     * Before Validation Event [onUpdate]
     */
    public function beforeValidationOnUpdate()
    {
        parent::beforeValidationOnUpdate();

        //set last login
        $this->last_login = date('Y-m-d H:i:s');
    }

    /**
     * Validations
     */
    public function validation()
    {
        //email required
        $this->validate(new Email([
            "field"    => 'email',
            "required" => true,
            "message"  => $this->getModelMessage("email_required")
        ]));

        //email unique
        $this->validate(new Uniqueness([
            "field"   => "email",
            "message" => $this->getModelMessage("email_uniqueness")
        ]));

        //account flag
        $this->validate(new InclusionIn([
            "field"   => "account_flag",
            "domain"  => self::$ACCOUNT_FLAGS,
            "message" => 'Invalid user account flag. Flags supported: '.implode(", ", self::$ACCOUNT_FLAGS)
        ]));

        //check validations
        if ($this->validationHasFailed() == true)
            return false;
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
        if(!is_null($account_flag) && in_array($account_flag, self::$ACCOUNT_FLAGS)) {
            $bind[2] = $account_flag;
            $conditions .= " AND account_flag = ?2";
        }

        return self::findFirst([$conditions, "bind" => $bind]);
    }
}
