<?php
/**
 * Base Model Users Checkouts (Relational)
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Checkout;

use Phalcon\Exception;
use Phalcon\Validation;
use Phalcon\Validation\Validator\InclusionIn;

use CrazyCake\Helpers\Forms;

/**
 * Base User Checkouts
 */
class BaseOrmUserCheckout extends \CrazyCake\Models\BaseOrm
{
	/* static vars */

	/**
	 * Pending checkouts expiration threshold, in minutes.
	 */
	public static $CHECKOUT_EXPIRATION = 72; //hours

	/**
	 * Buy Order code length
	 */
	public static $CODE_LENGTH = 16;

	/**
	 * Form data prefix
	 */
	public static $FORM_DATA_PREFIX = "Checkout_";

	/**
	 * States possible values
	 */
	public static $STATES = ["pending", "failed", "overturn", "success"];

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
	 * Initializer
	 */
	public function initialize()
	{
		// get class
		$user_entity   = str_replace("Checkout", "", static::entity());
		$object_entity = static::entity()."Object";

		// model relations
		$this->hasOne("user_id", $user_entity, "id");

		if (class_exists("\\".$object_entity))
			$this->hasMany("buy_order", $object_entity, "buy_order");
	}

	/**
	 * After Fetch Event
	 */
	public function afterFetch()
	{
		// id is not relevant in the model meta data
		$this->id = $this->buy_order;
	}

	/**
	 * Before Validation Event [onCreate]
	 */
	public function beforeValidationOnCreate()
	{
		// set default state
		$this->state = static::$STATES[0];
		// set server local time
		$this->local_time = date("Y-m-d H:i:s");
	}

	/**
	 * Validation
	 */
	public function validation()
	{
		$validator = new Validation();

		// inclusion
		$validator->add("state", new InclusionIn([
			"domain"  => static::$STATES,
			"message" => "Invalid state, supported: ".implode(", ", static::$STATES)
		]));

		return $this->validate($validator);
	}
	/** ------------------------------------------- § ------------------------------------------------ **/

	/**
	 * Generates a random code for a buy order
	 * @param Int $length - The buy order string length
	 * @return String
	 */
	public static function newBuyOrderCode($length = null)
	{
		if (is_null($length))
			$length = static::$CODE_LENGTH;

		$code = (\Phalcon\DI::getDefault())->getShared("cryptify")->newAlphanumeric($length);
		// unique constrait
		$exists = self::findFirstByBuyOrder($code);

		return $exists ? self::newBuyOrderCode($length) : $code;
	}

	/**
	 * Creates a new buy order
	 * @param Object $checkout_obj -The checkout object
	 * @return Mixed - The checkout ORM object
	 */
	public static function newBuyOrder($checkout_obj)
	{
		// get DI reference (static)
		$di = \Phalcon\DI::getDefault();
		// get classes
		$entity        = "\\".static::entity();
		$object_entity = $entity."Object";

		// generates buy order
		$buy_order = self::newBuyOrderCode();
		$checkout_obj->buy_order = $buy_order;

		// log statement
		$di->getShared("logger")->debug("BaseOrmUserCheckout::newBuyOrder -> saving BuyOrder: $buy_order");

		try {

			// begin trx
			$di->getShared("db")->begin();

			$data = (array)$checkout_obj;
			unset($data["objects"], $data["objects_classes"]);

			$data["client"] = json_encode($data["client"]);
			// ~ss($data);

			// creates object with some checkout object props
			$checkout = new $entity();

			if (!$checkout->save($data))
				throw new Exception("A DB error ocurred inserting checkout object");

			// save each checkout object
			foreach ($checkout_obj->objects as $obj) {

				// creates an object
				$new_checkout_obj = new $object_entity();
				// props
				$props = (array)$obj;
				$props["buy_order"] = $buy_order;

				if (!$new_checkout_obj->save($props))
					throw new Exception("A DB error ocurred saving in checkoutsObjects model: ".$new_checkout_obj->messages(true));
			}

			// commit transaction
			$di->getShared("db")->commit();

			return $checkout;
		}
		catch (Exception $e) {

			$di->getShared("logger")->error("BaseOrmUserCheckout::newBuyOrder -> exception: ".$e->getMessage());
			$di->getShared("db")->rollback();
			return false;
		}
	}

	/**
	 * Get checkout by buy order (Relational)
	 * @param String $buy_order - Checkout buyOrder
	 * @return Object
	 */
	public static function getByBuyOrder($buy_order)
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
		// get checkouts objects class
		$entity        = "\\".static::entity();
		$object_entity = $entity."Object";

		$objects = $object_entity::find([
			"columns"    => "object_class, object_id, quantity",
			"conditions" => "buy_order = ?1",
			"bind"       => [1 => $buy_order]
		]);

		$result = [];

		// loop through objects
		foreach ($objects as $obj) {

			$object_class    = $obj->object_class;
			$checkout_object = (object)$obj->toArray();
			// get object local props
			$props = !class_exists($object_class) ?: $object_class::getById($obj->object_id);

			if (!$props)
				continue;

			// extend custom flexible properties
			$checkout_object->name     = $props->name ?? "";
			$checkout_object->price    = $props->price ?? 0;
			$checkout_object->currency = $props->currency ?? "CLP";

			// UI props
			$checkout_object->price_formatted = Forms::formatPrice($checkout_object->price, $checkout_object->currency);

			array_push($result, $checkout_object);
		}

		return $result;
	}

	/**
	 * Method: Parses checkout form objects & set new props by reference (validator & parser)
	 * @param Object $checkout - The checkout object
	 * @param Array $data - The received form data
	 */
	public static function parseFormObjects(&$checkout, $data = [])
	{
		$checkout->objects = [];
		$checkout->amount  = 0;

		$classes = [];
		$total_q = 0;

		// loop throught checkout items
		foreach ($data as $key => $q) {

			// parse properties
			$props = explode("_", $key);

			// validates checkout data has defined prefix
			if (strpos($key, static::$FORM_DATA_PREFIX) === false || count($props) != 3 || empty($q))
				continue;

			// get object props
			$object_class = $props[1];
			$object_id    = $props[2];

			// create object if class dont exists
			$object = class_exists($object_class) ? $object_class::getById($object_id) : null;

			// append object class
			if (!in_array($object_class, $classes))
				$classes[] = $object_class;

			// update total Q
			$total_q += $q;

			// update amount
			if (!empty($object->price))
				$checkout->amount += $q * $object->price;

			// create new checkout object without ORM props
			$checkout_object = (object)[
				"object_class" => $object_class,
				"object_id"    => $object_id,
				"quantity"     => $q,
			];

			// set item in array as string or plain object
			$checkout->objects[] = $checkout_object;
		}

		// set objects class name
		$checkout->objects_classes = $classes;
		// update total Q
		$checkout->total_q = $total_q;
	}

	/**
	 * Updates checkout state
	 * @param String $buy_order - Checkout buyOrder
	 * @param String $state - Input state
	 */
	public static function updateState($buy_order, $state)
	{
		$entity = "\\".static::entity();

		if (in_array($state, static::$STATES))
			$entity::updateProperty($buy_order, "state", $state, "buy_order");
	}

	/**
	 * Deletes expired pending checkouts.
	 * Requires Carbon library
	 * @return Int
	 */
	public static function deleteExpired()
	{
		try {

			if (!class_exists("\Carbon\Carbon"))
				throw new Exception("Carbon library class not found.");

			// use carbon library to handle time
			$now = (new \Carbon\Carbon())->subHours(static::$CHECKOUT_EXPIRATION);

			// get expired objects
			$conditions = "state = 'pending' AND local_time < ?1";
			$objects    = self::find([$conditions, "bind" => [1 => $now->toDateTimeString()]]);

			$count = 0;

			if ($objects) {

				$count = $objects->count();

				$objects->delete();
			}

			return $count;
		}
		catch (\Exception | Exception $e) { return 0; }
	}
}
