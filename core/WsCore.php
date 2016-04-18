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
use CrazyCake\Phalcon\AppLoader;
use CrazyCake\Services\Guzzle;

/**
 * Common functions for API WS
 */
abstract class WsCore extends AppCore
{
    /* consts */
    const HEADER_API_KEY = 'X_API_KEY'; //HTTP header keys uses '_' for '-' in Phalcon

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
        $this->version = AppLoader::getModuleConfigProp("version");
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
        $props      = explode("_", strtolower($prop), 2);
        $class_name = ucfirst($props[0])."s"; //plural

        $s = $optional ? "@" : "";
        //get request param
        $data = $this->_handleRequestParams([
            "$s$prop"  => "int"
        ], $method);

        //get model data
        $object = $class_name::findFirst(["id = ?1", "bind" => [1 => $data[$prop]]]);

        if(!$object)
            $this->_sendJsonResponse(400);
        else
            return $object;
    }
    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * API key Validation
     */
    private function _validateApiKey()
    {
        $apiKey     = AppLoader::getModuleConfigProp("key");
        $keyEnabled = AppLoader::getModuleConfigProp("keyEnabled");

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
