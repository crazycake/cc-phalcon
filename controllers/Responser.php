<?php
/**
 * Responser Trait
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Controllers;

use Phalcon\Exception;

use CrazyCake\Models\BaseResultset;

/**
 * HTTP Response Handler
 */
trait Responser
{
	/**
	 * JSON response struct
	 * @var String
	 */
	public static $JSON_RESPONSE_STRUCT = '{"code":"200","status":"ok","payload":@payload}';

	/**
	 * Response HTTP Codes
	 * @var Array
	 */
	public $RCODES = [
		// success
		"200" => "OK",
		"400" => "Bad Request",
		"401" => "Unauthorized",
		"404" => "Not Found",
		"408" => "Request Timeout",
		"498" => "Invalid Token",
		"500" => "Internal Server Error"
	];

	/**
	 * Sends a JSON response for APIs.
	 * The HTTP header status code is always 200.
	 * @param String $code - The app message code.
	 * @param Object $payload - Payload to send
	 * @param String $type - (optional) Append a type attr to the response. Example alert, warning.
	 * @param String $namespace - (optional) Append a type namespace to the response.
	 * @return String - The response
	 */
	protected function jsonResponse($code = 200, $payload = null, $type = "", $namespace = "")
	{
		// if code is not identified set default
		if (!isset($this->RCODES[$code]))
			$this->RCODES[$code] = $this->RCODES[400];

		// set response
		$response = [
			"code"   => (string)$code,
			"status" => $code == 200 ? "ok" : "error"
		];

		// type
		if (!empty($type))
			$response["type"] = $type;

		// namespace
		if (!empty($namespace))
			$response["namespace"] = $namespace;

		// success data
		if ($code == 200) {

			// serialize object?
			if (is_object($payload))
				$payload = json_decode(json_encode($payload), true);

			// check redirection action
			if (!empty($payload["redirect"]))
				$response["redirect"] = $payload["redirect"];
			else
				$response["payload"] = $payload; // append payload
		}
		// error data
		else {

			// set payload as objectId for numeric data, for string set as error
			if (is_string($payload))
				$response["message"] = $payload;

			// set error for non array
			$response["error"] = is_object($payload) ? $payload : $this->RCODES[$code];
		}

		// outputs JSON response
		$this->outputJsonResponse($response);
	}

	/**
	 * Sends a file to buffer output response
	 * @param Binary $data - The binary data to send
	 * @param String $mime_type - The mime type
	 * @return String - The response
	 */
	protected function sendFileToBuffer($data = null, $mime_type = "application/json")
	{
		// append struct as string if data type is JSON
		if ($mime_type == "application/json")
			$data = str_replace("@payload", $data, self::$JSON_RESPONSE_STRUCT);

		if ($this->di->has("view"))
			$this->view->disable(); // disable view output

		$this->response->setStatusCode(200, "OK");
		$this->response->setHeader("Access-Control-Allow-Origin", "*");
		$this->response->setContentType($mime_type);

		// content must be set after content type
		if (!is_null($data))
			$this->response->setContent($data);

		$this->response->send();
		die();
	}

	/**
	 * Sets JSON response for output
	 * @param Array $response - The response
	 * @return String - The response
	 */
	protected function outputJsonResponse($response = []) {

		// if a view service is set, disable rendering
		if ($this->di->has("view"))
			$this->view->disable();

		// output the response
		$this->response->setStatusCode(200, "OK");
		$this->response->setHeader("Access-Control-Allow-Origin", "*");
		$this->response->setContentType("application/json");
		$this->response->setContent(json_encode($response, JSON_UNESCAPED_SLASHES));
		$this->response->send();
		die();
	}

	/**
	 * Sends a simple text response
	 * @param Mixed $text - Any text string
	 * @return String - The response
	 */
	protected function outputTextResponse($text = "OK") {

		// if a view service is set, disable rendering
		if ($this->di->has("view"))
			$this->view->disable(); // disable view output

		// output the response
		$this->response->setStatusCode(200, "OK");
		$this->response->setHeader("Access-Control-Allow-Origin", "*");
		$this->response->setContentType('text/html');
		$this->response->setContent($text);
		$this->response->send();
		die();
	}
}
