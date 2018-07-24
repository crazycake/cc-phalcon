<?php
/**
 * Base Model User Checkout (Mongo)
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Checkout;

use Phalcon\Exception;
use CrazyCake\Helpers\Forms;

/**
 * Base User Checkouts
 */
class BaseUserCheckoutDocument extends \CrazyCake\Models\BaseDocument
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
	public static $CODE_LENGTH = 16;

	/* properties */

	/**
	 * States possible values
	 * @var Array
	 */
	public static $STATES = ["pending", "failed", "overturn", "success"];


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

		$exists = self::getByProperties(["code" => $code]);

		return $exists ? self::newBuyOrderCode($length) : $code;
	}

	/**
	 * Creates a new buy order
	 * @param Object $checkout -The checkout object
	 * @return Mixed - The checkout ORM object
	 */
	public static function newBuyOrder($checkout)
	{
		// get DI reference (static)
		$di = \Phalcon\DI::getDefault();

		// generates buy order
		$checkout->buyOrder = self::newBuyOrderCode();
		$checkout->state    = "pending";

		// log statement
		$di->getShared("logger")->debug("BaseUserCheckout::newBuyOrder -> saving BuyOrder: $checkout->buyOrder");

		try {
			// insert
			if (!$checkout = self::insert($checkout))
				throw new Exception("A DB error ocurred inserting checkout object.");

			return $checkout;
		}
		catch (Exception $e) {

			$di->getShared("logger")->error("BaseUserCheckout::newBuyOrder -> exception: ".$e->getMessage());
			return false;
		}
	}

	/**
	 * Get checkout by buy order (Relational)
	 * @param String $buyOrder - Checkout buyOrder
	 * @return Object
	 */
	public static function getByBuyOrder($buyOrder)
	{
		return self::getByProperties(["buyOrder" => $buyOrder]);
	}

	/**
	 * Updates checkout state
	 * @param String $buyOrder - Checkout buyOrder
	 * @param String $state - Input state
	 */
	public static function updateState($buyOrder, $state)
	{
		$mongo = (\Phalcon\DI::getDefault())->getShared("mongo");

		if (in_array($state, static::$STATES))
			$mongo->{static::$COLLECTION}->updateOne(["buyOrder" => $buyOrder], ['$set' => ["state" => $state]]);
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

			$mongo = (\Phalcon\DI::getDefault())->getShared("mongo");

			// use carbon library to handle time
			$now = (new \Carbon\Carbon())->subHours(static::$CHECKOUT_EXPIRATION);

			// delete action
			$mongo->{static::$COLLECTION}->deleteOne(["state" => "pending", "local_time" => ['$lt' => self::toIsoDate($now)]]);

			return $result;
		}
		catch (\Exception | Exception $e) { return 0; }
	}
}
