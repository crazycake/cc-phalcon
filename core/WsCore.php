<?php
/**
 * WS Controller : Core WebService controller, includes basic and helper methods for child controllers.
 * Requires a Phalcon DI Factory Services
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

abstract class WsCore extends AppCore
{
    /* consts */
    const HEADER_API_KEY = 'X_API_KEY'; //HTTP header keys uses '_' for '-' in Phalcon

    /**
     * abstract required methods
     */
    abstract protected function welcome();

    /**
     * API messages
     * @var array
     * @access protected
     */
    protected $CODES;

    /**
     * Constructor function
     * @access protected
     */
    protected function onConstruct()
    {
        /** -- API codes -- **/
        $this->CODES = array(
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
        );

        /* API Key Validation */
        if ($this->config->app->apiKeyEnabled)
            $this->_validateApiKey();
    }

    /**
     * Not found service catcher
     * @access public
     */
    public function serviceNotFound()
    {
        $this->_sendJsonResponse(404);
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Sends a JSON response
     * @access protected
     * @param string $code The app message code, the HTTP statusCode is always 200.
     * @param null $data Payload to send
     * @return string The response
     */
    protected function _sendJsonResponse($code = 200, $data = null)
    {
        //if code is not identified, send an unknown error
        if (!isset($this->CODES[$code]))
            $code = 501;

        //is an app error?
        $app_error = ($code != 200) ? true : false;

        //set response
        $response = array();
        $response["code"]   = (string)$code;
        $response["status"] = $app_error ? "error" : "ok";

        //error data
        if ($app_error) {
            //set payload as objectId for numeric data, for string set as error
            if (is_numeric($data))
                $response["object_id"] = $data;
            else if (is_string($data))
                $response["message"] = $data;

            //set error for non array
            if (is_array($data))
                $response["error"] = implode(". ", $data);
            else
                $response["error"] = $this->CODES[$code];
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
        $content = json_encode(array('response' => $response), JSON_UNESCAPED_SLASHES);

        //output the response
        $this->response->setStatusCode(200, "OK");
        $this->response->setContentType('application/json'); //set JSON as Content-Type header
        $this->response->setContent($content);
        $this->response->send();
        die(); //exit
    }


    /**
     * Handles the request validating data
     * @param string $prop The object property name
     * @param boolean $optional Parameter optional flag
     * @return mixed(object|boolean)
     */
    protected function __handleObjectIdRequestParam($prop = "object_id", $optional = false, $method = 'GET')
    {
        $props      = explode("_", strtolower($prop), 2);
        $class_name = ucfirst($props[0])."s"; //plural

        $s = $optional ? "@" : "";
        //get request param
        $data = $this->_handleRequestParams(array(
            "$s$prop"  => "int"
        ), $method);

        //get model data
        $object = $class_name::findFirst(array(
            "id = '".$data[$prop]."'" //conditions
        ));

        if(!$object)
            $this->_sendJsonResponse(400);
        else
            return $object;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * API key Validation
     * @return void
     */
    private function _validateApiKey()
    {
        //get API key from config file & request header Api Key
        $app_api_key    = $this->config->app->apiKey;
        $header_api_key = $this->request->getHeader(self::HEADER_API_KEY);

        //check if keys are equal
        if ($app_api_key !== $header_api_key)
            $this->_sendJsonResponse(498);
    }
}
