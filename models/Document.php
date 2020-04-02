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
	 * @return Object
	 */
	public static function getClient()
	{
		return (\Phalcon\DI::getDefault())->getShared("mongo");
	}

	/**
	 * Gets Database manager (override for custom connection)
	 * @return Object
	 */
	public static function getManager()
	{
		return (\Phalcon\DI::getDefault())->getShared("mongoManager");
	}

	/**
	 * Finds one document
	 * @param Mixed $query - Query array or id as string / object
	 * @param Array $options - Options
	 * @return Object
	 */
	public static function findOne($query, $options = [])
	{
		$mongo = static::getClient();

		if (!is_array($query))
			$query = ["_id" => is_numeric($query) ? (string)$query : self::toObjectId($query)];

		try { $object = $mongo->{static::$COLLECTION}->findOne($query, $options); }

		catch (\Exception $e) { $object = false; }

		return $object ? $object->jsonSerialize() : null;
	}

	/**
	 * Find one or more documents
	 * @param Mixed $query - Query array
	 * @param Array $options - Options
	 * @return Array
	 */
	public static function find($query = [], $options = [])
	{
		$mongo = static::getClient();

		return $mongo->{static::$COLLECTION}->find($query, $options)->toArray();
	}

	/**
	 * Count documents
	 * @param Mixed $query - Query array or string id
	 * @param Array $options - Options
	 * @return Integer
	 */
	public static function count($query = [], $options = [])
	{
		$mongo = static::getClient();

		if (!is_array($query))
			$query = ["_id" => self::toObjectId($query)];

		try { return $mongo->{static::$COLLECTION}->count($query, $options); }

		catch (\Exception $e) { return 0; }
	}

	/**
	 * Get Property Distinct Values
	 * @param String $prop - property name
	 * @param Array $query - The query
	 * @param Mixed $case - case flag for values [UPPER, LOWER]
	 * @return Array
	 */
	public static function distinct($prop, $query = [], $case = null)
	{
		$mongo  = static::getClient();
		$values = $mongo->{static::$COLLECTION}->distinct($prop, $query);

		foreach ($values as &$v)
			$v = empty($case) ? $v : ($case == "UPPER" ? strtoupper($v) : strtolower($v));

		if (!$values)
			return [];

		sort($values);

		return $values;
	}

	/**
	 * Inserts a new document
	 * @param Mixed $data - The input data
	 * @return Object
	 */
	public static function insert($data)
	{
		$mongo = static::getClient();

		$insertion = $mongo->{static::$COLLECTION}->insertOne($data);
		$object_id = $insertion->getInsertedId();

		return self::getById($object_id);
	}

	/**
	 * Updates a single document using $set
	 * @param Mixed $query - Query array or string id
	 * @param Array $props - The properties
	 * @return Object
	 */
	public static function updateOne($query, $props)
	{
		$mongo = static::getClient();

		if (!is_array($query))
			$query = ["_id" => self::toObjectId($query)];

		return $mongo->{static::$COLLECTION}->updateOne($query, ['$set' => $props]);
	}

	/**
	 * Updates multiple documents using $set
	 * @param Mixed $query - Query array
	 * @param Array $prop - The properties
	 * @return Object
	 */
	public static function updateMany($query, $props)
	{
		$mongo = static::getClient();

		return $mongo->{static::$COLLECTION}->updateMany($query, ['$set' => $props]);
	}

	/**
	 * Deletes a single document
	 * @param Mixed $query - Query array or string id
	 * @return Object
	 */
	public static function deleteOne($query)
	{
		$mongo = static::getClient();

		if (!is_array($query))
			$query = ["_id" => self::toObjectId($query)];

		return $mongo->{static::$COLLECTION}->deleteOne($query);
	}

	/**
	 * Deletes multiple documents
	 * @param Array $query - Query array
	 * @return Object
	 */
	public static function deleteMany($query)
	{
		$mongo = static::getClient();

		return $mongo->{static::$COLLECTION}->deleteMany($query);
	}

	/**
	 * Converts to Mongo Object ID
	 * @param Mixed $id - The input id as string / object
	 * @return Object
	 */
	public static function toObjectId($id)
	{
		try { $id = $id instanceof \MongoDB\BSON\ObjectId ? $id : new \MongoDB\BSON\ObjectId($id); }

		catch (\Exception $e) { $id = (string)$id; }

		return $id;
	}

	/**
	 * Converts to ISO date
	 * @param Mixed $date - The input DateTime or date string
	 * @return Object
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
	 * Converts to Date string
	 * @param Object $bsonDate - The BSON date
	 * @param String $format - Date format
	 * @return String
	 */
	public static function toDateString($bsonDate, $format = "Y-m-d H:i:s")
	{
		$tz = new \DateTimeZone(date_default_timezone_get());

		return $bsonDate->toDateTime()->setTimezone($tz)->format($format);
	}

	/**
	 * Converts JSON to Mongo object
	 * @param Mixed $json - The input JSON
	 * @return Object
	 */
	public static function jsonToMongoObject($json)
	{
		if (!is_string($json))
			$json = \CrazyCake\Helpers\JSON::safeEncode($json);

		$bson = \MongoDB\BSON\fromJSON($json);

		return \MongoDB\BSON\toPHP($bson);
	}

	/**
	 * Get ObjectId Date
	 * @param Mixed $id - The input id as string or ObjectId
	 * @param String $format - Date format
	 * @return String
	 */
	public static function getIdDate($id, $format = "Y-m-d H:i:s")
	{
		$id = self::toObjectId($id);

		return self::toDateString(new \MongoDB\BSON\UTCDateTime($id->getTimestamp() * 1000), $format);
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
