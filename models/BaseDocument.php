<?php
/**
 * BaseDocument Model (No SQL)
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
	 * @var string
	 */
	public static $COLLECTION = "";


	/**
	 * Default timezone
	 * @var string
	 */
	public static $TIMEZONE = "America/Santiago";

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
	 * Get by Id
	 * @param string $id - The document ID
	 */
	public static function getById($id)
	{
		$mongo      = (\Phalcon\DI::getDefault())->getShared("mongo");
		$collection = static::$COLLECTION;

		try { $object = $mongo->{$collection}->findOne(["_id" => new \MongoDB\BSON\ObjectId($id)]); }
		catch (\Exception $e) { $object = false; }

		// return reduced object
		return $object ? $object->jsonSerialize() : null;
	}


	/**
	 * To ISO date helper
	 * @param [DateTime] $date
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
