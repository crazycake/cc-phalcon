<?php
/**
 * Responser Trait
 * Requires a Frontend or Backend Module with MvcCore
 * Requires Guzzle library (composer)
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Controllers;

//imports
use Phalcon\Exception;
use GuzzleHttp\Client as GuzzleClient; //Guzzle client for requests
use GuzzleHttp\Promise;
//core
use CrazyCake\Models\BaseResultset;

/**
 * HTTP Response Handler
 */
trait Responser
{
	/**
	 * JSON response struct
	 * @var string
	 */
	public static $JSON_RESPONSE_STRUCT = '{"response":{"code":"200","status":"ok","payload":@payload}}';

	/**
	 * Response HTTP Codes
	 */
	public static $RESPONSE_CODES = [
		//success
		"200" => "ok",
		//client errors
		"400" => "Bad Request, invalid GET or POST data",
		"401" => "Unauthorized",
		"403" => "Forbidden",
		"404" => "Not Found",
		"405" => "Method Not Allowed",
		"406" => "Not Acceptable",
		"408" => "Request Timeout",
		"498" => "Invalid Token",
		//server
		"500" => "Internal Server Error",
		"501" => "Unknown error",
		//db related
		"800" => "Empty result data",
		//resources related
		"900" => "Resource not found",
		"901" => "No files attached",
		"902" => "Invalid format of file attached"
	];

    /**
     * Sends a JSON response for APIs.
     * The HTTP statusCode is always 200.
     * Codes: ```200, 400, 404, 405, 498, 500, 501, 800, 900, 901, 902```
     * @param string $code - The app message code.
     * @param object $payload - Payload to send
     * @param string $type - (optional) Append a type attr to the response.
     * @param string $namespace - (optional) Append a type namespace to the response.
     * @return string - The response
     */
    protected function jsonResponse($code = 200, $payload = null, $type = "", $namespace = "")
    {
        //if code is not identified, mark as unknown error
        if (!isset($this->CODE[$code]))
            $this->CODE[$code] = $this->CODE[501];

        //set response
        $response = [
            "code"   => (string)$code,
            "status" => $code == 200 ? "ok" : "error"
        ];

        //type
        if (!empty($type))
            $response["type"] = $type;

        //namespace
        if (!empty($namespace))
            $response["namespace"] = $namespace;

        //success data
        if ($code == 200) {

            //if data is an object convert to array
            if (is_object($payload))
                $payload = get_object_vars($payload);

            //check redirection action
            if (is_array($payload) && isset($payload["redirect"])) {
                $response["redirect"] = $payload["redirect"];
            }
            //append payload
            else {

                //merge _ext properties for API
                if (MODULE_NAME === "api")
                    BaseResultset::mergeArbitraryProps($payload);

                $response["payload"] = $payload;
            }
        }
        //error data
        else {

            //set payload as objectId for numeric data, for string set as error
            if (is_numeric($payload))
                $response["object_id"] = $payload;
            else if (is_string($payload))
                $response["message"] = $payload;

            //set error for non array
            $response["error"] = is_object($payload) ? $payload : $this->CODE[$code];
        }

        //if a view service is set, disable rendering
        if (isset($this->view))
            $this->view->disable(); //disable view output

        //outputs JSON response
        $this->outputJsonResponse(["response" => $response]);
    }

	/**
     * Sends a file to buffer output response
     * @param binary $data - The binary data to send
     * @param string $mime_type - The mime type
     */
    protected function sendFileToBuffer($data = null, $mime_type = 'application/json')
    {
        //append struct as string if data type is JSON
        if ($mime_type == 'application/json')
            $data = str_replace("@payload", $data, self::$JSON_RESPONSE_STRUCT);

        if (isset($this->view)) {
            $this->view->disable();
            //return false;
        }

        $this->response->setStatusCode(200, "OK");
        $this->response->setContentType($mime_type);

        //content must be set after content type
        if (!is_null($data))
            $this->response->setContent($data);

        $this->response->send();
        die();
    }

    /**
     * Sets JSON response for output
     */
    protected function outputJsonResponse($response = []) {

        //if a view service is set, disable rendering
        if (isset($this->view))
            $this->view->disable(); //disable view output

        //output the response
        $this->response->setStatusCode(200, "OK");
        $this->response->setContentType("application/json"); //set JSON as Content-Type header
        $this->response->setContent(json_encode($response, JSON_UNESCAPED_SLASHES));
        $this->response->send();
        die();
    }

    /**
     * Sends a simple text response
     * @param  mixed [string|array] $text - Any text string
     */
    protected function textResponse($text = "OK") {

        if (is_array($text) || is_object($text))
            $text = json_encode($text, JSON_UNESCAPED_SLASHES);

        //if a view service is set, disable rendering
        if (isset($this->view))
            $this->view->disable(); //disable view output

        //output the response
        $this->response->setStatusCode(200, "OK");
        $this->response->setContentType('text/html');
        $this->response->setContent($text);
        $this->response->send();
        die();
    }
}