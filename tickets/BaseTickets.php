<?php
/**
 * Base Model Tickets
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Tickets;

//imports
use Phalcon\Mvc\Model\Validator\InclusionIn;
//other imports
use CrazyCake\Utils\FormHelper;

/**
 * Base Tickets Model
 */
class BaseTickets extends \CrazyCake\Models\Base
{
    /* properties */

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $small_print;

    /**
     * @var double
     */
    public $price;

    /**
     * @var string
     */
    public $coin;

    /**
     * @var int
     */
    public $quantity;

    /**
     * @var string
     */
    public $state;

    /**
     * @var string
     */
    public $created_at;

    /**
     * Extended properties
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
        $this->skipAttributes(['created_at, _ext']);
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
