<?php
/**
 * Base Model Users Checkouts Objects (Relational)
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Checkout;

/**
 * Base User Checkouts objects
 */
class BaseUserCheckoutObject extends \CrazyCake\Models\Base
{
	/* properties */

	/**
	 * Buy order
	 * @var String
	 */
	public $buy_order;

	/**
	 * Object class
	 * @var String
	 */
	public $object_class;

	/**
	 * Object ID
	 * @var String
	 */
	public $object_id;

	/**
	 * Quantity
	 * @var Int
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
	 * Validates that checkout object is already in stock.
	 * Sums to q the number of checkout object presents in a pending checkout state.
	 * @param String $object_class - The object class
	 * @param Int $object_id - The object id
	 * @param Int $q - The quantity to validate
	 * @return Boolean
	 */
	public static function validateStock($object_class = "", $object_id = 0, $q = 0)
	{
		if (!class_exists($object_class))
			throw new Exception("BaseUserCheckoutObject -> Object class not found ($object_class)");

		$object = $object_class::getById($object_id);

		if (!$object)
			return false;

		//get classes
		$entity 		 = static::entity();
		$checkout_entity = str_replace("Object", "", $entity);

		//get pending checkouts items quantity
		$objects = $checkout_entity::getByPhql(
			//phql
			"SELECT SUM(quantity) AS q
			 FROM $entity AS objects
			 INNER JOIN $checkout_entity AS checkout ON checkout.buy_order = objects.buy_order
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

		return $total >= $q;
	}

	/**
	 * Substract Checkout objects quantity for processed checkouts
	 * @param Array $objects - The checkout objects array (getCollection returned array)
	 */
	public static function substractStock($objects)
	{
		//loop throught items and substract Q
		foreach ($objects as $obj) {

			//get object ORM class
			$object_class = $obj->object_class;

			$orm_object = !class_exists($object_class) ?: $object_class::getById($obj->object_id);

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
				 SET quantity = ?0, state = ?1
				 WHERE id = ?2
				",
				[(int)$updated_quantity, $state, (int)$orm_object->id]
			);
		}
	}
}
