<?php
/**
 * Requester Trait - Can make HTTP requests.
 * Requires CoreController, Guzzle library (composer).
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Controllers;

use CrazyCake\Phalcon\App;

/**
 * HTTP Request Handler
 */
trait Requester
{
	/**
	 * Request timeout max value
	 * @var Float
	 */
	protected static $REQUEST_TIMEOUT = 30.0;

	/**
	 * Do a asynchronously request through Guzzle
	 * @param Array $options - Options:
	 * + base_url: The request base URL
	 * + uri: The request URI
	 * + payload: The encrypted string params data
	 * + method: The HTTP method (GET, POST, PUT)
	 * + socket: Makes async call as socket connection
	 * @return Mixed
	 */
	protected function newRequest($options = [])
	{
		$options = array_merge([
			"base_url"     => "",
			"uri"          => "",
			"method"       => "GET",
			"socket"       => false,
			"encrypt"      => false,
			"payload"      => false,
			"verify_host"  => false,
			"timeout"      => self::$REQUEST_TIMEOUT
		], $options);

		try {

			// default base url
			if (empty($options["base_url"]))
				$options["base_url"] = APP_BASE_URL;

			// merge options with parsed URL
			$url_pieces = parse_url($options["base_url"].$options["uri"]);

			if (!empty($url_pieces))
				  $options = array_merge($options, $url_pieces);

			// encrypt payload?
			if ($options["encrypt"] && !empty($options["payload"]))
				$options["payload"] = $this->cryptify->encryptData($options["payload"]);

			$this->logger->debug("Requester::newRequest -> Options: ".json_encode($options, JSON_UNESCAPED_SLASHES));

			// socket async call?
			if ($options["socket"])
				return $this->_socketRequest($options);

			// reflection method (get or post)
			$action = "_".strtolower($options["method"])."Request";

			return $this->$action($options);
		}
		catch (\Exception $e) {

			$this->logger->error("Requester::newRequest -> Failed request: ".$e->getMessage()."\nOptions: ".json_encode($options, JSON_UNESCAPED_SLASHES));

			return ["error" => $e->getMessage()];
		}
	}

	/**
	 * GET request
	 * @param Array $options - The input options
	 * @return Object - The promise object
	 */
	private function _getRequest($options = [])
	{
		// set guzzle instance
		$client = new \GuzzleHttp\Client([
			"base_uri" => $options["base_url"],
			"timeout"  => $options["timeout"]
		]);

		// curl options
		$guzzle_options = [
			"curl" => [
				CURLOPT_SSL_VERIFYHOST => $options["verify_host"] ? 2 : false, // prod_recommended: 2
				CURLOPT_SSL_VERIFYPEER => $options["verify_host"]              // prod_recommended: true
			]
		];

		// set headers?
		if (!empty($options["headers"]))
			$guzzle_options["headers"] = $options["headers"];

		// check params
		if (!empty($options["payload"]))
			$options["uri"] .= is_array($options["payload"]) ? "?".http_build_query($options["payload"]) : "/".$options["payload"];

		$this->logger->debug("\n--\nRequester::_getRequest [".$options["base_url"]."][".$options["uri"]."] guzzle_options:\n".json_encode($guzzle_options, JSON_UNESCAPED_SLASHES)."\n");

		// guzzle request
		$response = $client->request("GET", $options["uri"], $guzzle_options);

		$body = $response->getBody();

		$this->logger->debug("\n--\nRequester::_getRequest -> OK, received response [".$response->getStatusCode()."] length: ".strlen($body).
							 " -> preview: ".substr($body, 0, 300)." [".strlen($body)."]\nHeaders: ".json_encode($response->getHeaders(), JSON_UNESCAPED_SLASHES)."\n");

		return (string)$body;
	}

	/**
	 * POST request
	 * @param Array $options - The input options
	 * @return Object
	 */
	private function _postRequest($options = [])
	{
		// set guzzle instance
		$client = new \GuzzleHttp\Client([
			"base_uri" => $options["base_url"],
			"timeout"  => $options["timeout"]
		]);

		// curl options
		$guzzle_options = [
			"curl" => [
				CURLOPT_SSL_VERIFYHOST => $options["verify_host"] ? 2 : false, // prod_recommended: 2
				CURLOPT_SSL_VERIFYPEER => $options["verify_host"]              // prod_recommended: true
			]
		];

		// set headers?
		if (!empty($options["headers"]))
			$guzzle_options["headers"] = $options["headers"];

		// json body
		if (!empty($options["json"]))
			$guzzle_options["json"] = $options["json"];

		// params
		if (!empty($options["payload"]))
			$guzzle_options["form_params"] = $options["payload"];

		$this->logger->debug("\n--\nRequester::_postRequest [".$options["base_url"]."][".$options["uri"]."] guzzle_options:\n".json_encode($guzzle_options, JSON_UNESCAPED_SLASHES)."\n");

		// guzzle request
		$response = $client->request(strtoupper($options["method"]), $options["uri"], $guzzle_options);

		$body = $response->getBody();

		$this->logger->debug("\n--\nRequester::_postRequest -> OK, received response [".$response->getStatusCode()."] length: ".strlen($body).
							 " -> preview: ".substr($body, 0, 300)." [".strlen($body)."]\nHeaders: ".json_encode($response->getHeaders(), JSON_UNESCAPED_SLASHES)."\n");

		return (string)$body;
	}

	/**
	 * PUT request
	 * @param Array $options - The input options
	 * @return Object
	 */
	private function _putRequest($options = [])
	{
		return $this->_postRequest($options);
	}

	/**
	 * Simulates a socket async request without waiting for response
	 * @param Array $options - The input options
	 */
	private function _socketRequest($options = [])
	{
		$ssl      = $options["scheme"] == "https";
		$protocol = $ssl ? "ssl://" : "";

		// set socket to be opened
		$socket = fsockopen(
			$protocol.$options["host"],
			$options["port"] ?? ($ssl ? 443 : 80),
			$errno,
			$errstr,
			$options["timeout"]
		);

		// data goes in the path for a GET request
		if (strtoupper($options["method"]) == "GET") {

			$options["path"] .= $options["payload"];
			$length = 0;
		}
		else {

			// as query string or body content
			$options["payload"] = is_array($options["payload"]) ? http_build_query($options["payload"], "", "&") : "payload=".$options["payload"];
			$length = strlen($options["payload"]);
		}

		// set headers
		$out  = strtoupper($options["method"])." ".$options["path"]." HTTP/1.1\r\n";
		$out .= "Host: ".$options["host"]."\r\n";
		$out .= "User-Agent: AppLocalServer\r\n";
		$out .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$out .= "Content-Length: ".$length."\r\n";

		if (!empty($options["headers"])) {

			foreach ($options["headers"] as $header => $value)
				$out .= $header.": ".$value."\r\n";
		}

		// close headers
		$out .= "Connection: Close\r\n\r\n";

		// data goes in the request body for a POST request
		if (strtoupper($options["method"]) == "POST" && !empty($options["payload"]))
			$out .= $options["payload"];

		$this->logger->debug("\n--\nRequester::_socketRequest -> sending out request:\n".json_encode($out, JSON_UNESCAPED_SLASHES)."\n");

		fwrite($socket, $out);
		usleep(300000); // 0.3s
		fclose($socket);
	}
}
