<?php
/**
 * Base Model Users Tickets
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Models;

//other imports
use CrazyCake\Utils\DateHelper;

class BaseUsersTickets extends Base
{
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

    /** ------------------------------------------- § --------------------------------------------------
        Init
    ------------------------------------------------------------------------------------------------- **/
    public function initialize()
    {
        //model relations
        if(isset($user_id)) {
            $this->hasOne("user_id",  "Users", "id");
        }

        //Skips fields/columns on both INSERT/UPDATE operations
        $this->skipAttributes( array('created_at') );
    }
    /** -------------------------------------------------------------------------------------------------
        Events (don't forget to call parent::method)
    ------------------------------------------------------------------------------------------------- **/
    public function beforeValidationOnCreate()
    {
        //set qr hash
        if(is_null($this->qr_hash))
            $this->qr_hash = $this->generateRandomHash(uniqid()); //param is like a seed

        //set alphanumeric code
        if(is_null($this->code))
            $this->code = $this->generateRandomCode();
    }
    /** ------------------------------------------- § ------------------------------------------------ **/

    /**
     * Get user ticket by code
     * @param  int $user_id The user id (optional)
     * @param  string  $code    The ticket code
     * @return object UserTicket
     */
    public static function getUserTicketByCode($user_id = 0, $code = "")
    {
        if(empty($user_id)) {
            $conditions = "code = ?1";
            $parameters = array(1 => $code);
        }
        else {
            $conditions = "user_id = ?1 AND code = ?2";
            $parameters = array(1 => $user_id, 2 => $code);
        }

        return self::findFirst(array($conditions, "bind" => $parameters));
    }

    /**
     * Generates a random Hash
     * @access protected
     * @param  string $phrase
     * @param  int $length
     * @return string
     */
    protected function generateRandomHash($phrase = "")
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
        $exists = self::findFirst(array(
            "qr_hash = '$hash'"
        ));

        return $exists ? $this->generateRandomHash($phrase) : $hash;
    }

    /**
     * Generates a random Code for a user-ticket
     * @access protected
     * @param  string $phrase
     * @return string
     */
    protected function generateRandomCode()
    {
        $length = static::$TICKET_CODE_LEGTH;

        $code = $this->getDI()->getShared('cryptify')->generateAlphanumericCode($length);
        //unique constrait
        $exists = self::getUserTicketByCode($this->user_id, $this->code);

        return $exists ? $this->generateRandomCode() : $code;
    }
}
