<?php
/**
 * Base Model Users Checkouts Objects
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Checkout;

//imports
use CrazyCake\Phalcon\App;
use CrazyCake\Helpers\Forms;

/**
 * Base User Checkouts objects
 */
class BaseUserCheckoutObject extends \CrazyCake\Models\Base
{
	/* properties */

	/**
	 * Buy order
	 * @var string
	 */
	public $buy_order;

	/**
	 * Object class
	 * @var string
	 */
	public $object_class;

	/**
	 * Object ID
	 * @var string
	 */
	public $object_id;

	/**
	 * Quantity
	 * @var int
	 */
	public $quantity;

	/**
	 * Initializer
	 */
	public function initialize()
	{
		//set relation
		$this->hasOne("object_id", $this->object_class, "id", ["alias" => "rel"]);
	}

	/** ------------------------------------------- § ------------------------------------------------ **/

	/**
	 * Get checkout objects
	 * @param  string $buy_order - Checkout buyOrder
	 * @return array
	 */
	public static function getCollection($buy_order = "")
	{
		$objects = self::find([
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
			$props = !class_exists($object_class) ?: $object_class::findFirstById($obj->object_id);

			if (!$props) continue;

			//extend custom flexible properties
			$checkout_object->name     = isset($props->name) ? $props->name : $props->_ext["name"];
			$checkout_object->price    = $props->price;
			$checkout_object->currency = $props->currency;

			//UI props
			$checkout_object->price_formatted = Forms::formatPrice($props->price, $props->currency);

			array_push($result, $checkout_object);
		}

	   return $result;
	}

	/**
	 * Validates that checkout object is already in stock.
	 * Sums to q the number of checkout object presents in a pending checkout state.
	 * @param string $object_class - The object class
	 * @param int $object_id - The object id
	 * @param int $q - The quantity to validate
	 * @return boolean
	 */
	public static function validateStock($object_class = "", $object_id = 0, $q = 0)
	{
		if (!class_exists($object_class))
			throw new Exception("BaseUserCheckoutObject -> Object class not found ($object_class)");

		$object = $object_class::getById($object_id);

		if (!$object)
			return false;

		//get classes
		$user_checkout_class = App::getClass("user_checkout");
		//get checkouts objects class
		$class_model = static::who();

		//get pending checkouts items quantity
		$objects = $user_checkout_class::getByPhql(
		   //phql
		   "SELECT SUM(quantity) AS q
			FROM $class_model AS objects
			INNER JOIN $user_checkout_class AS checkout ON checkout.buy_order = objects.buy_order
			WHERE objects.object_id = :object_id:
				AND objects.object_class = :object_class:
				AND checkout.state = 'pending'
			",
		   //bindings
		   ["object_id" => $object_id, "object_class" => $object_class]
	   );
	   //get sum quantity
	   $checkout_q = $objects->getFirst()->q;

		if (is_null($checkout_q))
			$checkout_q = 0;

		//substract total
		$total = $object->quantity - $checkout_q;
		//var_dump($total, $object->quantity, $checkout_q, $total);exit;

		if ($total <= 0)
			return false;

	   return ($total >= $q) ? true : false;
	}

	/**
	 * Substract Checkout objects quantity for processed checkouts
	 * @param array $objects - The checkout objects array (getCollection returned array)
	 */
	public static function substractStock($objects)
	{
		//loop throught items and substract Q
		foreach ($objects as $obj) {

			//get object ORM class
			$object_class = $obj->object_class;

			$orm_object = !class_exists($object_class) ?: $object_class::findFirst(["id = ?1", "bind" => [1 => $obj->object_id]]);

			if(!$orm_object || empty($obj->quantity))
				continue;

			$current_quantity = $orm_object->quantity;
			$updated_quantity = (int)($current_quantity - $obj->quantity);

			$state = $orm_object->state;

			if ($updated_quantity <= 0) {

				$updated_quantity = 0;
				$state = "closed";
			}

			//update record throught query (safer than ORM)
			self::executePhql(
				"UPDATE $object_class
					SET quantity = ?1, state = ?2
					WHERE id = ?0
				",
				[$orm_object->id, $updated_quantity, $state]
			);
		}
	}
}
