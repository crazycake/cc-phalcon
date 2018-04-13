<?php
/**
 * ReCaptcha Helper
 * @link https://github.com/google/recaptcha
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Helpers;

//imports
use Phalcon\Exception;

/**
 * ReCaptcha Helper
 */
class ReCaptcha
{
	/**
	 * Google recaptcha client
	 * @var Object
	 */
	protected $recaptcha;

	/**
	 * Constructor
	 * @param String $secret_key - The reCaptcha secret key
	 * @throws Exception
	 */
	public function __construct($secret_key = null)
	{
		if (is_null($secret_key))
			throw new Exception("ReCaptcha Helper -> Google reCaptcha key is required.");

		//set secret key
		$this->recaptcha = new \ReCaptcha\ReCaptcha($secret_key);
	}

	/**
	 * Verifies that recaptcha value is valid with Google reCaptcha API
	 * @param String $gRecaptchaResponse - The reCaptcha response
	 * @return Boolean
	 */
	public function isValid($gRecaptchaResponse = null)
	{
		//get DI instance (static)
		$di = \Phalcon\DI::getDefault();

		if (empty($gRecaptchaResponse))
			return false;

		//get remote address
		$ip = $di->getShared("request")->getServerAddress();
		//verify response
		$response = $this->recaptcha->verify($gRecaptchaResponse, $ip);

		if ($response->isSuccess())
			return true;

		$errors = $response->getErrorCodes();

		if ($di->getShared("logger"))
			$di->getShared("logger")->error("ReCaptcha Helper -> Invalid reCaptcha response: ".json_encode($errors));

		return false;
	}
}
