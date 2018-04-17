<?php
/**
 * Base Model (Relational)
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Models;

//imports
use \Phalcon\Mvc\Model\Query as PHQL;

/**
 * Base extended functions for models
 */
class Base extends \Phalcon\Mvc\Model
{
	/* properties */

	/**
	 * The object ID
	 * @var Int
	 */
	public $id;

	/* Methods
	---------------------------------------------- § -------------------------------------------------- */

	/**
	 * Entity (class name)
	 * @link http://php.net/manual/en/language.oop5.late-static-bindings.php
	 * @return String The current class name
	 */
	public static function entity()
	{
		return __CLASS__;
	}

	/**
	 * Find Override
	 * @param Array $params - The input params
	 * @param Boolean $reduce - Reduce object to native array
	 * @return Array
	 */
	public static function find($params = null, $reduce = false)
	{
		$objects = parent::find($params);

		$props = empty($params["columns"]) ? null : $params["columns"];

		if($reduce)
			$objects = BaseResultset::reduceResultset($objects, $props);

		return $objects;
	}

	/**
	 * FindFirst Override
	 * @param Array $params - The input params
	 * @param Boolean $reduce - Reduce object to native array
	 * @return Object
	 */
	public static function findFirst($params = null, $reduce = false)
	{
		$object = parent::findFirst($params);

		$props = empty($params["columns"]) ? null : $params["columns"];

		if($reduce)
			$object = BaseResultset::reduceResultset($object, $props);

		return $object;
	}

	/**
	 * Find Object by ID
	 * @param Int $id - The object ID
	 * @return Object
	 */
	public static function getById($id = 0)
	{
		return self::findFirst(["id = ?1", "bind" => [1 => $id]]);
	}

	/**
	 * Get a Resulset by SQL query.
	 * @param String $sql - The SQL string
	 * @param Array $binds - The query bindings (optional)
	 * @param String $entity - The table entity
	 * @return Mixed
	 */
	public static function getByQuery($sql = "SELECT 1", $binds = [], $entity = null)
	{
		if (is_null($binds))
			$binds = [];

		if (is_null($entity))
			$entity = static::entity();

		$objects = new $entity();
		$result  = new BaseResultset(null, $objects, $objects->getReadConnection()->query($sql, $binds));

		return empty($result->count()) ? false : $result;
	}

	/**
	 * Get an object property value by executing a SQL query
	 * @param String $sql - The SQL string
	 * @param String $prop - The object property
	 * @param Array $binds - The query bindings (optional)
	 * @param String $entity - The table entity
	 * @return Object
	 */
	public static function getPropertyByQuery($sql = "SELECT 1", $prop = "id", $binds = [], $entity = null)
	{
		$result = self::getByQuery($sql, $binds, $entity);

		$result = $result ? $result->getFirst() : null;

		return $result->{$prop} ?? null;
	}

	/**
	 * Get Objects by PHQL language
	 * @param String $sql - The PHQL query string
	 * @param Array $binds - The binding params array
	 * @return Array
	 */
	public static function getByPhql($phql = "SELECT 1", $binds = [])
	{
		if (is_null($binds))
			$binds = [];

		$query  = new PHQL($phql, \Phalcon\DI::getDefault());
		$result = $query->execute($binds);

		if (empty($result->count()))
			return false;

		return $result;
	}

	/**
	 * Executes a PHQL Query, used for INSERT, UPDATE, DELETE
	 * @param String $phql - The PHQL query string
	 * @param Array $binds - The binding params array
	 * @return Boolean
	 */
	public static function executePhql($phql = "SELECT 1", $binds = [])
	{
		if (is_null($binds))
			$binds = [];

		$query = new PHQL($phql, \Phalcon\DI::getDefault());

		//Executing with bound parameters
		$status = $query->execute($binds);

		return $status ? $status->success() : false;
	}

	/**
	 * Inserts a new object
	 * @param Object $object
	 * @return Mixed
	 */
	public static function insert($data)
	{
		// ORM save
		$entity = static::entity();
		$object = new $entity();
		$object->save($data);

		// DB ORM errors?
		if(!empty($object->messages())) {

			(\Phalcon\DI::getDefault())->getShared("logger")->error("Base::insert -> failed insertion ".json_encode($object->messages()));
			return false;
		}

		return $object;
	}

	/**
	 * Updates a property
	 * @param Int $id - The object id
	 * @param String $prop - The property name
	 * @param Mixed $value - The value
	 * @param String $key - The key index
	 */
	public static function updateProperty($id, $prop, $value, $key = "id")
	{
		$entity = static::entity();

		return self::executePhql(
			"UPDATE $entity SET $prop = ?1 WHERE $key = ?0",
			[$id, $value]
		); 
	}

	/**
	 * Reduces a model object losing ORM properties
	 * @param Array $props - Filter properties, if empty array given filters all.
	 * @return Object
	 */
	public function reduce($props = null) {

		return (object)$this->toArray($props);
	}

	/**
	 * Get all messages from a created or updated object
	 * @param Boolean $format - Returns a joined string
	 * @return Mixed [array|string]
	 */
	public function messages($format = false)
	{
		$data = [];

		if (!method_exists($this, "getMessages"))
			return $data;

		foreach ($this->getMessages() as $msg)
			array_push($data, $msg->getMessage());

		if ($format)
			$data = implode($data, " ");

		return $data;
	}
}
