<?php
/**
 * Account Token
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Account;

//imports
use Phalcon\Exception;

/**
 * Account Token
 */
trait AccountToken
{
	/**
	 * Token expiration in days (can be overrided).
	 * @var Integer
	 */
	public static $TOKEN_EXPIRES = [
		"access"     => 30,
		"activation" => 5,
		"pass"       => 1
	];

	/**
	 * Token default length (can be overrided).
	 * @var Integer
	 */
	public static $TOKEN_LENGTH = 13;

	/** ------------------------------------------- § ------------------------------------------------ **/

	/**
	 * Returns a new redis client
	 */
	protected static function newRedisClient()
	{
		$redis = new \Redis();
		$redis->connect(getenv("REDIS_HOST") ?: "redis");

		return $redis;
	}

	/**
	 * Get Token
	 * @param String $user_id - The user id
	 * @param String $type - The token type
	 * @return String
	 */
	public static function getToken($user_id, $type)
	{
		$redis = self::newRedisClient();

		$token = $redis->get("TOKEN_$type_$user_id");
		$redis->close();
		
		return $token;
	}

	/**
	 * Saves a new token
	 * @param Int $user_id - The user ID
	 * @param String $type - The token type
	 */
	public static function newToken($user_id, $type)
	{
		$redis = self::newRedisClient();

		$token   = (\Phalcon\DI::getDefault())->getShared("cryptify")->newHash(static::$TOKEN_LENGTH);
		$expires = static::$TOKEN_EXPIRES[$type];

		$redis->set("TOKEN_$type_$user_id", $token);
		$redis->expire($user_id."#".$type, 10); //$expires * 86400
		$redis->close();

		return $token;
	}

	/**
	 * Saves a token if expires and returns the token chain data
	 * @param Int $user_id - The user ID
	 * @param String $type - The token type
	 * @return String
	 */
	public static function newTokenChainCrypt($user_id, $type)
	{
		$token = self::getToken($user_id, $type);

		if (!$token)
			$token = self::newToken($user_id, $type);

		//append encrypted data
		$encrypted = (\Phalcon\DI::getDefault())->getShared("cryptify")->encryptData($user_id."#".$type."#".$token);

		return $encrypted;
	}

	/**
	 * Delete Token
	 * @param String $user_id - The user id
	 * @param String $type - The token type
	 * @return String
	 */
	public static function deleteToken($user_id, $type)
	{
		$redis = self::newRedisClient();

		$redis->del("TOKEN_$type_$user_id");
		$redis->close();
	}

	/**
	 * Validates user & temp-token data. Input data is encrypted with cryptify lib. Returns decrypted data.
	 * @param String $encrypted - The encrypted data
	 * @return Boolean
	 */
	public static function handleEncryptedValidation($encrypted)
	{
		if (empty($encrypted))
			throw new Exception("got empty encrypted data");

		$data = (\Phalcon\DI::getDefault())->getShared("cryptify")->decryptData($encrypted, "#");

		//validate data (user_id, token_type and token)
		if (count($data) < 3)
			throw new Exception("decrypted data is not 3 dimension array [user_id, token_type, token]");

		//set vars values
		list($user_id, $token_type, $token) = $data;

		//get token
		$storedToken = self::getToken($user_id, $token_type);

		if (!$storedToken || $token != $storedToken)
			throw new Exception("no token match found.");

		return $data;
	}
}
