<?php
/**
 * Guzzle Trait
 * Requires a Frontend or Backend Module with CoreController
 * Requires Guzzle library (composer)
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Services;

//imports
use Phalcon\Exception;
use GuzzleHttp\Client as GuzzleClient;  //Guzzle client for requests
use GuzzleHttp\Promise;

/**
 * Guzzle HTTP Request Handler
 */
trait Guzzle
{
    /** static vars */
    protected static $REQUEST_TIMEOUT     = 30.0;
    protected static $SOCKET_DEFAULT_PORT = 80;

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
    protected function _newRequest($options = array())
    {
        //simple input validation
        if (empty($options["base_url"]) || empty($options["uri"]))
            throw new Exception("Guzzle::newRequest -> base_url & uri method params are required.");

        if(empty($options["payload"]))
            $options["payload"] = "";

        //set method, default is GET, value is uppercased
        $options["method"] = empty($options["method"]) ? "GET" : strtoupper($options["method"]);

        try {
            //socket async call?
            if(!empty($options["socket"]) && $options["socket"] === true)
                return $this->_socketAsync($options);

            $guzzle_options = [
                'base_uri' => $options["base_url"],
                'timeout'  => self::$REQUEST_TIMEOUT
            ];

            $client = new GuzzleClient($guzzle_options);

            //reflection function
            $action = "_".strtolower($options["method"])."Request";
            return $this->$action($client, $options);
        }
        catch(Exception $e)  { $exception = $e; }
        catch(\Exception $e) { $exception = $e; }

        //log error
        $di = \Phalcon\DI::getDefault();
        $di->getShared('logger')->error("Guzzle::newRequest -> Options: ".json_encode($options, JSON_UNESCAPED_SLASHES).", Exception: ".$exception->getMessage().
                                        "\n".$exception->getLine()." ".$e->getFile());
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Do a GET request
     * @param object $client - The HTTP Guzzle client
     * @param array $options - The input options
     */
    private function _getRequest($client, $options = array())
    {
        //curl options
        $verify_host = (APP_ENVIRONMENT != "production") ? false : 2; //prod_recommended: 2
        $verify_peer = (APP_ENVIRONMENT != "production") ? false : true; //prod_recommended: true

        $guzzle_options = [
            'curl' => [
                CURLOPT_SSL_VERIFYHOST => $verify_host,
                CURLOPT_SSL_VERIFYPEER => $verify_peer
            ]
        ];

        //set headers?
        if(!empty($options["headers"]))
            $guzzle_options["headers"] = $options["headers"];


        //check params
        $params = "/".$options["payload"];

        if(isset($options["query-string"]) && $options["query-string"])
            $params = http_build_query($options["payload"]);

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
    private function _postRequest($client, $options = array())
    {
        //curl options
        $verify_host = (APP_ENVIRONMENT != "production") ? false : 2;
        $verify_peer = (APP_ENVIRONMENT != "production") ? false : true;

        $guzzle_options = [
            'form_params' => ["payload" => $options["payload"]],
            'curl' => [
                CURLOPT_SSL_VERIFYHOST => $verify_host,
                CURLOPT_SSL_VERIFYPEER => $verify_peer
            ]
        ];

        //set headers?
        if(!empty($options["headers"]))
            $guzzle_options["headers"] = $options["headers"];

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
    private function _sendPromise($promise = null, $options = array())
    {
        //handle promise
        $promise->then(function ($response) use ($options) {

            //set logger
            $di = \Phalcon\DI::getDefault();
            $logger = $di->getShared('logger');

            $body = $response->getBody();

            if(method_exists($body, "getContents"))
                $body = $body->getContents();

            //handle response (OK status)
            if ($response->getStatusCode() == 200 && strpos($body, "<!DOCTYPE") === false) {
                $logger->debug("Guzzle::_sendPromise -> Uri: ".$options["uri"].", response: $body");
            }
            else {

                if(isset($this->router)) {
                    $controllerName = $this->router->getControllerName();
                    $actionName     = $this->router->getActionName();
                    $logger->error("Guzzle::_sendPromise -> Error on request (".$options["uri"]."): $controllerName -> $actionName");
                }
                else {
                    $logger->error("Guzzle::_sendPromise -> An Error occurred on request, uri: ".$options["uri"]);
                }

                //catch response for app errors
                if (strpos($body, "<!DOCTYPE") === false)
                    $logger->debug("Guzzle::_sendPromise -> Catched response: $body");
                else
                    $logger->debug("Guzzle::_sendPromise -> NOTE: Above response is a redirection webpage, check correct route and redirections.");
            }
        });
        //force promise to be completed
        $promise->wait();
    }

    /**
     * Simulates a socket async request without waiting for response
     * @param array $options - The input options
     */
    private function _socketAsync($options = array())
    {
        //full URL
        $parts = parse_url($options["base_url"].$options["uri"]);

        /*if($parts["sheme"] == "https")
            $default_port = 443; //SSL*/

        // set socket to be opened
        $socket = fsockopen(
            $parts['host'],
            isset($parts['port']) ? $parts['port'] : self::$SOCKET_DEFAULT_PORT,
            $errno,
            $errstr,
            self::$REQUEST_TIMEOUT
        );

        // Data goes in the path for a GET request
        if($options["method"] == "GET") {
            $parts['path'] .= $options["payload"]; //normal would be a ? symbol with & delimeter
            $length = 0;
        }
        else {
            $options["payload"] = "payload=".$options["payload"];
            $length = strlen($options["payload"]);
        }

        //set output
        $out = $options["method"]." ".$parts['path']." HTTP/1.1\r\n";
        $out .= "Host: ".$parts['host']."\r\n";
        $out .= "User-Agent: AppLocalServer\r\n";
        $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $out .= "Content-Length: ".$length."\r\n";

        //set headers
        if(!empty($options["headers"])) {

            foreach ($options["headers"] as $header => $value)
                $out .= $header.": ".$value."\r\n";
        }

        //closer
        $out .= "Connection: Close\r\n\r\n";

        // Data goes in the request body for a POST request
        if ($options["method"] == "POST" && !empty($options["payload"])) {
            $out .= $options["payload"];
        }
        //$this->logger->debug("Guzzle::_socketAsync -> sending out request ".print_r($out, true));

        fwrite($socket, $out);
        fclose($socket);
    }
}
