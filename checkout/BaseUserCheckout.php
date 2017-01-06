<?php
/**
 * Base Model Users Checkouts
 * Requires Criptify Util library
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Checkout;

//imports
use Phalcon\Exception;
use Phalcon\Mvc\Model\Validator\InclusionIn;
//core
use CrazyCake\Phalcon\App;

/**
 * Base User Checkouts
 */
class BaseUserCheckout extends \CrazyCake\Models\Base
{
    /* static vars */
    public static $CHECKOUT_EXPIRES_THRESHOLD = 10;  //minutes
    public static $BUY_ORDER_CODE_LENGTH      = 16;

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
    public $currency;

    /**
     * @var string
     */
    public $state;

    /*
     * @var string
     */
    public $gateway;

    /**
     * @var string
     */
    public $local_time;

    /**
     * @var string
     * The browser client
     */
    public $client;

    /**
     * @static
     * @var array
     */
    static $STATES = ["pending", "failed", "overturn", "success"];

    /**
     * Initializer
     */
    public function initialize()
    {
        //get class
        $user_class = App::getClass("user", false);

        $user_checkout_object_class = App::getClass("user_checkout_object", false);

        //model relations
        $this->hasOne("user_id", $user_class, "id");
        $this->hasMany("buy_order", $user_checkout_object_class, "buy_order");
    }

    /**
     * After Fetch Event
     */
    public function afterFetch()
    {
        //id is not relevant in the model meta data
        $this->id = $this->buy_order;
    }

    /**
     * Before Validation Event [onCreate]
     */
    public function beforeValidationOnCreate()
    {
        //set default state
        $this->state = self::$STATES[0];
        //set server local time
        $this->local_time = date("Y-m-d H:i:s");
    }

    /**
     * Validation
     */
    public function validation()
    {
        //inclusion
        $this->validate(new InclusionIn([
            "field"   => "state",
            "domain"  => self::$STATES,
            "message" => "Invalid state. States supported: ".implode(", ", self::$STATES)
        ]));

        //check validations
        if ($this->validationHasFailed() == true)
            return false;
    }
    /** ------------------------------------------- § ------------------------------------------------ **/

    /**
     * Get the last user checkout
     * @param  int $user_id - The User ID
     * @param  string $state - The checkout state property
     * @return mixed [string|object]
     */
    public static function getLast($user_id = 0, $state = "pending")
    {
        $conditions = "user_id = ?1 AND state = ?2";
        $binding    = [1 => $user_id, 2 => $state];

        return self::findFirst([$conditions, "bind" => $binding, "order" => "local_time DESC"]);
    }

    /**
     * Generates a random code for a buy order
     * @param int $length - The buy order string length
     * @return string
     */
    public static function newBuyOrderCode($length = null)
    {
		if(is_null($length))
			$length = static::$BUY_ORDER_CODE_LENGTH;

        $di   = \Phalcon\DI::getDefault();
        $code = $di->getShared("cryptify")->newAlphanumeric($length);
        //unique constrait
        $exists = self::findFirstByBuyOrder($code);

        return $exists ? $this->newBuyOrderCode($length) : $code;
    }

    /**
     * Creates a new buy order
     * @param object $checkoutObj -The checkout object
     * @return mixed [object] - The checkout ORM object
     */
    public static function newBuyOrder($checkoutObj = null)
    {
        if (is_null($checkoutObj))
            return false;

        //get DI reference (static)
        $di = \Phalcon\DI::getDefault();
        //get classes
        $checkout_class_name = static::who();
        //get checkouts objects class
        $checkout_object_class_name = App::getClass("user_checkout_object");

        //generates buy order
        $buy_order = self::newBuyOrderCode();
        $checkoutObj->buy_order = $buy_order;

        //log statement
        $di->getShared("logger")->debug("BaseUserCheckout::newBuyOrder -> Saving BuyOrder: $buy_order");

        try {

            //creates object with some checkout object props
            $checkout = new $checkout_class_name();

            //begin trx
            $di->getShared("db")->begin();

            //implode sub-arrays
            $checkout_data = (array)$checkoutObj;
            //unset checkout objects
            unset($checkout_data["objects"]);

            foreach ($checkout_data as $key => $value) {

                if(is_array($value))
                    $checkout_data[$key] = implode(",", $value);
            }
            //sd($checkout_data);

            if (!$checkout->save($checkout_data))
                throw new Exception("A DB error ocurred saving in checkouts model.");

            //save each checkout object
            foreach ($checkoutObj->objects as $obj) {

                //creates an object
                $checkoutObj = new $checkout_object_class_name();
                //props
                $props = (array)$obj;
                $props["buy_order"] = $buy_order;

                if (!$checkoutObj->save($props))
                    throw new Exception("A DB error ocurred saving in checkoutsObjects model.");
            }

            //commit transaction
            $di->getShared("db")->commit();

            return $checkout;
        }
        catch (Exception $e) {
            $di->getShared("logger")->error("BaseUserCheckout::newBuyOrder -> An error ocurred: ".$e->getMessage());
            $di->getShared("db")->rollback();
            return false;
        }
    }

    /**
     * Deletes expired pending checkouts
     * Requires Carbon library
     * @return int
     */
    public static function deleteExpired()
    {
        try {
            //use carbon library to handle time
            $now = new \Carbon\Carbon();
            //substract time
            $now->subMinutes(static::$CHECKOUT_EXPIRES_THRESHOLD);
            //s($now->toDateTimeString());exit;

            //get expired objects
            $conditions = "state = ?1 AND local_time < ?2";
            $binding    = [1 => "pending", 2 => $now->toDateTimeString()];
            //query
            $objects = self::find([$conditions, "bind" => $binding]);

            $count = 0;

            if ($objects) {
                //set count
                $count = $objects->count();
                //delete action
                $objects->delete();
            }

            //delete expired objects
            return $count;
        }
        catch (Exception $e) {
            //throw new Exception("BaseUserCheckout::deleteExpired -> error: ".$e->getMessage());
            return 0;
        }
    }
}
