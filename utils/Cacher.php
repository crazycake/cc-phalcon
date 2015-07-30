<?php
/**
 * Cacher: Includes data cache operations with Redis.
 * Used to store user data like sessions in all modules.
 * Requires Redis server installed
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Utils;

//imports
use Phalcon\Exception;

class Cacher
{
    const REDIS_DEFAULT_PORT     = 6379;
    const REDIS_DEFAULT_AUTH     = 'foobared';
    const REDIS_DEFAULT_LIFETIME = 172800; //2 days

    /**
     * The Redis libary reference
     * @var object
     */
    private $redis;

    /**
     * contructor
     * @param string $adapter Adapter name, availables: redis.
     */
    function __construct($adapter = null, $conf = array())
    {
        if(is_null($adapter))
            throw new Exception("Cacher -> adapter param is invalid. Options: redis for the moment.");

        switch (strtolower($adapter)) {
            case 'redis':
                $this->setupRedis($conf);
                break;
            default:
                break;
        }
    }

    /**
     * Sets up redis connection, conf params:
     * port : Redis port number.
     * auth : Auth server key.
     * lifetime : Cache expiration, in seconds, default 2 days.
     */
    public function setupRedis($conf = array())
    {
        // Cache data for 2 days (default)
        $frontCache = new \Phalcon\Cache\Frontend\Data(array(
            "lifetime" => isset($conf["lifetime"]) ? $conf["lifetime"] : self::REDIS_DEFAULT_LIFETIME
        ));

        // sets up redis connection
        $this->redis = new Phalcon\Cache\Backend\Redis($frontCache, array(
           'host'       => 'localhost',
           'port'       => isset($conf["port"]) ? $conf["port"] : self::REDIS_DEFAULT_PORT,
           'auth'       => isset($conf["auth"]) ? $conf["auth"] : self::REDIS_DEFAULT_AUTH,
           'persistent' => false
        ));
    }

    /**
     * Saves data to Redis server
     * @param string $key The Key
     * @param mixed $value The Value for Key
     * @return boolean True if data was saved in Redis.
     */
    public function saveRedisData($key = "", $value = null)
    {
        if(empty($key))
            throw new Exception("Cacher -> Key parameter is empty");

        if(is_null($value))
            throw new Exception("Cacher -> Attempting to save null value for key $key.");

         //Cache arbitrary data
         try {
             $this->redis->save($key, $value);
             return true;
         }
         catch(\Exception $e) {

             //get DI instance (static)
             $di = \Phalcon\DI::getDefault();
             $logger = $di->getShared("logger");

             $logger->error("Cacher -> Error saving data to redis server, key:$key. Err: ".$e->getMessage());
             return false;
         }
     }

    /**
     * Gets redis data
     * @param string $key The Key for searching
     * @return mixed Object or null
     */
    public function getRedisData($key = "")
    {
        if(empty($key))
            return null;

        //Cache arbitrary data
        try {
            $data = $this->redis->get($key);
            return $data;
        }
        catch(\Exception $e) {

            //get DI instance (static)
            $di = \Phalcon\DI::getDefault();
            $logger = $di->getShared("logger");

            $logger->error("Cacher -> Error retrieving data from redis server, key:$key. Err: ".$e->getMessage());
            return null;
        }
    }
}
