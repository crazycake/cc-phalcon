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

use CrazyCake\Phalcon\App;
use CrazyCake\Helpers\Forms;

/**
 * Base User Checkouts
 */
class BaseUserCheckout extends \CrazyCake\Models\Base
{
	/* static vars */

	/**
	 * Pending checkouts expiration threshold, in minutes.
	 * @var Integer
	 */
	public static $CHECKOUT_EXPIRATION = 72; //hours

	/**
	 * Buy Order code length
	 * @var Integer
	 */
	public static $BUY_ORDER_CODE_LENGTH = 16;

	/* properties */

	/**
	 * Buy Order string
	 * @var String
	 */
	public $buy_order;

	/**
	 * User ID
	 * @var Int
	 */
	public $user_id;

	/**
	 * Amount
	 * @var Float
	 */
	public $amount;

	/**
	 * Currency [USD, CLP]
	 * @var String
	 */
	public $currency;

	/**
	 * State
	 * @var String
	 */
	public $state;

	/**
	 * Gateway name
	 * @var String
	 */
	public $gateway;

	/**
	 * local server time
	 * @var String
	 */
	public $local_time;

	/**
	 * The browser client
	 * @var String
	 */
	public $client;

	/**
	 * States possible values
	 * @var Array
	 */
	static $STATES = ["pending", "failed", "overturn", "success"];

	/**
	 * Initializer
	 */
	public function initialize()
	{
		//get class
		$user_entity   = str_replace("Checkout", "", static::entity());
		$object_entity = static::entity()."Object";

		//model relations
		$this->hasOne("user_id", $user_entity, "id");

		if(class_exists(App::getClass($object_entity))
			$this->hasMany("buy_order", $object_entity, "buy_order");
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
			"message" => "Invalid state, supported: ".implode(", ", self::$STATES)
		]));

		return $this->validate($validator);
	}
	/** ------------------------------------------- § ------------------------------------------------ **/

	/**
	 * Get the last user checkout
	 * @param Int $user_id - The User ID
	 * @param String $state - The checkout state property
	 * @return Mixed
	 */
	public static function getLast($user_id = 0, $state = "pending")
	{
		$conditions = "user_id = ?1 AND state = ?2";
		$binding    = [1 => $user_id, 2 => $state];

		return self::findFirst([$conditions, "bind" => $binding, "order" => "local_time DESC"]);
	}

	/**
	 * Generates a random code for a buy order
	 * @param Int $length - The buy order string length
	 * @return String
	 */
	public static function newBuyOrderCode($length = null)
	{
		if(is_null($length))
			$length = static::$BUY_ORDER_CODE_LENGTH;

		$code = (\Phalcon\DI::getDefault())->getShared("cryptify")->newAlphanumeric($length);
		//unique constrait
		$exists = self::findFirstByBuyOrder($code);

		return $exists ? self::newBuyOrderCode($length) : $code;
	}

	/**
	 * Creates a new buy order
	 * @param Object $checkout_obj -The checkout object
	 * @return Mixed - The checkout ORM object
	 */
	public static function newBuyOrder($checkout_obj = null)
	{
		if (is_null($checkout_obj))
			return false;

		//get DI reference (static)
		$di = \Phalcon\DI::getDefault();
		//get classes
		$entity        = App::getClass(static::entity());
		$object_entity = $entity."Object";

		//generates buy order
		$buy_order = self::newBuyOrderCode();
		$checkout_obj->buy_order = $buy_order;

		//log statement
		$di->getShared("logger")->debug("BaseUserCheckout::newBuyOrder -> saving BuyOrder: $buy_order");

		try {

			//creates object with some checkout object props
			$checkout = new $entity();

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

			if (!$checkout->save($checkout_data))
				throw new Exception("A DB error ocurred saving in checkouts model.");

			//save each checkout object
			foreach ($checkout_obj->objects as $obj) {

				//creates an object
				$new_checkout_obj = new $object_entity();
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

			$di->getShared("logger")->error("BaseUserCheckout::newBuyOrder -> exception: ".$e->getMessage());
			$di->getShared("db")->rollback();
			return false;
		}
	}

	/**
	 * Get checkout by buy order (Relational)
	 * @param String $buy_order - Checkout buyOrder
	 * @return Object
	 */
	public static getByBuyOrder($buy_order)
	{
		return self::findFirstByBuyOrder($buy_order);
	}

	/**
	 * Get checkout objects (Relational)
	 * @param String $buy_order - Checkout buyOrder
	 * @return Array
	 */
	public static function getObjects($buy_order = "")
	{
		//get checkouts objects class
		$entity        = App::getClass(static::entity());
		$object_entity = $entity."Object";

		$objects = $object_entity::find([
			"columns"    => "object_class, object_id, quantity",
			"conditions" => "buy_order = ?1",
			"bind"       => [1 => $buy_order]
		]);

		$result = [];

		//loop through objects
		foreach ($objects as $obj) {

			$object_class = $obj->object_class;
			//create a new object and clone common props
			$checkout_object = (object)$obj->toArray();
			//get object local props
			$props = !class_exists($object_class) ?: $object_class::getById($obj->object_id);

			if (!$props) continue;

			//extend custom flexible properties
			$checkout_object->name     = $props->name ?? "";
			$checkout_object->price    = $props->price ?? 0;
			$checkout_object->currency = $props->currency ?? "CLP";

			//UI props
			$checkout_object->price_formatted = Forms::formatPrice($checkout_object->price, $checkout_object->currency);

			array_push($result, $checkout_object);
		}

		return $result;
	}

	/**
	 * Deletes expired pending checkouts.
	 * Requires Carbon library
	 * @return Int
	 */
	public static function deleteExpired()
	{
		try {

			if(!class_exists("\Carbon\Carbon"))
				throw new Exception("Carbon library class not found.");

			//use carbon library to handle time
			$now = new \Carbon\Carbon();
			//substract time
			$now->subHours(static::$CHECKOUT_EXPIRATION);
			//s($now->toDateTimeString());

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
		catch (\Exception | Exception $e) {

			return 0;
		}
	}
}
