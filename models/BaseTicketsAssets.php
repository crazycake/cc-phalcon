<?php
/**
 * Base Model Tickets
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Models;

//imports
use Phalcon\Mvc\Model\Validator\InclusionIn;
//other imports
use CrazyCake\Utils\FormHelper;

class BaseTicketsAssets extends Base
{
    /* properties */

    /**
    * @var int
    */
    public $ticket_id;

    /**
    * @var int
    */
    public $default_quantity;

    /**
    * @var int
    */
    public $user_max_quantity;

    /**
    * @var int
    */
    public $quantity;

    /**
    * @var double
    */
    public $price;

    /**
    * @var string
    */
    public $coin;

    /**
    * @var string
    */
    public $state;

    /**
    * @var string
    */
    public $created_at;

    /**
    * @var array
    */
    public $_ext;

    /* inclusion vars */

    /**
     * @static
     * @var array
     */
    static $STATES = array('open', 'closed', 'soldout');

    /** ------------------------------------------- § --------------------------------------------------
        Init
    ------------------------------------------------------------------------------------------------- **/
    public function initialize()
    {
        //Skips fields/columns on both INSERT/UPDATE operations
        $this->skipAttributes(['created_at', '_ext']);
    }
    /** ------------------------------------------- § --------------------------------------------------
       Events
    ------------------------------------------------------------------------------------------------- **/
    public function afterFetch()
    {
        //extend properties
        $id_hashed = $this->getDI()->getShared('cryptify')->encryptHashId($this->id);

        $this->_ext = ["id_hashed" => $id_hashed];

        //format ticket price (custom prop)
        if(!is_null($this->price) && !is_null($this->coin))
            $this->_ext["price_formatted"] = FormHelper::formatPrice($this->price, $this->coin);
    }
    /** -------------------------------------------------------------------------------------------------
        Validations
    ------------------------------------------------------------------------------------------------- **/
    public function validation()
    {
        $this->validate( new InclusionIn([
            "field"   => "state",
            "domain"  => self::$STATES,
            "message" => 'Invalid state. States supported: '.implode(", ", self::$STATES)
         ]));

        //check validations
        if ($this->validationHasFailed() == true)
            return false;
    }
    /** ------------------------------------------- § ------------------------------------------------ **/

}
