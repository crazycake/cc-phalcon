<?php
/**
 * Base Users Tickets Model
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Models;

//other imports
use CrazyCake\Utils\DateHelper;

abstract class BaseUsersTicketsModel extends BaseModel
{
    //this static methods can be 'overrided' as late binding
    public static $TOKEN_EXPIRES_THRESHOLD = 2; //days
    public static $TICKET_ALPHA_CODE_LEGTH = 10;

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
    public $flag;

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
        $this->hasOne("user_id",  "Users", "id");

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
            $this->qr_hash = $this->generateRandomHash(uniqid());

        //set alphanumeric code
        if(is_null($this->code))
            $this->code = $this->generateRandomCode(self::$TICKET_ALPHA_CODE_LEGTH);
    }

    /** ------------------------------------------- § ------------------------------------------------ **/

    /**
     * Generates a random Hash
     * @access protected
     * @param  string $phrase
     * @param  int $length
     * @return string
     */
    protected function generateRandomHash($phrase = "", $length = 50)
    {
        $code = "";
        $p    = 0;

        for ($k = 1; $k <= $length; $k++) {
            $num  = chr(rand(48, 57));
            $char = chr(rand(97, 122));
            //append string
            $code .= (rand(1, 2) == 1) ? $num : $char;
        }

        //make sure hash is always different
        return sha1($code.microtime().$phrase);
    }

    /**
     * Generates a random Code for a ticket
     * @access protected
     * @param  string $phrase
     * @param  int $length
     * @return string
     */
    protected function generateRandomCode($length = 8)
    {
        $code = "";
        //exclude some chars for legible strings
        $excluded_nums  = array("0", "2", "5");
		$excluded_chars = array("O", "Z", "S", "I", "V");

        for ($k = 1; $k <= $length; $k++) {

            $num  = chr(rand(48, 57));
            $char = strtoupper(chr(rand(97, 122)));
            $p 	  = rand(1,2);

            if(in_array($num, $excluded_nums))
				$num  = ($p == 1) ? "4" : "G";

            if(in_array($char, $excluded_chars))
                $char = ($p == 1) ? "C" : "7";

            //append string
            $code .= ($p == 1) ? $num : $char;
        }

        //unique constrait
        $exists = self::findFirst(array(
            "code = '".$code."'"
        ));;

        return $exists ? $this->generateRandomCode($length) : $code;
    }
}
