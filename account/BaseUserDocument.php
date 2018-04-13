<?php
/**
 * Base Model User (Document)
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Account;

/**
 * Base User Model
 */
class BaseUserDocument extends \CrazyCake\Models\BaseDocument
{
	/**
	 * Flags
	 * @var Array
	 */
	static $FLAGS = ["pending", "enabled", "disabled"];

	/**
	 * Inserts a new object
	 * @override
	 * @param Object $object
	 * @return Mixed
	 */
	public static function insert(&$user)
	{
		//set password hash
		if (!is_null($user->pass))
			$user->pass = (\Phalcon\DI::getDefault())->getShared("security")->hash($user->pass);

		//set timestamp
		$user->createdAt = $self::toIsoDate();

		return parent::insert($user);
	}

	/**
	 * Find User by email
	 * @param String $email - The user email
	 * @param String $flag - The account flag value
	 * @return Object
	 */
	public static function getUserByEmail($email, $flag = null)
	{
		$mongo      = (\Phalcon\DI::getDefault())->getShared("mongo");
		$collection = static::$COLLECTION;

		$query = ["email" => $email];

		if($flag)
			$query["flag"] = $flag;

		try { $object = $mongo->{$collection}->findOne($query); }
		catch (\Exception $e) { $object = false; }

		// return reduced object
		return $object ? $object->jsonSerialize() : null;
	}
}
