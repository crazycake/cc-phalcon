<?php
/**
 * Requester Trait - Can make HTTP requests
 * Requires a Frontend or Backend Module with BaseCore
 * Requires Guzzle library (composer)
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Controllers;

//imports
use Phalcon\Exception;
use GuzzleHttp\Client as GuzzleClient;  //Guzzle client for requests
use GuzzleHttp\Promise;
//core
use CrazyCake\Phalcon\App;

/**
 * HTTP Request Handler
 */
trait Requester
{
    /** static vars */
    protected static $REQUEST_TIMEOUT   = 30.0;
    protected static $HTTP_DEFAULT_PORT = 80;

    /* --------------------------------------------------- § -------------------------------------------------------- */

	/**
     * Do a asynchronously request through Guzzle
     * @param array $options - Options:
     * +base_url: The request base URL
     * +uri: The request URI
     * +payload: The encrypted string params data
     * +method: The HTTP method (GET, POST)
     * +socket: Makes async call as socket connection
     */
    protected function newRequest($options = [])
    {
        $options = array_merge([
            "base_url"     => "",
            "uri"          => "",
            "payload"      => "",
            "method"       => "GET",
            "socket"       => false,
            "verify_host"  => false,
            "query-string" => false
        ], $options);

        try {

    		// merge options with parsed URL
            $url_pieces = parse_url($options["base_url"].$options["uri"]);

            if(!empty($url_pieces))
    		      $options = array_merge($options, $url_pieces);

            $this->logger->debug("Requester::newRequest -> Options: ".json_encode($options, JSON_UNESCAPED_SLASHES));

            // socket async call?
            if ($options["socket"])
                return $this->_socketAsync($options);

			// guzzle instance
            $client = new GuzzleClient([
                "base_uri" => $options["base_url"],
                "timeout"  => self::$REQUEST_TIMEOUT
            ]);

            // reflection method (get or post)
            $action = "_".strtolower($options["method"])."Request";

            return $this->$action($client, $options);
        }
        catch (Exception $e)  { $exc = $e; }
        catch (\Exception $e) { $exc = $e; }

        // log error
        $di = \Phalcon\DI::getDefault();
        $di->getShared("logger")->error("Requester::newRequest -> Err: ".$exc->getMessage()."\n".$exc->getLine()." ".$e->getFile().". Options: ".print_r($options, true));

        return null;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Do a GET request
     * @param object $client - The HTTP Guzzle client
     * @param array $options - The input options
     */
    private function _getRequest($client, $options = [])
    {
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
        if ($options["query-string"] || is_array($options["payload"])) {
            $params = http_build_query($options["payload"]);
        }
        else {
            $params = "/".$options["payload"];
        }

        //set promise
        $promise = $client->requestAsync("GET", $options["uri"].$params, $guzzle_options);

        //send promise
        return $this->_sendPromise($promise, $options["uri"]);
    }

    /**
     * Do a POST request
     * @param object $client - The HTTP Guzzle client
     * @param array $options - The input options
     */
    private function _postRequest($client, $options = [])
    {
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

        //set promise
        $promise = $client->requestAsync("POST", $options["uri"], $guzzle_options);

        //send promise
        return $this->_sendPromise($promise, $options);
    }

    /**
     * Logs Guzzle promise response
     * @param object $promise - The promise object
     * @param array $options - The input options
     */
    private function _sendPromise($promise = null, $options = [])
    {
        //handle promise
        $promise->then(function ($response) use ($options) {

            //set logger
            $di = \Phalcon\DI::getDefault();
            $logger = $di->getShared("logger");

            $body = $response->getBody();
            $body = method_exists($body, "getContents") ? $body->getContents() : "";

			$logger->debug("Requester::_sendPromise -> response length: [".$response->getStatusCode()."] ".strlen($body));

            //catch response for app errors
            if (strpos($body, "<!DOCTYPE") !== false)
                $logger->debug("Requester::_sendPromise -> NOTE: Above response is a redirected page, check correct route and redirections.");
        });
        //force promise to be completed
        $promise->wait();

        return $promise;
    }

    /**
     * Simulates a socket async request without waiting for response
     * @param array $options - The input options
     */
    private function _socketAsync($options = [])
    {
        //set socket to be opened
        $socket = fsockopen(
            $options["host"],
            self::$HTTP_DEFAULT_PORT,
            $errno,
            $errstr,
            self::$REQUEST_TIMEOUT
        );
        //sd($options);

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
