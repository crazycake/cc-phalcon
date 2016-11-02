<?php
/**
* Redis: Includes data cache operations with Redis.
* Requires Redis server installed
* @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
*/

namespace CrazyCake\Services;

//imports
use Phalcon\Exception;
use Phalcon\DI;
//other libs
use Predis\Client as RedisClient;

/**
* Redis service
*/
class Redis
{
	/** Consts **/

	/**
	* @var Redis default Configuration
	* @link https://github.com/nrk/predis/wiki/Connection-Parameters
	* scheme : defaults tcp.
	* host : defaults to localhost.
	* port : Redis port number.
	* database : Accepts a numeric value that is used by Predis to automatically select a logical database.
	* password : Auth password server key.
	* persistent : Specifies if the underlying connection resource should be left open when a script ends its lifecycle.
	*/
	const REDIS_DEFAULT_CONF = [
		"scheme"     => "tcp",
		"host"       => "127.0.0.1",
		"port"       => 6379,
		"persistent" => false
	];

	/**
	* The client reference
	* @var object
	*/
	protected $client;

	/**
	* Constructor
	* @param array $conf - Redis configs
	*/
	function __construct($conf = [])
	{
		//get DI reference (static)
		$di = \Phalcon\DI::getDefault();

		//set cache prefix with app namespace
		if (!isset($conf["prefix"]))
			$conf["prefix"] = $di->getShared("config")->app->namespace.":";

		// setup redis connection
		$this->client = new RedisClient(array_merge(self::REDIS_DEFAULT_CONF, $conf));

		//check client was set
		if (is_null($this->client))
			throw new Exception("Redis -> Missing client configuration.");
	}

	/**
	* Saves data to cache server
	* @param string $key - The Key
	* @param mixed $value - The Value for Key
	* @return boolean - Value is true if data was set in cache.
	*/
	public function set($key = "", $value = null)
	{
		try {

			if (empty($key))
				throw new Exception("Key parameter is empty");

			if (is_null($value))
				throw new Exception("Attempting to set null value for key $key.");

			//set data
			$value  = json_encode($value, JSON_UNESCAPED_SLASHES);
			$result = $this->client->set($key, $value);

			if (!$result)
				throw new Exception("Error setting key");

			if (APP_ENVIRONMENT === "local") {

				$di = \Phalcon\DI::getDefault();
				$logger = $di->getShared("logger");
				$logger->debug("Redis:set -> set key: $key => ".$value);
			}

			return true;
		}
		catch (Exception $e) {
			return false;
		}
	}

	/**
	* Gets cached data
	* @param string $key - The Key for searching
	* @param boolean $decode - Flag if data must be json_decoded
	* @return object - Returns null if object was not found
	*/
	public function get($key = "", $decode = true)
	{
		try {

			if (empty($key))
				throw new Exception("Empty key given");

			//get cached data
			$result = $this->client->get($key);

			if (!$result)
				throw new Exception("Key not found");

			return $decode ? json_decode($result) : $result;
		}
		catch (Exception $e) {
			return null;
		}
	}

	/**
	* Deletes a cached key
	* @param string $key - The Key for searching
	* @return boolean - Returns true if was an affected key
	*/
	public function delete($key = "")
	{
		try {

			if (empty($key))
				throw new Exception("Empty key given");

			//get cached data
			$result = $this->client->get($key);

			if (!$result)
				return false;

			//delete key
			$this->client->del($key);

			return true;
		}
		catch (Exception $e) {
			return null;
		}
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */
}
