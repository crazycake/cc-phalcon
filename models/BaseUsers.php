<?php
/**
 * Base Model Users
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Models;

//imports
use Phalcon\Mvc\Model\Validator\Email;
use Phalcon\Mvc\Model\Validator\InclusionIn;
use Phalcon\Mvc\Model\Validator\Uniqueness;

abstract class BaseUsers extends Base
{
    /**
     * child required methods
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
    static $ACCOUNT_FLAGS = array('pending', 'enabled', 'disabled');

    /** ------------------------------------------- § --------------------------------------------------
        Init
    ------------------------------------------------------------------------------------------------- **/
    public function initialize()
    {
        //Skips fields/columns on both INSERT/UPDATE operations
        $this->skipAttributes(array('created_at'));
    }
    /** -------------------------------------------------------------------------------------------------
        Events
    ------------------------------------------------------------------------------------------------- **/
    public function afterFetch()
    {
        //hashed ticket id?
        if(isset($this->id))
            $this->id_hashed = $this->getDI()->getShared('cryptify')->encryptHashId($this->id);
    }
    /** ---------------------------------------------------------------------------------------------- **/
    public function beforeValidationOnCreate()
    {
        //set password hash
        if(!is_null($this->pass))
            $this->pass = $this->getDI()->getShared('security')->hash( $this->pass );

        //set last login
        $this->last_login = date('Y-m-d H:i:s');
    }
    /** ---------------------------------------------------------------------------------------------- **/
    public function beforeValidationOnUpdate()
    {
        //set last login
        $this->last_login = date('Y-m-d H:i:s');
    }
    /** ---------------------------------------------------------------------------------------------- **/
    public function onValidationFails()
    {
        //...
    }
    /** ---------------------------------------------------------------------------------------------- **/
    public function notSave()
    {
        //...
    }
    /** -------------------------------------------------------------------------------------------------
        Validations
    ------------------------------------------------------------------------------------------------- **/
    public function validation()
    {
        //email required
        $this->validate(new Email(array(
            'field'    => 'email',
            'required' => true
        )));

        //email unique
        $this->validate(new Uniqueness(array(
            "field"   => "email",
            "message" => $this->getModelMessage("email_uniqueness")
        )));

        //account flag
        $this->validate(new InclusionIn(array(
            "field"   => "account_flag",
            "domain"  => self::$ACCOUNT_FLAGS,
            "message" => 'Invalid user account flag. Flags supported: '.implode(", ", self::$ACCOUNT_FLAGS)
        )));

        //check validations
        if ($this->validationHasFailed() == true)
            return false;
    }
    /** ------------------------------------------- § --------------------------------------------------  **/

    /**
     * Find User by email
     * @static
     * @param string $email The user email
     * @param string $account_flag The account flag value in self defined array
     * @return Users
     */
    public static function getUserByEmail($email, $account_flag = null)
    {
        $conditions = array("email = '".$email."'"); //default condition

        //filter by account flag?
        if(!is_null($account_flag) && in_array($account_flag, self::$ACCOUNT_FLAGS))
            array_push($conditions, "account_flag = '".$account_flag."'");

        //join conditions (AND)
        $conditions = implode(" AND ", $conditions);

        return self::findFirst( array("conditions" => $conditions) );
    }

    /**
     * Validates if a namespace exists
     * @static
     * @param string $namespace
     * @return boolean
     */
    public static function validateNamespaceExists($namespace = "")
    {
        $user = self::findFirst("namespace = '".$namespace."'");

        return $user ? true : false;
    }
}
