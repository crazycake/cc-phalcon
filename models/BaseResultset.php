<?php
/**
 * Base Model Resultset
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Models;

//imports
use \Phalcon\Mvc\Model\Resultset\Simple as Resultset;

/**
 * Base extended functions for resultsets
 */
class BaseResultset extends Resultset
{
	/* Methods
	--------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Reduces a Resultset to native objects array
	 * @param Array $props - Filter properties, if empty array given filters all.
	 * @return Array
	 */
	public function reduce($props = null) {

		return self::reduceResultset($this, $props);
	}

	/**
	 * Reduces a Resultset to native objects array
	 * @param Object $resultset - Phalcon simple resultset
	 * @param Array $props - Filter properties, if empty array given filters all.
	 * @return Array
	 */
	public static function reduceResultset($resultset = null, $props = null) {

		if (!$resultset)
			return [];

		//objects
		$objects = [];
		
		foreach ($resultset as $obj)
			$objects[] = method_exists($obj, "reduce") ? $obj->reduce($props) : (object)$obj->toArray($props);

		return $objects;
	}

	/**
	 * Returns an array of Ids of current resultset object
	 * @param Array $field - The object field name
	 * @return Array of Ids
	 */
	public function toIdsArray($field = "id")
	{
		return self::getIdsArray($this, $field);
	}

	/**
	 * Returns an array of distinct ids of given objects
	 * @param Array $result - The resultSet array or a simple array
	 * @param Array $field - The object field name
	 * @param Boolean $unique - Flag for non repeated values
	 * @return Array of Ids
	 */
	public static function getIdsArray($result, $field = "id", $unique = true)
	{
		$ids = [];

		foreach ($result as $object)
			array_push($ids, $object->{$field});

		if (empty($ids))
			return false;

		return $unique ? array_unique($ids) : $ids;
	}

	/**
	 * Merge arbitrary props in _ext array property.
	 * @param Array $result - The resultset array or a native array
	 * @param String $field - The field with extentended props
	 * @param Array
	 */
	public static function mergeArbitraryProps(&$result = null, $field = "ext")
	{
		if ($result instanceof Resultset)
			$result = $result->toArray();

		if (is_object($result))
			$result = get_object_vars($result);

		if (empty($result) || !is_array($result))
			return;

		//anonymous function, merge _ext prop
		$mergeProps = function(&$object) {

			if (!is_array($object))
				return;

			$props = [];

			if (isset($object[field]))
				$props = $object[field];

			if (!is_null($props))
				$object = array_merge($props, $object);

			//unset unwanted props
			unset($object[field]);
		};

		//loop & merge props
		foreach ($result as &$obj)
			$mergeProps($obj);
	}
}
