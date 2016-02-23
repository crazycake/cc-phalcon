<?php
/**
 * WS Controller : Core WebService controller, includes basic and helper methods for child controllers.
 * Requires a Phalcon DI Factory Services
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

//imports
use Phalcon\Exception;
//other imports
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
     * API messages
     * @var array
     */
    protected $CODES;

    /**
     * on Construct event
     */
    protected function onConstruct()
    {
        /** -- API codes -- **/
        $this->CODES = [
            //success
            "200" => "ok",
            //client errors
            "400" => "invalid GET or POST data (bad request)",
            "404" => "service not found",
            "405" => "method now allowed",
            "498" => "invalid header API key",
            //server
            "500" => "internal server error",
            "501" => "service unknown error",
            //db related
            "800" => "no db results found",
            //resources related
            "900" => "resource not found",
            "901" => "no files attached",
            "902" => "invalid format of file attached"
        ];

        /* API Key Validation */
        if ($this->config->app->api->keyEnabled)
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
     * Sends a JSON response for APIs.
     * The HTTP statusCode is always 200.
     * Codes: ```200, 400, 404, 405, 498, 500, 501, 800, 900, 901, 902```
     * @param string $code - The app message code.
     * @param object $data - Payload to send
     * @return string - The response
     */
    protected function _sendJsonResponse($code = 200, $data = null)
    {
        //if code is not identified, send an unknown error
        if (!isset($this->CODES[$code]))
            $code = 501;

        //is an app error?
        $app_error = ($code != 200) ? true : false;

        //set response
        $response = [
            "code"   => (string)$code,
            "status" => $app_error ? "error" : "ok"
        ];

        //error data
        if ($app_error) {
            //set payload as objectId for numeric data, for string set as error
            if (is_numeric($data))
                $response["object_id"] = $data;
            else if (is_string($data))
                $response["message"] = $data;

            //set error for non array
            $response["error"] = is_array($data) ? implode(". ", $data) : $this->CODES[$code];
        }
        //success data
        else {
            //if data is an object convert to array
            if (is_object($data))
                $data = get_object_vars($data);

            //append payload?
            if (!is_null($data))
                $response["payload"] = $data;
        }

        //encode JSON
        $content = json_encode(['response' => $response], JSON_UNESCAPED_SLASHES);

        //output the response
        $this->response->setStatusCode(200, "OK");
        $this->response->setContentType('application/json'); //set JSON as Content-Type header
        $this->response->setContent($content);
        $this->response->send();
        die(); //exit
    }

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
        $object = $class_name::findFirst([
            "id = '".$data[$prop]."'" //conditions
        ]);

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
        //get API key from config file & request header Api Key
        $app_api_key    = $this->config->app->api->key;
        $header_api_key = $this->request->getHeader(self::HEADER_API_KEY);
        //print_r($this->request->getHeaders());exit;

        //check if keys are equal
        if ($app_api_key !== $header_api_key)
            $this->_sendJsonResponse(498);
    }
}
