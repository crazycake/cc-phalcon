<?php
/**
 * Base Model Tickets
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Models;

//imports
use Phalcon\Mvc\Model\Validator\InclusionIn;
//other imports
use CrazyCake\Utils\DateHelper;
use CrazyCake\Utils\FormHelper;

class BaseTickets extends Base
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
        $this->skipAttributes(array('created_at'));
    }
    /** ------------------------------------------- § --------------------------------------------------
       Events
    ------------------------------------------------------------------------------------------------- **/
    public function afterFetch()
    {
        //set class name used in UI
        $this->class_name = static::who();

        //set hashed id
        $this->id_hashed = $this->getDI()->getShared('cryptify')->encryptHashId($this->id);

        //format ticket price (custom prop)
        if(isset($this->price) && isset($this->coin))
            $this->_price_formatted = FormHelper::formatPrice($this->price, $this->coin);
    }
    /** -------------------------------------------------------------------------------------------------
        Validations
    ------------------------------------------------------------------------------------------------- **/
    public function validation()
    {
        $this->validate( new InclusionIn(array(
            "field"   => "state",
            "domain"  => self::$STATES,
            "message" => 'Invalid state. States supported: '.implode(", ", self::$STATES)
         )));

        //check validations
        if ($this->validationHasFailed() == true)
            return false;
    }
    /** ------------------------------------------- § ------------------------------------------------ **/

}
