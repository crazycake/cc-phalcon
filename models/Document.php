<?php
/**
 * Document Model (MongoDB)
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Models;

/**
 * Base extended functions for models
 */
class Document
{
	/**
	 * The collection name (required)
	 * @var String
	 */
	public static $COLLECTION = "";

	/**
	 * Gets Database client (override for custom connection)
	 */
	public static function getClient()
	{
		return (\Phalcon\DI::getDefault())->getShared("mongo");
	}

	/**
	 * Get by Id
	 * @param Mixed $id - The document ID (String or ObjectId)
	 */
	public static function getById($id, $options = [])
	{
		$mongo = static::getClient();

		try { $object = $mongo->{static::$COLLECTION}->findOne(["_id" => is_numeric($id) ? (string)$id : self::toObjectId($id)], $options); }

		catch (\Exception $e) { $object = false; }

		// return reduced object
		return $object ? $object->jsonSerialize() : null;
	}

	/**
	 * Get by Properties
	 * @param Array $props - Properties (associative array)
	 * @param Array $opts - Options (associative array)
	 */
	public static function getByProperties($props, $opts = [])
	{
		$mongo = static::getClient();

		try { $object = $mongo->{static::$COLLECTION}->findOne($props, $opts); }

		catch (\Exception $e) { $object = false; }

		// reduce object
		return $object ? $object->jsonSerialize() : null;
	}

	/**
	 * Get Distinct Property Values
	 * @param String $prop - property name
	 * @param Mixed $case - case flag [UPPER, LOWER]
	 */
	public static function getDistinctValues($prop, $case = false)
	{
		$mongo = static::getClient();

		$values = $mongo->{static::$COLLECTION}->distinct($prop);

		foreach ($values as &$v)
			$v = empty($case) ? $v : ($case == "UPPER" ? strtoupper($v) : strtolower($v));

		if (!$values)
			return [];

		sort($values);

		return $values;
	}

	/**
	 * Updates an array of properties
	 * @param Mixed $search - Array or String
	 * @param Array $prop - The properties
	 * @param String $key - The key index
	 */
	public static function updateProperties($search, $props)
	{
		$mongo = static::getClient();

		if (!is_array($search))
			$search = ["_id" => self::toObjectId($search)];

		return $mongo->{static::$COLLECTION}->updateOne($search, ['$set' => $props]);
	}

	/**
	 * Inserts a new document
	 * @param Mixed $data - The input data
	 */
	public static function insert($data)
	{
		$mongo = static::getClient();

		$insertion = $mongo->{static::$COLLECTION}->insertOne($data);
		$object_id = $insertion->getInsertedId();

		return self::getById($object_id);
	}

	/**
	 * To Mongo Object ID
	 * @param Mixed $id
	 */
	public static function toObjectId($id)
	{
		try { $id = $id instanceof \MongoDB\BSON\ObjectId ? $id : new \MongoDB\BSON\ObjectId($id); }

		catch (\Exception $e) { $id = (string)$id; }

		return $id;
	}

	/**
	 * To ISO date helper
	 * @param DateTime $date
	 */
	public static function toIsoDate($date = null)
	{
		if (!$date)
			$date = new \DateTime();

		else if (is_string($date))
			$date = new \DateTime($date);

		return new \MongoDB\BSON\UTCDateTime($date->getTimestamp() * 1000);
	}

	/**
	 * To Date string
	 * @param BSON $bsonDate
	 * @param String $format
	 */
	public static function toDateString($bsonDate, $format = "Y/m/d H:i:s")
	{
		$tz = new \DateTimeZone(date_default_timezone_get());

		return $bsonDate->toDateTime()->setTimezone($tz)->format($format);
	}

	/**
	 * json to mongo object
	 * @param Mixed[String, Array, Object] $json
	 * @return object
	 */
	public static function jsonToMongoObject($json)
	{
		if (!is_string($json))
			$json = json_encode($json);

		$bson = \MongoDB\BSON\fromJSON($json);

		return \MongoDB\BSON\toPHP($bson);
	}

	/**
	 * Recursively flatten multidimensional array to one dimension (helper)
	 * @param Array $array - An input array
	 * @return Array
	 */
	public static function flattenArray($array = []) {

		return !is_array($array) ? [$array] : array_reduce($array, function ($c, $a) {

			return array_merge($c, static::flattenArray($a));

		}, []);
	}
}
