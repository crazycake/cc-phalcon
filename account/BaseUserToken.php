<?php
/**
 * Base Model User Token
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Account;

//imports
use Phalcon\Exception;
use Phalcon\Validation;
use Phalcon\Validation\Validator\InclusionIn;

/**
 * Base User Tokens Model
 */
class BaseUserToken extends \CrazyCake\Models\Base
{
	/* properties */

	/**
	 * The user ID
	 * @var int
	 */
	public $user_id;

	/**
	 * token hash
	 * @var string
	 */
	public $token;

	/**
	 * Possible values: activation, access, pass.
	 * @var string
	 */
	public $type;

	/**
	 * datetime
	 * @var string
	 */
	public $created_at;

	/* inclusion vars */

	/**
	 * Token expiration in days.
	 * Can be overrided.
	 * @var integer
	 */
	public static $TOKEN_EXPIRES_THRESHOLD = [
		"activation" => 60,
		"access"     => 90,
		"pass"       => 3
	];

	/**
	 * Token default length
	 * @var integer
	 */
	public static $TOKEN_LENGTH = 13;

	/**
	 * Validation Event
	 */
	public function validation()
	{
		$validator = new Validation();

		//type
		$validator->add("type", new InclusionIn([
			"domain"  => array_keys(self::$TOKEN_EXPIRES_THRESHOLD),
			"message" => "Invalid token type."
		]));

		return $this->validate($validator);
	}

	/** ------------------------------------------- § ------------------------------------------------ **/

	/**
	 * Find First Token By Value and Type.
	 * @static
	 * @param string $token - The token value
	 * @param string $type - The token type
	 * @return object
	 */
	public static function getTokenByValue($token = "", $type = "activation")
	{
		$conditions = "token = ?1 AND type = ?2";
		$binding    = [1 => $token, 2 => $type];

		return self::findFirst([$conditions, "bind" => $binding]);
	}

	/**
	 * Find First Token By User and Value.
	 * @static
	 * @param int $user_id - The user ID
	 * @param string $type - The token type
	 * @param string $token - The token value
	 * @return object
	 */
	public static function getTokenByUserAndValue($user_id = 0, $type = "activation", $token = "")
	{
		$conditions = "user_id = ?1 AND type = ?2 AND token = ?3";
		$binding    = [1 => $user_id, 2 => $type, 3 => $token];

		return self::findFirst([$conditions, "bind" => $binding]);
	}

	/**
	 * Find First Token By User and Token Type
	 * @static
	 * @param int $user_id - The user ID
	 * @param string $type - The token type
	 * @return object
	 */
	public static function getTokenByUserAndType($user_id = 0, $type = "activation")
	{
		$conditions = "user_id = ?1 AND type = ?2";
		$binding    = [1 => $user_id, 2 => $type];

		return self::findFirst([$conditions, "bind" => $binding]);
	}

	/**
	 * Saves a new ORM object.
	 * @static
	 * @param int $user_id - The user ID
	 * @param string $type - The token type, default is "activation"
	 * @return mixed [string|boolean]
	 */
	public static function newToken($user_id = 0, $type = "activation")
	{
		//Saves a new token
		$class = static::who();
		$token = new $class();

		$di   = \Phalcon\DI::getDefault();
		$hash = $di->getShared("cryptify")->newHash(static::$TOKEN_LENGTH);

		$token->user_id    = $user_id;
		$token->token      = $hash;
		$token->type       = $type;
		$token->created_at = date("Y-m-d H:i:s");

		//save token
		return $token->save() ? $token : false;
	}

	/**
	 * Check token date and generate a new user token if expired, returns a token object.
	 * @param int $user_id - The user ID
	 * @param string $type - The token type
	 * @return string
	 */
	public static function newTokenIfExpired($user_id = 0, $type = null)
	{
		//search if a token already exists and delete it.
		$token = self::getTokenByUserAndType($user_id, $type);

		if ($token) {
			//check token "days passed" (checks if token is expired)
			$days_passed = self::getTimePassedFromDate($token->created_at);

			if ($days_passed > static::$TOKEN_EXPIRES_THRESHOLD[$type]) {
				//if token has expired delete it and generate a new one
				$token->delete();
				$token = self::newToken($user_id, $type);
			}
		}
		else {
			$token = self::newToken($user_id, $type);
		}

		//append encrypted data
		$di = \Phalcon\DI::getDefault();
		$token->encrypted = $di->getShared("cryptify")->encryptData($token->user_id."#".$token->type."#".$token->token);

		return $token;
	}

	/**
	 * Validates user & temp-token data. Input data is encrypted with cryptify lib. Returns decrypted data.
	 * DI dependency injector must have cryptify service
	 * UserTokens must be set in models
	 * @static
	 * @param string $encrypted - The encrypted data
	 * @return array
	 */
	public static function handleEncryptedValidation($encrypted = null)
	{
		if (empty($encrypted))
			throw new Exception("got empty encrypted data");

		$di   = \Phalcon\DI::getDefault();
		$data = $di->getShared("cryptify")->decryptData($encrypted, "#");

		//validate data (user_id, token_type and token)
		if (count($data) < 3)
			throw new Exception("decrypted data is not at least 3 dimension array (user_id, token_type, token).");

		//set vars values
		$user_id    = $data[0];
		$token_type = $data[1];
		$token      = $data[2];

		//search for user and token combination
		$token = self::getTokenByUserAndValue($user_id, $token_type, $token);

		if (!$token)
			throw new Exception("temporal token dont exists.");

		$expiration = static::$TOKEN_EXPIRES_THRESHOLD[$token_type];

		//for other token type, get days passed
		$days_passed = self::getTimePassedFromDate($token->created_at);

		if ($days_passed > $expiration)
			throw new Exception("temporal token (id: ".$token->id.") has expired (".$days_passed." days passed since ".$token->created_at.")");

		return $data;
	}

	/**
	 * Deletes expired tokens
	 * Requires Carbon library
	 * @return int
	 */
	public static function deleteExpired()
	{
		if(!class_exists("\Carbon\Carbon"))
			return false;

		//use carbon to manipulate days
		try {

			$token_types = static::$TOKEN_EXPIRES_THRESHOLD;
			$count       = 0;

			foreach ($token_types as $type => $expiration) {

				//use server datetime
				$now = new \Carbon\Carbon();
				//consider one hour early from date
				$now->subDays($expiration);
				//sd($now->toDateTimeString());

				//get expired objects
				$conditions = "created_at < ?1 AND type = ?2";
				$binding    = [1 => $now->toDateTimeString(), 2 => $type];
				//query
				$objects = self::find([$conditions, "bind" => $binding]);

				//delete objects
				if (!$objects)
					continue;

				//set count
				$count += $objects->count();
				//delete action
				$objects->delete();
			}

			//return count
			return $count;
		}
		catch (Exception $e) {

			$this->logger->warning("BaseUserToken::deleteExpiredTokens -> error: ".$e->getMessage());
			return 0;
		}
	}

	/**
	 * Returns daytime passed from Now and given date
	 * @static
	 * @param date $date - Format Y-m-d H:i:s or a DateTime object
	 * @param string $f - The interval unit datetime, default is days.
	 * @return int
	 */
	public static function getTimePassedFromDate($date = null, $f = "days")
	{
		//validation
		if (is_null($date))
			throw new Exception("BaseUserToken::getTimePassedFromDate -> invalid date parameter, must be string or DateTime object.");

		//check if is a DateTime object
		$target_date = $date instanceof \DateTime ? $date : new \DateTime($date);

		//check days passed
		$today = new \DateTime("now");
		//calculate diff dates
		$interval = $today->diff($target_date);

		//return days property
		return $interval->$f;
	}
}
