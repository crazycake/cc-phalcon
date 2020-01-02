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
	 * Gets Database manager (override for custom connection)
	 */
	public static function getManager()
	{
		return (\Phalcon\DI::getDefault())->getShared("mongoManager");
	}

	/**
	 * Get by Id
	 * @param Mixed $id - The document ID (String or ObjectId)
	 * @param Array $options - Options
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
	 * @param Array $search - Search array
	 * @param Array $options - Options
	 */
	public static function getByProperties($search, $options = [])
	{
		$mongo = static::getClient();

		try { $object = $mongo->{static::$COLLECTION}->findOne($search, $options); }

		catch (\Exception $e) { $object = false; }

		// reduce object
		return $object ? $object->jsonSerialize() : null;
	}

	/**
	 * Get Property Distinct Values
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
	 * Find documents
	 * @param Mixed $search - Search array or string id
	 * @param Array $options - Options
	 */
	public static function find($search = [], $options = [])
	{
		$mongo = static::getClient();

		return $mongo->{static::$COLLECTION}->find($search, $options)->toArray();
	}

	/**
	 * Count documents
	 * @param Mixed $search - Search array or string id
	 * @param Array $options - Options
	 */
	public static function count($search = [], $options = [])
	{
		$mongo = static::getClient();

		if (!is_array($search))
			$search = ["_id" => self::toObjectId($search)];

		try { return $mongo->{static::$COLLECTION}->count($search, $options); }

		catch (\Exception $e) { return 0; }
	}

	/**
	 * Updates a single document
	 * @param Mixed $search - Search array or string id
	 * @param Array $prop - The properties
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
	 * Deletes a single document
	 * @param Mixed $search - Search array or string id
	 */
	public static function deleteOne($search)
	{
		$mongo = static::getClient();

		if (!is_array($search))
			$search = ["_id" => self::toObjectId($search)];

		return $mongo->{static::$COLLECTION}->deleteOne($search);
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
	public static function toDateString($bsonDate, $format = "Y-m-d H:i:s")
	{
		$tz = new \DateTimeZone(date_default_timezone_get());

		return $bsonDate->toDateTime()->setTimezone($tz)->format($format);
	}

	/**
	 * Get Id Date
	 * @param Mixed $id
	 * @param String $format
	 */
	public static function getIdDate($id, $format = "Y-m-d H:i:s")
	{
		$id = self::toObjectId($id);

		return self::toDateString(new \MongoDB\BSON\UTCDateTime($id->getTimestamp() * 1000), $format);
	}

	/**
	 * json to mongo object
	 * @param Mixed[String, Array, Object] $json
	 * @return object
	 */
	public static function jsonToMongoObject($json)
	{
		if (!is_string($json))
			$json = \CrazyCake\Helpers\JSON::safeEncode($json);

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
