<?php
/**
 * Base Model Users Checkouts (Relational)
 * Requires Criptify Util library
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Checkout;

//imports
use Phalcon\Exception;
use Phalcon\Validation;
use Phalcon\Validation\Validator\InclusionIn;
//core
use CrazyCake\Phalcon\App;

/**
 * Base User Checkouts
 */
class BaseUserCheckout extends \CrazyCake\Models\Base
{
	/* static vars */

	/**
	 * Pending checkouts expiration threshold, in minutes.
	 * @static
	 * @var integer
	 */
	public static $CHECKOUT_EXPIRES_THRESHOLD = 72; //hours

	/**
	 * Buy Order code length
	 * @static
	 * @var integer
	 */
	public static $BUY_ORDER_CODE_LENGTH = 16;

	/* properties */

	/**
	 * Buy Order string
	 * @var string
	 */
	public $buy_order;

	/**
	 * User ID
	 * @var int
	 */
	public $user_id;

	/**
	 * Amount
	 * @var double
	 */
	public $amount;

	/**
	 * Currency [USD, CLP, ...]
	 * @var string
	 */
	public $currency;

	/**
	 * State
	 * @var string
	 */
	public $state;

	/**
	 * Gateway name
	 * @var string
	 */
	public $gateway;

	/**
	 * local server time
	 * @var string
	 */
	public $local_time;

	/**
	 * The browser client
	 * @var string
	 */
	public $client;

	/**
	 * States possible values
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
		$user_entity = App::getClass("user", false);

		$user_checkout_object_entity = App::getClass("user_checkout_object", false);

		//model relations
		$this->hasOne("user_id", $user_entity, "id");

		if(class_exists($user_checkout_object_entity))
			$this->hasMany("buy_order", $user_checkout_object_entity, "buy_order");
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
		$validator = new Validation();

		//inclusion
		$validator->add("state", new InclusionIn([
			"domain"  => self::$STATES,
			"message" => "Invalid state. States supported: ".implode(", ", self::$STATES)
		]));

		return $this->validate($validator);
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

		return $exists ? self::newBuyOrderCode($length) : $code;
	}

	/**
	 * Creates a new buy order
	 * @param object $checkout_obj -The checkout object
	 * @return mixed [object] - The checkout ORM object
	 */
	public static function newBuyOrder($checkout_obj = null)
	{
		if (is_null($checkout_obj))
			return false;

		//get DI reference (static)
		$di = \Phalcon\DI::getDefault();
		//get classes
		$checkout_entity = static::entity();
		//get checkouts objects class
		$checkout_object_entity = App::getClass("user_checkout_object");

		//generates buy order
		$buy_order = self::newBuyOrderCode();
		$checkout_obj->buy_order = $buy_order;

		//log statement
		$di->getShared("logger")->debug("BaseUserCheckout::newBuyOrder -> Saving BuyOrder: $buy_order");

		try {

			//creates object with some checkout object props
			$checkout = new $checkout_entity();

			//begin trx
			$di->getShared("db")->begin();

			//implode sub-arrays
			$checkout_data = (array)$checkout_obj;
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
			foreach ($checkout_obj->objects as $obj) {

				//creates an object
				$new_checkout_obj = new $checkout_object_entity();
				//props
				$props = (array)$obj;
				$props["buy_order"] = $buy_order;

				if (!$new_checkout_obj->save($props))
					throw new Exception("A DB error ocurred saving in checkoutsObjects model: ".$new_checkout_obj->messages(true));
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
	 * Deletes expired pending checkouts.
	 * Requires Carbon library
	 * @return int
	 */
	public static function deleteExpired()
	{
		try {

			if(!class_exists("\Carbon\Carbon"))
				throw new Exception("Carbon library class not found.");

			//use carbon library to handle time
			$now = new \Carbon\Carbon();
			//substract time
			$now->subHours(static::$CHECKOUT_EXPIRES_THRESHOLD);
			//s($now->toDateTimeString());exit;

			//get expired objects
			$conditions = "state = 'pending' AND local_time < ?1";
			$binding    = [1 => $now->toDateTimeString()];
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

			return 0;
		}
	}
}
