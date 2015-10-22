<?php
/**
 * Guzzle Trait
 * Requires a Frontend or Backend Module with CoreController
 * Requires Guzzle library (composer)
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Traits;

//imports
use Phalcon\Exception;
use GuzzleHttp\Client as GuzzleClient;  //Guzzle client for requests
use GuzzleHttp\Promise;

trait Guzzle
{
	//....

    /* --------------------------------------------------- § -------------------------------------------------------- */

	/**
     * Do a asynchronously request through Guzzle
     * @param string $url The request base URL
     * @param string $uri The request URI
     * @param array $data The encrypted string params data
     * @param string $method The HTTP method (GET, POST)
     * @param string $sockets Makes async call as socket connection
     */
    protected function _sendAsyncRequest($url = null, $uri = null, $data = null, $method = "GET", $socket = false)
    {
        //simple input validation
        if (is_null($url) || is_null($uri))
            throw new Exception("Guzzle::sendAsyncRequest -> url & uri method params are required.");

        if(is_null($data))
            $data = "";

        //socket async call?
        if($socket) {
            $this->_socketAsync($url.$uri, $data, $method);
            return;
        }

        $exception = false;

        try {
            $client = new GuzzleClient(['base_uri' => $url, 'timeout' => 30.0]);

            //reflection function
            $action = "_".strtolower($method)."Request";
            $this->$action($client, $uri, $data);
        }
        catch(Exception $e)  { $exception = $e; }
        catch(\Exception $e) { $exception = $e; }

        if($exception) {
            $di = DI::getDefault();
            $di->getShared('logger')->error("Guzzle::sendAsyncRequest -> Exception: ".$exception->getMessage());
        }
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Do a GET request
     * @param  object $client The HTTP Guzzle client
     * @param  string $uri   The URI
     * @param  object $data  The encrypted string params data
     */
    private function _getRequest($client, $uri, $data)
    {
        //curl options
        $verify_host = (APP_ENVIRONMENT != "production") ? false : 2;
        $verify_peer = (APP_ENVIRONMENT != "production") ? false : true;

        $promise = $client->getAsync("$uri/$data", [
            'curl' => [
                CURLOPT_SSL_VERIFYHOST => $verify_host,
                CURLOPT_SSL_VERIFYPEER => $verify_peer
            ]
        ]);

        //send promise
        $this->_sendPromise($promise, $uri);
    }

    /**
     * Do a POST request
     * @param  object $client The HTTP Guzzle client
     * @param  string $uri   The URI
     * @param  object $data  The encrypted string params data
     */
    private function _postRequest($client, $uri, $data)
    {
        //curl options
        $verify_host = (APP_ENVIRONMENT != "production") ? false : 2;
        $verify_peer = (APP_ENVIRONMENT != "production") ? false : true;

        $promise = $client->postAsync($uri, [
            'form_params' => ["payload" => $data],
            'curl' => [
                CURLOPT_SSL_VERIFYHOST => $verify_host,
                CURLOPT_SSL_VERIFYPEER => $verify_peer
            ]
        ]);

        //send promise
        $this->_sendPromise($promise, $uri);
    }

    /**
     * Logs Guzzle promise response
     * @param object $promise
     * @param string $method
     */
    private function _sendPromise($promise, $uri)
    {
        if(is_null($uri))
            $uri = "uknown";

        $promise->then(function ($response) use ($uri) {

            $body = $response->getBody();

            if(method_exists($body, "getContents")) {
                $body = $body->getContents();
            }

            //handle response (OK status)
            if ($response->getStatusCode() == 200 && strpos($body, "<!DOCTYPE") === false) {
                $this->logger->log("Guzzle::logGuzzleResponse -> Uri: $uri, response: $body");
            }
            else {

                if(isset($this->router)) {
                    $controllerName = $this->router->getControllerName();
                    $actionName     = $this->router->getActionName();
                    $this->logger->error("Guzzle::logGuzzleResponse -> Error on request ($uri): $controllerName -> $actionName");
                }
                else {
                    $this->logger->error("Guzzle::logGuzzleResponse -> An Error occurred on request: $uri");
                }

                //catch response for app errors
                if (strpos($body, "<!DOCTYPE") === false)
                    $this->logger->log("Guzzle::logGuzzleResponse -> Catched response: $body");
                else
                    $this->logger->log("Guzzle::logGuzzleResponse -> NOTE: Above response is a redirection webpage, check correct route and redirections.");
            }
        });
        //force promise to be completed
        $promise->wait();
    }

    /**
     * Simulates a socket async request without waiting for response
     * @param  string $url The URL
     * @param  array $data The encrypted string params data
     * @param  string $method The HTTP Method [GET or POST]
     */
    private function _socketAsync($url = "", $data = "", $method = "GET")
    {
        $exception = false;

        try {

            $parts = parse_url($url);

            $default_port = 80;

            /*if($parts["sheme"] == "https")
                $default_port = 443; //SSL*/

            // set socket to be opened
            $socket = fsockopen(
                $parts['host'],
                isset($parts['port']) ? $parts['port'] : $default_port,
                $errno,
                $errstr,
                30
            );

            // Data goes in the path for a GET request
            if($method == 'GET') {
                $parts['path'] .= $data; //normal would be a ? symbol with & delimeter
                $length = 0;
            }
            else {
                $data = "payload=".$data;
                $length = strlen($data);
            }

            $out = "$method ".$parts['path']." HTTP/1.1\r\n";
            $out .= "Host: ".$parts['host']."\r\n";
            $out .= "User-Agent: AppLocalServer\r\n";
            $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $out .= "Content-Length: ".$length."\r\n";
            $out .= "Connection: Close\r\n\r\n";

            // Data goes in the request body for a POST request
            if ($method == 'POST' && isset($data))
                $out .= $data;

            fwrite($socket, $out);
            fclose($socket);
        }
        catch(Exception $e)  { $exception = $e; }
        catch(\Exception $e) { $exception = $e; }

        if($exception) {
            $di = DI::getDefault();
            $di->getShared('logger')->error("Guzzle::_socketAsync -> Exception ($url): ".$exception->getMessage());
        }
    }
}
