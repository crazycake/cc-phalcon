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
        // simple input validation
        if (empty($options["base_url"]))
            throw new Exception("Requester::newRequest -> base_url & uri method params are required.");

        if (empty($options["uri"]))     $options["uri"]     = "";
        if (empty($options["payload"])) $options["payload"] = "";

        // set method, default is GET, value is uppercased
        $options["method"] = empty($options["method"]) ? "GET" : strtoupper($options["method"]);

		// get URL parts
        $url_parts = parse_url($options["base_url"].$options["uri"]);

		// merge options
		$options = array_merge($options, $url_parts);
		// sd($options);

        $this->logger->debug("Requester::newRequest -> Options: ".json_encode($options, JSON_UNESCAPED_SLASHES));

        try {
            // socket async call?
            if (!empty($options["socket"]) && $options["socket"] === true)
                return $this->_socketAsync($options);

			// guzzle options
            $guzzle_options = [
                "base_uri" => $options["base_url"],
                "timeout"  => self::$REQUEST_TIMEOUT
            ];

            $client = new GuzzleClient($guzzle_options);

            // reflection function
            $action = "_".strtolower($options["method"])."Request";

            return $this->$action($client, $options);
        }
        catch (Exception $e)  { $exception = $e; }
        catch (\Exception $e) { $exception = $e; }

        // log error
        $di = \Phalcon\DI::getDefault();
        $di->getShared("logger")->error("Requester::newRequest -> Options: ".json_encode($options, JSON_UNESCAPED_SLASHES).", Exception: ".$exception->getMessage().
                                        "\n".$exception->getLine()." ".$e->getFile());
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
        $verify_host = (!empty($options["verify_host"]) && $options["verify_host"]) ? 2 : false;    //prod_recommended: 2
        $verify_peer = (!empty($options["verify_host"]) && $options["verify_host"]) ? true : false; //prod_recommended: true

        $guzzle_options = [
            "curl" => [
                CURLOPT_SSL_VERIFYHOST => $verify_host,
                CURLOPT_SSL_VERIFYPEER => $verify_peer
            ]
        ];

        //set headers?
        if (!empty($options["headers"]))
            $guzzle_options["headers"] = $options["headers"];

        //check params
        if (isset($options["query-string"]) && $options["query-string"]) {
            $params = http_build_query($options["payload"]);
        }
        else {
            $params = "/".$options["payload"];
        }

        //set promise
        $promise = $client->requestAsync("GET", $options["uri"].$params, $guzzle_options);
        //send promise
        $this->_sendPromise($promise, $options["uri"]);
    }

    /**
     * Do a POST request
     * @param object $client - The HTTP Guzzle client
     * @param array $options - The input options
     */
    private function _postRequest($client, $options = [])
    {
        //curl options
        $verify_host = (!empty($options["verify_host"]) && $options["verify_host"]) ? 2 : false;
        $verify_peer = (!empty($options["verify_host"]) && $options["verify_host"]) ? true : false;
        //form params
        $form_params = is_array($options["payload"]) ? $options["payload"] : ["payload" => $options["payload"]];

        $guzzle_options = [
            "form_params" => $form_params,
            "curl" => [
                CURLOPT_SSL_VERIFYHOST => $verify_host,
                CURLOPT_SSL_VERIFYPEER => $verify_peer
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
        $this->_sendPromise($promise, $options);
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

            if (method_exists($body, "getContents"))
                $body = $body->getContents();

            //handle response (OK status)
            if ($response->getStatusCode() == 200 && strpos($body, "<!DOCTYPE") === false) {

                $logger->debug("Requester::_sendPromise -> response: $body \nOptions: ".json_encode($options, JSON_UNESCAPED_SLASHES));
            }
            else {

                if (isset($this->router)) {
                    $controller_name = $this->router->getControllerName();
                    $action_name     = $this->router->getActionName();
                    $logger->error("Requester::_sendPromise -> Error on request: $controller_name -> $action_name. Options: ".json_encode($options, JSON_UNESCAPED_SLASHES));
                }
                else {
                    $logger->error("Requester::_sendPromise -> An Error occurred on request. Options: ".json_encode($options, JSON_UNESCAPED_SLASHES));
                }

                //catch response for app errors
                if (strpos($body, "<!DOCTYPE") === false)
                    $logger->debug("Requester::_sendPromise -> Catched response: $body \nOptions: ".json_encode($options, JSON_UNESCAPED_SLASHES));
                else
                    $logger->debug("Requester::_sendPromise -> NOTE: Above response is a redirection webpage, check correct route and redirections.");
            }
        });
        //force promise to be completed
        $promise->wait();
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
        if ($options["method"] == "GET") {
            $options["path"] .= $options["payload"];
            $length = 0;
        }
        else {

            //create a concatenated string
            if (is_array($options["payload"])) {
                $options["payload"] = http_build_query($options["payload"], "","&");
            }
            //default behavior
            else {
                $options["payload"] = "payload=".$options["payload"];
            }

            $length = strlen($options["payload"]);
        }

        //set output
        $out = $options["method"]." ".$options["path"]." HTTP/1.1\r\n";
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
        if ($options["method"] == "POST" && !empty($options["payload"])) {
            $out .= $options["payload"];
        }

        $this->logger->debug("Requester::_socketAsync -> sending out request ".print_r($out, true));

        fwrite($socket, $out);
        usleep(300000); //0.3s
        fclose($socket);
    }
}
