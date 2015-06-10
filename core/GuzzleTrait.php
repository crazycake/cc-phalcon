<?php
/**
 * Guzzle Trait
 * Requires a Frontend or Backend Module with CoreController
 * Requires Guzzle library (composer)
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

//imports
use GuzzleHttp\Client as GuzzleClient;  //Guzzle client for requests

trait GuzzleTrait
{
	//....

    /* --------------------------------------------------- § -------------------------------------------------------- */

	/**
     * Do a asynchronously request
     * @param string $url The request URL
     * @param string $method The request name
     */
    protected function sendAsyncRequest($url = null, $method = null)
    {
        //simple input validation
        if (empty($url))
            throw new \Exception("GuzzleTrait::sendAsyncRequest -> url method param is required.");

        $response = (new GuzzleClient())->get($url, ['future' => true]);
        $this->logGuzzleResponse($response, $method);
    }

    /**
     * Logs Guzzle response
     * @param object $response
     * @param string $method
     */
    protected function logGuzzleResponse($response, $method = "not specified")
    {
        //save response only for non production-environment
        if (APP_ENVIRONMENT === 'production')
            return;

        $response->then(function ($response) use ($method) {
            $body = $response->getBody();

            if(get_class($body) == "Stream")
                $body = $body->getContents();

            //handle response (OK status)
            if ($response->getStatusCode() == 200)
                $this->logger->log('GuzzleTrait::logGuzzleResponse -> Method: ' . $method . ', response:' . $body);
            else
                $this->logger->error('GuzzleTrait::logGuzzleResponse -> Error on request: ' . $method .', response: ' . $body);
        });
    }
}
