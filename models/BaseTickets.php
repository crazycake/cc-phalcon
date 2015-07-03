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
     * Gets object quantity and validates stock
     * @static
     * @param int $ticket_id The ticket id
     * @param int $q The amount needed
     * @param string $checkout_class If set, includes in validation UsersCheckouts items
     * @return boolean
     */
    public static function validateTicketStock($ticket_id = 0, $q = 0, $checkout_class = null)
    {
        $object = self::getObjectById($ticket_id);

        if(!$object)
            return 0;

        if(is_null($checkout_class))
            return ($object->quantity >= $q) ? true : false;

        if(!class_exists($checkout_class))
            throw new Exception("BaseTickets -> Checkout class not found ($checkout_class)");

        //get self class
        $object_class = static::who();
        //get checkout quantity
        $checkout_objects = $checkout_class::getObjectsByPhql(
           //phql
           "SELECT SUM(quantity) AS q
            FROM $checkout_class
            WHERE object_id = :object_id:
                AND object_class = :object_class:
            ",
           //binds
           array('object_id' => $ticket_id, "object_class" => $object_class)
       );
       //get sum quantity
       $checkout_q = $checkout_objects->getFirst()->q;

        if(is_null($checkout_q))
            $checkout_q = 0;

        //substract total
        $total = $object->quantity - $checkout_q;

        if($total <= 0)
            return false;

       return ($total > $q) ? true : false;
    }

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
