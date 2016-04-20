<?php
/**
 * Base Model Users Tickets
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Tickets;

/**
 * Base User Tickets Model
 */
class BaseUserTicket extends \CrazyCake\Models\Base
{
    /* static vars */

    //this static methods can be 'overrided' as late binding
    public static $QR_CODE_LEGTH     = 40;
    public static $TICKET_CODE_LEGTH = 10;

    /* properties */

    /**
     * @var int
     */
    public $user_id;

    /**
     * @var int
     */
    public $ticket_id;

    /**
     * @var string
     */
    public $code;

    /**
     * @var string
     */
    public $qr_hash;

    /**
     * @var string
     */
    public $created_at;

    /**
     * Extended properties
     */
    public $_ext;

    /**
     * Initializer
     */
    public function initialize()
    {
        //get class
        $users_class = \CrazyCake\Core\AppCore::getModuleClass("user", false);
        //model relations
        $this->hasOne("user_id", $users_class, "id");

        //Skips fields/columns on both INSERT/UPDATE operations
        $this->skipAttributes(['_ext']);
    }

    /**
     * Before Validation Event [onCreate]
     */
    public function beforeValidationOnCreate()
    {
        //set qr hash
        if(is_null($this->qr_hash))
            $this->qr_hash = $this->newHash(uniqid()); //param is like a seed

        //set alphanumeric code
        if(is_null($this->code))
            $this->code = $this->newCode();

        //set created at
        $this->created_at = date("Y-m-d H:i:s");
    }

    /**
     * After Fetch Event
     */
    public function afterFetch()
    {
        //extend properties
        $id_hashed = $this->getDI()->getShared('cryptify')->encryptHashId($this->id);

        $this->_ext = ["id_hashed" => $id_hashed];
    }

    /** ------------------------------------------- § ------------------------------------------------ **/

    /**
     * Get tickets by a array of IDs
     * @static
     * @param array $record_ids - An array of IDs
     * @param boolean $as_array - Flag to return resultset as array
     * @return mixed [boolean|array]
     */
    public static function getByIds($record_ids = array(), $as_array = false)
    {
        if(empty($record_ids))
            return false;

        //filter by ids
        $ids_filter = "";

        foreach ($record_ids as $key => $id)
            $record_ids[$key] = "id = '$id'";

        $ids_filter = implode(" OR ", $record_ids);

        $objects = self::find($ids_filter);

        if(!$objects)
            return false;

        return $as_array ? $objects->toArray() : $objects;
    }

    /**
     * Get user ticket by code
     * @param string $code - The ticket code
     * @param int $user_id - The user ID (optional)
     * @return object UserTicket
     */
    public static function getByCodeAndUserId($code = "", $user_id = 0)
    {
        if(empty($user_id)) {
            $conditions = "code = ?1";
            $parameters = [1 => $code];
        }
        else {
            $conditions = "user_id = ?1 AND code = ?2";
            $parameters = [1 => $user_id, 2 => $code];
        }

        return self::findFirst([$conditions, "bind" => $parameters]);
    }

    /**
     * Get user ticket by qr hash
     * @param string $qr_hash - The QR hash code
     * @return mixed [boolean|object]
     */
    public static function getUserByQrHash($qr_hash = "")
    {
        $ticket = self::findFirstByQrHash($qr_hash);

        if(!$ticket || !isset($ticket->user_id))
            return false;

        //return user object
        $users_class = \CrazyCake\Core\AppCore::getModuleClass("user", false);

        return $users_class::getById($ticket->user_id);
    }

    /**
     * Generates a random Hash
     * @access protected
     * @param  string $phrase - A text phrase
     * @return string
     */
    protected function newHash($phrase = "")
    {
        $length = static::$QR_CODE_LEGTH;
        $code   = "";
        $p      = 0;

        for ($k = 1; $k <= $length; $k++) {

            $num  = chr(rand(48, 57));
            $char = chr(rand(97, 122));
            //append string
            $code .= (rand(1, 2) == 1) ? $num : $char;
        }

        //make sure hash is always different
        $hash = sha1($code.microtime().$phrase);
        $hash = substr(str_shuffle($hash), 0, $length);

        //unique constrait
        $exists = self::findFirst(["qr_hash = '$hash'"]);

        return $exists ? $this->newHash($phrase) : $hash;
    }

    /**
     * Generates a random Code for a user-ticket
     * @access protected
     * @return string
     */
    protected function newCode()
    {
        $code = $this->getDI()->getShared('cryptify')
                              ->newAlphanumeric(static::$TICKET_CODE_LEGTH);
        //unique constrait
        $user_id = isset($this->user_id) ? $this->user_id : 0;
        $exists  = self::getByCodeAndUserId($code, $user_id);

        return $exists ? $this->newCode() : $code;
    }
}
