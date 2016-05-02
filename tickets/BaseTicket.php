<?php
/**
 * Base Model Tickets
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Tickets;

//imports
use Phalcon\Mvc\Model\Validator\InclusionIn;
//other imports
use CrazyCake\Helpers\Forms;

/**
 * Base Tickets Model
 */
class BaseTicket extends \CrazyCake\Models\Base
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
    public $currency;

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
    static $STATES = ["open", "closed", "soldout"];

    /**
     * Initializer
     */
    public function initialize()
    {
        //Skips fields/columns on both INSERT/UPDATE operations
        $this->skipAttributes(["created_at, _ext"]);
    }

    /**
     * After Fetch Event
     */
    public function afterFetch()
    {
        //extend properties
        $id_hashed = $this->getDI()->getShared("cryptify")->encryptHashId($this->id);

        $this->_ext = ["id_hashed" => $id_hashed];

        //format ticket price (custom prop)
        if(!is_null($this->price) && !is_null($this->currency))
            $this->_ext["price_formatted"] = Forms::formatPrice($this->price, $this->currency);
    }

    /**
     * Validation Event
     */
    public function validation()
    {
        $this->validate( new InclusionIn([
            "field"   => "state",
            "domain"  => self::$STATES,
            "message" => "Invalid state. States supported: ".implode(", ", self::$STATES)
         ]));

        //check validations
        if ($this->validationHasFailed() == true)
            return false;
    }
}