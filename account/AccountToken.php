<?php
/**
 * Account Token
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Account;

/**
 * Account Token
 */
trait AccountToken
{
	/**
	 * Token expiration in days
	 * @var Integer
	 */
	public static $TOKEN_EXPIRES = [
		"access"     => 30,
		"activation" => 7,
		"pass"       => 1
	];

	/**
	 * Token default length
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

		$token = $redis->get("TOKEN_".$type."_".$user_id);
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

		$token   = (\Phalcon\DI::getDefault())->getShared("cryptify")->newHash(self::$TOKEN_LENGTH);
		$expires = self::$TOKEN_EXPIRES[$type];

		$key = "TOKEN_".$type."_".$user_id;

		$redis->set($key, $token);
		$redis->expire($key, $expires * 86400);
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
		$hash = (\Phalcon\DI::getDefault())->getShared("cryptify")->encryptData($user_id."#".$type."#".$token);

		return $hash;
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

		$redis->del("TOKEN_".$type."_".$user_id);
		$redis->close();
	}

	/**
	 * Validates user & temp-token data. Hash is an encrypted strig with user_id & token.
	 * @param String $hash - The hash data
	 * @return Boolean
	 */
	public static function validateHash($hash = "")
	{
		if (empty($hash))
			throw new \Exception("got empty hash");

		$data = (\Phalcon\DI::getDefault())->getShared("cryptify")->decryptData($hash, "#");

		// validate data (user_id, token_type and token)
		if (count($data) < 3)
			throw new \Exception("decrypted data is not 3 dimension array [user_id, token_type, token]");

		// set vars values
		list($user_id, $token_type, $token) = $data;

		// get token
		$stored_token = self::getToken($user_id, $token_type);

		if (!$stored_token || $token != $stored_token)
			throw new \Exception("no token match found.");

		return $data;
	}
}
