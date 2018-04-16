<?php
/**
 * BaseDocument Model (NoSQL)
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Models;

/**
 * Base extended functions for models
 */
class BaseDocument
{
	/* properties */

	/**
	 * The colelction name [required]
	 * @var String
	 */
	public static $COLLECTION = "";


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
	 * Get by Id
	 * @param Mixed $id - The document ID (String or ObjectId)
	 */
	public static function getById($id)
	{
		$mongo      = (\Phalcon\DI::getDefault())->getShared("mongo");
		$collection = static::$COLLECTION;

		$object_id = $id instanceof \MongoDB\BSON\ObjectId ? $id : new \MongoDB\BSON\ObjectId($id);

		try { $object = $mongo->{$collection}->findOne(["_id" => $object_id]); }
		catch (\Exception $e) { $object = false; }

		// return reduced object
		return $object ? $object->jsonSerialize() : null;
	}

	/**
	 * Updates a property
	 * @param Int $id - The object id
	 * @param String $prop - The property name
	 * @param Mixed $value - The value
	 */
	public static function updateProperty($id, $prop, $value)
	{
		$mongo      = (\Phalcon\DI::getDefault())->getShared("mongo");
		$collection = static::$COLLECTION;

		return $this->mongo->{$collection}->updateOne(["_id" => new \MongoDB\BSON\ObjectId($id)], ['$set' => ["$prop" => $value]]); 
	}

	/**
	 * Updates an array of properties
	 * @param Int $id - The object id
	 * @param Array $prop - The properties
	 */
	public static function updateProperties($id, $props)
	{
		$mongo      = (\Phalcon\DI::getDefault())->getShared("mongo");
		$collection = static::$COLLECTION;

		return $this->mongo->{$collection}->updateOne(["_id" => new \MongoDB\BSON\ObjectId($id)], ['$set' => $props]); 
	}

	/**
	 * Inserts a new document
	 * @param Mixed $data - The input data
	 */
	public static function insert($data)
	{
		$mongo      = (\Phalcon\DI::getDefault())->getShared("mongo");
		$collection = static::$COLLECTION;

		$insertion = $mongo->{$collection}->insertOne($data);
		$object_id = $insertion->getInsertedId();

		return self::getById($object_id);
	}

	/**
	 * To ISO date helper
	 * @param DateTime $date
	 */
	public static function toIsoDate($date = null)
	{
		if(!$date)
			$date = new \DateTime();

		else if(is_string($date))
			$date = new \DateTime($date);

		return new \MongoDB\BSON\UTCDateTime($date->getTimestamp() * 1000);
	}
}
