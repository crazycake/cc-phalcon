<?php
/**
 * WS Controller : Core WebService controller, includes basic and helper methods for child controllers.
 * Requires a Phalcon DI Factory Services
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

//imports
use Phalcon\Exception;
//core
use CrazyCake\Services\Guzzle;

/**
 * Common functions for API WS
 */
abstract class WsCore extends AppCore
{
    /* consts */
    const HEADER_API_KEY = 'X_API_KEY'; //HTTP header keys uses '_' for '-' in Phalcon

    const WS_RESPONSE_CACHE_PATH = APP_PATH.'cache/response/';

    /**
     * Welcome message for API server status
     */
    abstract protected function welcome();

    /* traits */
    use Guzzle;

    /**
     * API version
     */
    public $version;

    /**
     * on Construct event
     */
    protected function onConstruct()
    {
        parent::onConstruct();

        //set API version
        $this->version = self::getModuleConfigProp("version");
        // API Key Validation
        $this->_validateApiKey();
    }

    /**
     * Not found service catcher
     */
    public function serviceNotFound()
    {
        $this->_sendJsonResponse(404);
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Handles validation from a given object
     * @param string $prop - The object property name
     * @param boolean $optional - Parameter optional flag
     * @param boolean $method - HTTP method, default is GET
     * @return mixed [object|boolean]
     */
    protected function _handleObjectIdRequestParam($prop = "object_id", $optional = false, $method = 'GET')
    {
        $scheme = explode("_", strtolower($prop));
        //unset last prop
        array_pop($scheme);
        //get class name
        $class_name = \Phalcon\Text::camelize(implode($scheme, "_"));

        $p = $optional ? "@" : "";
        //get request param
        $data = $this->_handleRequestParams([
            "$p$prop"  => "int"
        ], $method);

        $value = isset($data[$prop]) ? $data[$prop] : null;

        //get model data
        $object = empty($value) ? null : $class_name::findFirst(["id = ?1", "bind" => [1 => $value]]);

        if(!$optional && !$object)
            $this->_sendJsonResponse(400);
        else
            return $object;
    }

    /**
     * Handles a cache response
     * @param string $key - The key for saving cached data
     * @param mixed $data - The data to be cached or served
     * @param boolean $bust - Forces a cache update
     */
    protected function _handleCacheResponse($key = "response", $data = null, $bust = false)
    {
        //prepare input data
        $hash = sha1($key);

        if(empty($hash) || empty($data))
            $this->_sendJsonResponse(800);

        $json_file = self::WS_RESPONSE_CACHE_PATH."$hash.json";

        //get data for API struct
        if(!$bust && is_file($json_file)) {
            $this->_sendFileToBuffer(file_get_contents($json_file));
            return;
        }

        //check dir
        if(!is_dir(self::WS_RESPONSE_CACHE_PATH))
            mkdir(self::WS_RESPONSE_CACHE_PATH, 0775);

        //save file to disk
        file_put_contents($json_file, json_encode($data, JSON_UNESCAPED_SLASHES));

        //send response
        $this->_sendJsonResponse(200, $data);
    }

    /**
     * Cleans json cache files
     */
    protected function _cleanCacheResponse()
    {
        if(!is_dir(self::WS_RESPONSE_CACHE_PATH))
            return;

        foreach (glob(self::WS_RESPONSE_CACHE_PATH."/*.json") as $filename) {

            if (is_file($filename))
                unlink($filename);
        }
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * API key Validation
     */
    private function _validateApiKey()
    {
        $apiKey     = self::getModuleConfigProp("key");
        $keyEnabled = self::getModuleConfigProp("keyEnabled");

        if (!$keyEnabled)
            return;

        //get API key from request headers
        $headerApiKey = $this->request->getHeader(self::HEADER_API_KEY);
        //print_r($this->request->getHeaders());exit;

        //check if keys are equal
        if ($apiKey !== $headerApiKey)
            $this->_sendJsonResponse(498);
    }
}
