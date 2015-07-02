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

class BaseModelTickets extends BaseModel
{
    //this static method can be 'overrided' as late binding
    public static $CHECKOUT_MAX_NUMBER = 10;

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
     * @var float
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
        $this->skipAttributes( array('created_at') );
    }
    /** ------------------------------------------- § --------------------------------------------------
       Events
    ------------------------------------------------------------------------------------------------- **/
    public function afterFetch()
    {
        //hashed ticket id?
        if(isset($this->id_hashed))
            $this->id_hashed = $this->getDI()->get('cryptify')->encryptHashId($this->id_hashed);

        //format ticket price (custom prop)
        if(isset($this->price) && isset($this->coin))
            $this->_price_formatted = self::formatTicketPrice($this->price, $this->coin);
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

    /**
     * Formats price
     * @todo Complete other global coins formats
     * @static
     * @param numeric $price
     * @param string $coin
     * @return string
     */
    public static function formatTicketPrice($price, $coin)
    {
        $formatted = $price;

        switch ($coin) {
            case 'CLP':
                $formatted = "$".str_replace(".00", "", number_format($formatted));
                $formatted = str_replace(",", ".", $formatted);
                break;
            case 'USD':
                break;
            default:
                break;
        }

        return $formatted;
    }
}
