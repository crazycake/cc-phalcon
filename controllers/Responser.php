<?php
/**
 * Responser Trait
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Controllers;

use Phalcon\Exception;

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
	 * @param String $code - The app message code.
	 * @param Object $payload - Payload to send
	 * @return String - The response
	 */
	protected function jsonResponse($code = 200, $payload = null)
	{
		// set response
		$response = [
			"code"   => (string)$code,
			"status" => $code == 200 ? "ok" : "error"
		];

		// success data
		if ($code == 200) {

			// serialize object?
			if (is_object($payload))
				$payload = json_decode(json_encode($payload), true);

			// redirect or payload
			if (!empty($payload["redirect"]))
				$response["redirect"] = $payload["redirect"];
			else
				$response["payload"] = $payload;
		}
		// error data
		else {

			$response["error"]   = $this->RCODES[$code] ?? 400;
			$response["message"] = $payload;
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
	protected function sendFileToBuffer($data = null, $mime_type = "text/plain")
	{
		if ($this->di->has("view"))
			$this->view->disable(); // disable view output

		$this->response->setStatusCode(200, "OK");
		$this->response->setHeader("Access-Control-Allow-Origin", "*");
		$this->response->setContentType($mime_type);
		// content must be set after content type
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
