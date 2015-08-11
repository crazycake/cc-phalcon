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
    protected $cachePrefix;

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
        $this->cachePrefix = $di->getShared('config')->app->namespace."-";

        //call method by reflection
        $this->{"setup".$this->adapter}($conf);

        //check client was set
        if(is_null($this->client))
            throw new Exception("Cacher -> Missing client configuration.");
    }

    /**
     * Saves data to cache server
     * @param string $key The Key
     * @param mixed $value The Value for Key
     * @return boolean True if data was set in cache.
     */
    public function set($key = "", $value = null)
    {
         try {

             if(empty($key))
                 throw new Exception("Key parameter is empty");

             if(is_null($value))
                 throw new Exception("Attempting to set null value for key $key.");

             //set cache data
             $value  = json_encode($value);
             $result = $this->{"set".$this->adapter}($this->cachePrefix.$key, $value);

             if(!$result)
                throw new Exception("Adapter error: ".print_r($result, true));

            if(APP_ENVIRONMENT == "development") {
                $di = DI::getDefault();
                $logger = $di->getShared("logger");
                $logger->debug("Cacher:set -> set key: $key => ".$value);
            }

             return true;
         }
         catch(Exception $e) {

             $this->_logError("Cacher -> Failed saving data to ".$this->adapter." server, key: ".$key, $e);
             return false;
         }
     }

     /**
      * Gets cache data
      * @param string $key The Key for searching
      * @param boolean $decode Flag if data must be json_decoded
      * @return mixed Object or null
      */
     public function get($key = "", $decode = true)
     {
         try {

             if(empty($key))
                 throw new Exception("Empty key given");

             //get cache data
             $result = $this->{"get".$this->adapter}($this->cachePrefix.$key);

             if(!$result)
                throw new Exception("Adapter error: ".print_r($result, true));

            return $decode ? json_decode($result) : $result;
         }
         catch(Exception $e) {

             $this->_logError("Cacher -> Failed retrieving data from ".$this->adapter." server, key: ".$key, $e);
             return null;
         }
     }

     /**
      * deletes a cached key
      * @param string $key The Key for searching
      * @return boolean Returns true if was an affected key
      */
     public function delete($key = "")
     {
         try {

             if(empty($key))
                 throw new Exception("Empty key given");

             //get cache data
             $result = $this->{"get".$this->adapter}($this->cachePrefix.$key);

             if(!$result)
                return false;

            //delete key
            $this->{"delete".$this->adapter}($this->cachePrefix.$key);

            return true;
         }
         catch(Exception $e) {

             $this->_logError("Cacher -> Failed deleting data from ".$this->adapter." server, key: ".$key, $e);
             return null;
         }
     }

     /* --------------------------------------------------- § -------------------------------------------------------- */

     /**
      * Logs catched errors
      * @param  [string] $text The error text
      * @param  [exception] $e  The exception
      */
     private function _logError($text, $e)
     {
         //get DI instance (static)
         $di = DI::getDefault();
         $logger = $di->getShared("logger");
         $logger->error($text.". Err: ".$e->getMessage());
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
        $clientConf = array(
            'scheme'     => "tcp",
            'host'       => "127.0.0.1",
            'port'       => self::REDIS_DEFAULT_PORT,
            'persistent' => false
        );
        // sets up redis connection
        $this->client = new RedisClient(array_merge($clientConf, $conf));
    }

    /**
     * Saves data to Redis server
     * @param string $key The Key
     * @param mixed $value The Value for Key
     * @return mixed
     */
    public function setRedis($key = "", $value = null)
    {
        return $this->client->set($key, $value);
    }

    /**
     * Gets redis data
     * @param string $key The Key for searching
     * @return mixed Object or null
     */
    public function getRedis($key = "")
    {
        return $this->client->get($key);
    }

    /**
     * Deleted data to Redis server
     * @param string $key The Key
     */
    public function deleteRedis($key = "")
    {
        $this->client->del($key);
    }
}
