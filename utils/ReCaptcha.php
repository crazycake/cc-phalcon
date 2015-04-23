<?php
/**
 * ReCaptcha Helper
 * Requires: Curl Extension with SSL protocol handler
 * @author Jesse G. Donat <donatj@gmail.com>
 * @contributor Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Utils;

//imports
use Phalcon\Exception;

class ReCaptcha
{
    /* consts */
    const GOOGLE_RECAPTCHA_API_URL = "https://www.google.com/recaptcha/api/siteverify";

    /**
     * Google recaptcha secret key
     * @var string
     */
    private $secret_key;

    /**
     * Constructor
     * @param string $secret_key
     * @throws \Phalcon\Exception
     */
    public function __construct($secret_key = null)
    {
        if (is_null($secret_key))
            throw new Exception("ReCaptcha Helper -> Google reCaptcha key is required.");

        //set secret key
        $this->secret_key = $secret_key;
        //set ip adress
        //$this->ip_address = $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Verifies that recaptcha value is valid with Google reCaptcha API
     * @param string $response
     * @throws Exception
     * @return bool
     */
    public function checkResponse($response)
    {
        //set URL
        $url = self::GOOGLE_RECAPTCHA_API_URL . "?secret=" . $this->secret_key . "&response=" . $response; //."&remoteip=".$this->ip_address;

        //prepare the request
        $curl = curl_init();
        //set options
        curl_setopt_array($curl, array(
            CURLOPT_URL            => $url, // Set URL
            CURLOPT_RETURNTRANSFER => true, // Wait for response
            CURLOPT_TIMEOUT        => 10, // TimeOut seconds
            CURLOPT_SSL_VERIFYPEER => false, // Disable SSL verification
            CURLOPT_USERAGENT      => 'Phalcon-Curl', // UserAgent
        ));

        //execute request
        $response   = curl_exec($curl);
        $curl_error = curl_error($curl);
        //close curl connection
        curl_close($curl);

        if (!empty($curl_error))
            throw new Exception("ReCaptcha Helper -> An error ocurred in curl connection: $curl_error.");

        $response = json_decode($response, true);

        //check response
        if (isset($response["success"]))
            return $response["success"];
        else
            return false;
    }
}
