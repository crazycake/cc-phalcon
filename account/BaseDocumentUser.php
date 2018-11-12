<?php
/**
 * Base Model User (Document)
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Account;

/**
 * Base User Model
 */
class BaseDocumentUser extends \CrazyCake\Models\BaseDocument
{
	/**
	 * Flags
	 * @var Array
	 */
	static $FLAGS = ["pending", "enabled", "disabled"];

	/**
	 * Inserts a new document
	 * @override
	 * @param Mixed $data - The input data
	 * @return Mixed
	 */
	public static function insert($data)
	{
		if (is_array($data))
			$data = (object)$data;

		// set password hash
		if (!empty($data->pass))
			$data->pass = (\Phalcon\DI::getDefault())->getShared("security")->hash($data->pass);

		// set timestamp
		$data->createdAt = self::toIsoDate();

		return parent::insert($data);
	}

	/**
	 * Find User by email
	 * @param String $email - The user email
	 * @param String $flag - The account flag value
	 * @return Object
	 */
	public static function getUserByEmail($email, $flag = null)
	{
		$mongo = (\Phalcon\DI::getDefault())->getShared("mongo");

		$query = ["email" => $email];

		if ($flag)
			$query["flag"] = $flag;

		try { $object = $mongo->{static::$COLLECTION}->findOne($query); }
		catch (\Exception $e) { $object = false; }

		// return reduced object
		return $object ? $object->jsonSerialize() : null;
	}
}
