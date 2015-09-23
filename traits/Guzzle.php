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
     * @param array $data The parameters data
     * @param string $method The HTTP method (GET, POST)
     */
    protected function _sendAsyncRequest($url = null, $uri = null, $data = array(), $method = "GET")
    {
        //simple input validation
        if (is_null($url) || is_null($uri))
            throw new Exception("Guzzle::sendAsyncRequest -> url & uri method params are required.");

        $client = new GuzzleClient(['base_uri' => $url, 'timeout' => 30.0]);

        //reflection function
        $action = "_".strtolower($method)."Request";
        $this->$action($client, $uri, $data);
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Do a GET request
     * @param  object $client The HTTP Guzzle client
     * @param  string $uri   The URI
     * @param  object $data   The param data
     */
    private function _getRequest($client, $uri, $data)
    {
        $promise = $client->getAsync("$uri/$data");

        $this->_sendPromise($promise, $uri);
    }

    /**
     * Do a POST request
     * @param  object $client The HTTP Guzzle client
     * @param  string $uri   The URI
     * @param  object $data   The param data
     */
    private function _postRequest($client, $uri, $data)
    {
        $promise = $client->postAsync($uri, [
            'form_params' => ["payload" => $data]
        ]);

        //logs response
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
}
