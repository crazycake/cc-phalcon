<?php
/**
 * Requester Trait - Can make HTTP requests
 * Requires CoreController, Guzzle library (composer)
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Controllers;

//imports
use Phalcon\Exception;
//core
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

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Do a asynchronously request through Guzzle
	 * @param Array $options - Options:
	 * + base_url: The request base URL
	 * + uri: The request URI
	 * + payload: The encrypted string params data
	 * + method: The HTTP method (GET, POST)
	 * + socket: Makes async call as socket connection
	 * @return Object - The request object
	 */
	protected function newRequest($options = [])
	{
		$options = array_merge([
			"base_url"     => "",
			"uri"          => "",
			"payload"      => "",
			"method"       => "GET",
			"socket"       => false,
			"encrypt"      => false,
			"verify_host"  => false,
			"query-string" => false,
			"timeout"      => self::$REQUEST_TIMEOUT
		], $options);

		try {

			// default base url
			if(empty($options["base_url"]))
				$options["base_url"] = $this->baseUrl();

			// merge options with parsed URL
			$url_pieces = parse_url($options["base_url"].$options["uri"]);

			if (!empty($url_pieces))
				  $options = array_merge($options, $url_pieces);

			$this->logger->debug("Requester::newRequest -> Options: ".json_encode($options, JSON_UNESCAPED_SLASHES));

			// encrypt payload?
			if (!empty($this->cryptify) && !empty($options["payload"]) && $options["encrypt"])
				$options["payload"] = $this->cryptify->encryptData($options["payload"]);

			// socket async call?
			if ($options["socket"])
				return $this->_socketAsync($options);

			// reflection method (get or post)
			$action = "_".strtolower($options["method"])."Request";

			return $this->$action($options);
		}
		catch (\Exception | Exception $e) { $ex = $e; }

		$this->logger->error("Requester::newRequest -> Failed request: ".$ex->getMessage()."\n".$ex->getLine()." ".$ex->getFile().
							 "\nOptions: ".json_encode($options, JSON_UNESCAPED_SLASHES));

		return null;
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Do a GET request
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
		
		//curl options
		$guzzle_options = [
			"curl" => [
				CURLOPT_SSL_VERIFYHOST => $options["verify_host"] ? 2 : false, // prod_recommended: 2
				CURLOPT_SSL_VERIFYPEER => $options["verify_host"]              // prod_recommended: true
			]
		];

		//set headers?
		if (!empty($options["headers"]))
			$guzzle_options["headers"] = $options["headers"];

		//check params for query strings
		$query_string = $options["query-string"] || is_array($options["payload"]);

		$params = $query_string ? "?".http_build_query($options["payload"]) : "/".$options["payload"];
		
		$this->logger->debug("Requester::_getRequest [".$options["uri"]."] options: ".print_r($guzzle_options, true));

		//new promise
		$response = $client->request("GET", $options["uri"].$params, $guzzle_options);

		$body = $response->getBody();

		$this->logger->debug("Requester::_getRequest -> OK, received response [".$response->getStatusCode()."] length:  ".strlen($body).
												  "\nHeaders: ".json_encode($response->getHeaders(), JSON_UNESCAPED_SLASHES));

		return (string)$body;
	}

	/**
	 * Do a POST request
	 * @param Array $options - The input options
	 * @return Object - The promise object
	 */
	private function _postRequest($options = [])
	{
		// set guzzle instance
		$client = new \GuzzleHttp\Client([
			"base_uri" => $options["base_url"],
			"timeout"  => $options["timeout"]
		]);

		//curl options
		$guzzle_options = [
			"form_params" => $options["payload"],
			"curl" => [
				CURLOPT_SSL_VERIFYHOST => $options["verify_host"] ? 2 : false, // prod_recommended: 2
				CURLOPT_SSL_VERIFYPEER => $options["verify_host"]              // prod_recommended: true
			]
		];

		//set headers?
		if (!empty($options["headers"]))
			$guzzle_options["headers"] = $options["headers"];

		//set body?
		if (!empty($options["body"]))
			$guzzle_options["body"] = $options["body"];

		$this->logger->debug("Requester::_postRequest [".$options["uri"]."] options: ".print_r($guzzle_options, true));

		$response = $client->request("POST", $options["uri"], $guzzle_options);

		$body = $response->getBody();

		$this->logger->debug("Requester::_postRequest -> OK, received response [".$response->getStatusCode()."] length:  ".strlen($body).
												   "\nHeaders: ".json_encode($response->getHeaders(), JSON_UNESCAPED_SLASHES));

		return (string)$body;
	}


	/**
	 * Simulates a socket async request without waiting for response
	 * @param Array $options - The input options
	 */
	private function _socketAsync($options = [])
	{
		$ssl      = $options["scheme"] == "https";
		$protocol = $ssl ? "ssl://" : "";

		//set socket to be opened
		$socket = fsockopen(
			$protocol.$options["host"],
			$options["port"] ?? ($ssl ? 443 : 80),
			$errno,
			$errstr,
			$options["timeout"]
		);

		// Data goes in the path for a GET request
		if (strtoupper($options["method"]) == "GET") {

			$options["path"] .= $options["payload"];
			$length = 0;
		}
		else {

			//query string or body content
			if (is_array($options["payload"]))
				$options["payload"] = http_build_query($options["payload"], "","&");
			else
				$options["payload"] = "payload=".$options["payload"];

			$length = strlen($options["payload"]);
		}

		//set output
		$out = strtoupper($options["method"])." ".$options["path"]." HTTP/1.1\r\n";
		$out .= "Host: ".$options["host"]."\r\n";
		$out .= "User-Agent: AppLocalServer\r\n";
		$out .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$out .= "Content-Length: ".$length."\r\n";

		//set headers
		if (!empty($options["headers"])) {

			foreach ($options["headers"] as $header => $value)
				$out .= $header.": ".$value."\r\n";
		}

		//closer
		$out .= "Connection: Close\r\n\r\n";

		// Data goes in the request body for a POST request
		if (strtoupper($options["method"]) == "POST" && !empty($options["payload"]))
			$out .= $options["payload"];

		$this->logger->debug("Requester::_socketAsync -> sending out request ".print_r($out, true));

		fwrite($socket, $out);
		usleep(300000); //0.3s
		fclose($socket);
	}
}
