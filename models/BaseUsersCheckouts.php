<?php
/**
 * Base Model Users Checkouts
 * Requires Criptify Util library
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Models;

//imports
use Phalcon\Exception;
use Phalcon\Mvc\Model\Validator\InclusionIn;

class BaseUsersCheckouts extends Base
{
    /* static vars */
    public static $DEFAULT_OBJECTS_CLASS = "UsersCheckoutsObjects";
    public static $BUY_ORDER_CODE_LENGTH = 16;

    /* properties */

    /*
     * @var string
     */
    public $buy_order;

    /**
     * @var int
     */
    public $user_id;

    /**
     * @var double
     */
    public $amount;

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
     * @static
     * @var array
     */
    static $STATES = array('pending', 'failed', 'success');

    /** -------------------------------------------- § -------------------------------------------------
        Init
    ------------------------------------------------------------------------------------------------- **/
    public function initialize()
    {
        //model relations
        $this->hasOne("user_id", "Users", "id");

        //Skips fields/columns on both INSERT/UPDATE operations
        $this->skipAttributes(array('created_at'));
    }
    /** -------------------------------------------------------------------------------------------------
        Validations
    ------------------------------------------------------------------------------------------------- **/
    public function validation()
    {
        //inclusion
        $this->validate( new InclusionIn(array(
            "field"   => "state",
            "domain"  => self::$STATES,
            "message" => 'Invalid state. States supported: '.implode(", ", self::$STATES)
         )));

        //check validations
        if ($this->validationHasFailed() == true)
            return false;
    }
    /** -------------------------------------------------------------------------------------------------
        Events
    ------------------------------------------------------------------------------------------------- **/
    public function afterFetch()
    {
        $this->id = $this->buy_order;
    }
    /** ---------------------------------------------------------------------------------------------- **/
    public function beforeValidationOnCreate()
    {
        //set default state
        $this->state = self::$STATES[0];
    }
    /** ------------------------------------------- § ------------------------------------------------ **/

    /**
     * Get a checkout object by buyOrder
     * @param  string $buy_order [description]
     * @return mixed [string|boolea]
     */
    public static function getCheckout($buy_order = "")
    {
        $conditions = "buy_order = ?1";
        $parameters = array(1 => $buy_order);

        return self::findFirst( array($conditions, "bind" => $parameters) );
    }

    /**
     * Generates a random code for a buy order
     * @param  string $phrase
     * @return string
     */
    public static function generateBuyOrder($length)
    {
        $di   = \Phalcon\DI::getDefault();
        $code = $di->getShared('cryptify')->generateAlphanumericCode($length);
        //unique constrait
        $exists = self::findFirst(array("buy_order = '$code'"));

        return $exists ? $this->generateBuyOrder($length) : $code;
    }

    /**
     * Creates a new buy order
     * @param  int $user_id The user id
     * @param  array $objects The objects to be saved
     * @param  double $amount The total checkout amount
     * @param  string $coin The amount coin
     * @return mixed [boolean|string] If success returns the buyOrder
     */
    public static function newBuyOrder($user_id = 0, $objects = array(), $amount = 0, $coin = "")
    {
        if(empty($user_id) || empty($objects))
            return false;

        //get DI reference (static)
        $di = \Phalcon\DI::getDefault();
        //get classes
        $checkoutModel = static::who();
        //get checkouts objects class
        $objectsModel = static::$DEFAULT_OBJECTS_CLASS;

        //generates buy order
        $buy_order = self::generateBuyOrder(static::$BUY_ORDER_CODE_LENGTH);

        //creates object
        $checkout = new $checkoutModel();
        $checkout->user_id   = $user_id;
        $checkout->buy_order = $buy_order;
        $checkout->amount    = $amount;
        $checkout->coin      = $coin;

        try {
            //begin trx
            $di->getShared('db')->begin();

            if(!$checkout->save())
                throw new Exception("A DB error ocurred saving in checkouts model.");

            //save each item (format: {class_objectId} : {q})
            foreach ($objects as $key => $q) {

                $props = explode("_", $key);
                //creates an object
                $checkoutObj = new $objectsModel();
                $checkoutObj->buy_order    = $buy_order;
                $checkoutObj->object_class = $props[0];
                $checkoutObj->object_id    = $props[1];
                //set quantity
                $checkoutObj->quantity = $q;

                if(!$checkoutObj->save())
                    throw new Exception("A DB error ocurred saving in checkoutsObjects model.");
            }

            //commit transaction
            $di->getShared('db')->commit();

            return $buy_order;
        }
        catch(Exception $e) {
            $di->getShared('logger')->error("BaseUsersCheckouts::newBuyOrder -> An error ocurred: ".$e->getMessage());
            $di->getShared('db')->rollback();
            return false;
        }
    }

    /**
     * Updates a checkout state
     * @param  string $buy_order The buy order
     * @param  string $state     The new state
     * @return boolean
     */
    public static function updateState($buy_order, $state)
    {
        $checkout = self::getCheckout($buy_order);

        //check object and default state
        if(!$checkout || $checkout->state != self::$STATES[0])
            return false;

        $checkout->update(array(
            "state" => $state
        ));

        return true;
    }

    /**
     * Validates that checkout object is already in stock.
     * Sums to q the number of checkout object presents in a pending checkout state.
     * @param  string $object_class The object class
     * @param  int $object_id The object id
     * @param  int $q The quantity to validate
     * @return boolean
     */
    public static function validateObjectStock($object_class = "", $object_id = 0, $q = 0)
    {
        if(!class_exists($object_class))
            throw new Exception("BaseUsersCheckouts -> Object class not found ($object_class)");

        $object = $object_class::getObjectById($object_id);

        if(!$object)
            return false;

        //get classes
        $checkoutModel = static::who();
        //get checkouts objects class
        $objectsModel = static::$DEFAULT_OBJECTS_CLASS;

        //get pending checkouts items quantity
        $objects = $checkoutModel::getObjectsByPhql(
           //phql
           "SELECT SUM(quantity) AS q
            FROM $objectsModel AS objects
            INNER JOIN $checkoutModel AS checkout ON checkout.buy_order = objects.buy_order
            WHERE objects.object_id = :object_id:
                AND objects.object_class = :object_class:
                AND checkout.state = 'pending'
            ",
           //bindings
           array('object_id' => $object_id, "object_class" => $object_class)
       );
       //get sum quantity
       $checkout_q = $objects->getFirst()->q;

        if(is_null($checkout_q))
            $checkout_q = 0;

        //substract total
        $total = $object->quantity - $checkout_q;
        //var_dump($total, $object->quantity, $checkout_q);exit;

        if($total <= 0)
            return false;

       return ($total > $q) ? true : false;
    }

    /**
     * Get checkout buyOrders by given objects
     * @param int $userId The User id
     * @param string $state The checkout state
     * @param array $objectIds An array with object IDs (required)
     * @param string $objectClass The object class name (required)
     * @return array
     */
    public static function getBuyOrdersByObjectsIds($user_id, $state = "success", $object_ids = array(), $object_class = "")
    {
        if(!class_exists($object_class))
            throw new Exception("BaseUsersCheckouts -> Object class not found ($object_class)");

        if(empty($object_ids))
            return array();

        //get classes
        $checkoutModel = static::who();
        //get checkouts objects class
        $objectsModel = static::$DEFAULT_OBJECTS_CLASS;

        $conditions = "";

        foreach ($object_ids as $key => $id)
            $object_ids[$key] = "objects.object_id = '$id'";

        $ids_filter = implode(" OR ", $object_ids);
        $conditions .= " AND (".$ids_filter.") ";

        //result
        $result = $checkoutModel::getObjectsByPhql(
           //phql
           "SELECT objects.buy_order
            FROM $objectsModel AS objects
            INNER JOIN $checkoutModel AS checkout ON checkout.buy_order = objects.buy_order
            WHERE checkout.user_id IS NOT NULL
                AND checkout.user_id = :user_id:
                AND checkout.state = :state:
                AND objects.object_class = :object_class:
                $conditions
            ",
           //bindings
           array("user_id" => $user_id, "state" => $state, "object_class" => $object_class)
       );

        if(!$result)
            return false;

        return BaseResultset::getIdsArray($result, "buy_order");
    }
}
