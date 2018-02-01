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
	 * @var int
	 */
	public $id;

	/* Methods
	---------------------------------------------- § -------------------------------------------------- */

	/**
	 * Entity (class name)
	 * @static
	 * @link http://php.net/manual/en/language.oop5.late-static-bindings.php
	 * @return string The current class name
	 */
	public static function entity()
	{
		return __CLASS__;
	}

	/**
	 * Find Override
	 * @static
	 * @param array $params - The input params
	 * @param boolean $reduce - Reduce object to native array
	 * @return object Simple\Resultset
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
	 * @static
	 * @param array $params - The input params
	 * @param boolean $reduce - Reduce object to native array
	 * @return object
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
	 * @static
	 * @param int $id - The object ID
	 * @return Object
	 */
	public static function getById($id = 0)
	{
		return self::findFirst(["id = ?1", "bind" => [1 => $id]]);
	}

	/**
	 * Get a Resulset by SQL query.
	 * @static
	 * @param string $sql - The SQL string
	 * @param array $binds - The query bindings (optional)
	 * @param string $className - A different class name than self (optional)
	 * @return mixed
	 */
	public static function getByQuery($sql = "SELECT 1", $binds = [], $className = null)
	{
		if (is_null($binds))
			$binds = [];

		if (is_null($className))
			$className = static::entity();

		$objects = new $className();
		$result  = new BaseResultset(null, $objects, $objects->getReadConnection()->query($sql, $binds));

		return empty($result->count()) ? false : $result;
	}

	/**
	 * Get an object property value by executing a SQL query
	 * @static
	 * @param string $sql - The SQL string
	 * @param string $prop - The object property
	 * @param array $binds - The query bindings (optional)
	 * @param string $className - A different class name than self (optional)
	 * @return object
	 */
	public static function getPropertyByQuery($sql = "SELECT 1", $prop = "id", $binds = [], $className = null)
	{
		$result = self::getByQuery($sql, $binds, $className);

		$result = $result ? $result->getFirst() : null;

		return $result->{$prop} ?? null;
	}

	/**
	 * Get Objects by PHQL language
	 * @static
	 * @param string $sql - The PHQL query string
	 * @param array $binds - The binding params array
	 * @return array
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
	 * @static
	 * @param string $phql - The PHQL query string
	 * @param array $binds - The binding params array
	 * @return boolean
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
	 * Reduces a model object losing ORM properties
	 * array $props - Filter properties. if empty array given filters all.
	 * @return object
	 */
	public function reduce($props = null) {

		return (object)$this->toArray($props);
	}

	/**
	 * Get all messages from a created or updated object
	 * @param boolean $format - Returns a joined string
	 * @return mixed [array|string]
	 */
	public function messages($format = false)
	{
		$data = [];

		if (!method_exists($this, "getMessages"))
			return ($data[0] = "Unknown ORM Error");

		foreach ($this->getMessages() as $msg)
			array_push($data, $msg->getMessage());

		if ($format)
			$data = implode($data, " ");

		return $data;
	}
}
