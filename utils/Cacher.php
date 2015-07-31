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
use Phalcon\DI;
//other libs
use Predis\Client as RedisClient;

class Cacher
{
    const REDIS_DEFAULT_PORT = 6379;
    const REDIS_DEFAULT_DB   = 15;

    /**
     * The Adapter name
     * @var string
     */
    protected $adapter;

    /**
     * The client reference
     * @var object
     */
    protected $client;


    /**
     * Cache prefix for cache Keys
     * Prefix takes app namespace value
     * @var string
     */
    protected $cache_prefix;

    /**
     * contructor
     * @param string $adapter Adapter name, availables: redis.
     */
    function __construct($adapter = null, $conf = array())
    {
        if(is_null($adapter))
            throw new Exception("Cacher -> adapter param is invalid. Options: redis for the moment.");

        //set DI reference (static)
        $di = DI::getDefault();

        //set adapter
        $this->adapter = ucfirst($adapter);
        //set cache prefix with app namespace
        $this->cache_prefix = $di->getShared('config')->app->namespace."-";

        //call method by reflection
        $this->{"setup".$this->adapter}($conf);
    }

    /**
     * Saves data to cache server
     * @param string $key The Key
     * @param mixed $value The Value for Key
     * @return boolean True if data was set in cache.
     */
    public function set($key = "", $value = null)
    {
        if(is_null($this->client))
            throw new Exception("Cacher -> missing client configuration.");

        if(empty($key))
            throw new Exception("Cacher -> Key parameter is empty");

        if(is_null($value))
            throw new Exception("Cacher -> Attempting to set null value for key $key.");

         try {
             //set cache data
             $result = $this->{"set".$this->adapter}($this->cache_prefix.$key, $value);

             if(!$result)
                throw new Exception("Cacher -> Adapter error: ".print_r($result, true));

             return true;
         }
         catch(\Exception $e) {

             //get DI instance (static)
             $di = DI::getDefault();
             $logger = $di->getShared("logger");
             $logger->error("Cacher -> Error saving data to $adapter server, key:$key. Err: ".$e->getMessage());

             return false;
         }
     }

     /**
      * Gets cache data
      * @param string $key The Key for searching
      * @return mixed Object or null
      */
     public function get($key = "")
     {
         if(is_null($this->client))
             throw new Exception("Cacher -> missing client configuration.");

         if(empty($key))
             return null;

         try {
             //get cache data
             $result = $this->{"get".$this->adapter}($this->cache_prefix.$key);

             if(!$result)
                throw new Exception("Cacher -> Adapter error: ".print_r($result, true));

            return $result;
         }
         catch(\Exception $e) {

             //get DI instance (static)
             $di = DI::getDefault();
             $logger = $di->getShared("logger");
             $logger->error("Cacher -> Error retrieving data from $adapter server, key:$key. Err: ".$e->getMessage());

             return null;
         }
     }

    /** -------------------------------------------------------------------------------------------------
        Redis implementations
    ------------------------------------------------------------------------------------------------- **/

    /**
     * Sets up redis connection, predis lib is used
     * @link https://github.com/nrk/predis/wiki/Connection-Parameters
     * scheme : defaults tcp.
     * host : defaults to localhost.
     * port : Redis port number.
     * database : Accepts a numeric value that is used by Predis to automatically select a logical database.
     * password : Auth password server key.
     * persistent : Specifies if the underlying connection resource should be left open when a script ends its lifecycle.
     */
    public function setupRedis($conf = array())
    {
        // sets up redis connection
        $this->client = new RedisClient(array(
            'scheme'     => isset($conf["scheme"]) ? $conf["scheme"] : "tcp",
            'host'       => isset($conf["host"]) ? $conf["host"] : "127.0.0.1",
            'port'       => isset($conf["port"]) ? $conf["port"] : self::REDIS_DEFAULT_PORT,
            'database'   => isset($conf["database"]) ? $conf["database"] : self::REDIS_DEFAULT_DB,
            'password'   => isset($conf["password"]) ? $conf["password"] : '',
            'persistent' => isset($conf["persistent"]) ? $conf["persistent"] : false
        ));
    }

    /**
     * Saves data to Redis server
     * @param string $key The Key
     * @param mixed $value The Value for Key
     * @return mixed
     */
    public function setRedis($key = "", $value = null)
    {
        $data = json_encode($value);
        //saves data
        return $this->client->set($key, $data);
     }

    /**
     * Gets redis data
     * @param string $key The Key for searching
     * @return mixed Object or null
     */
    public function getRedisData($key = "")
    {
        $data = $this->client->get($key);

        return json_decode($data);
    }
}
