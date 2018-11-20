<?php
/**
 * ReCaptcha Helper
 * @link https://github.com/google/recaptcha
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Helpers;

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
		if (empty($secret_key))
			throw new Exception("ReCaptcha Helper -> Google reCaptcha key is required.");

		// set secret key
		$this->recaptcha = new \ReCaptcha\ReCaptcha($secret_key);
	}

	/**
	 * Verifies that recaptcha value is valid with Google reCaptcha API
	 * @param String $token - The reCaptcha client response token
	 * @param String $action - The action to validate
	 * @param String $score - The score threshold
	 * @return Boolean
	 */
	public function isValid($token = null, $action = "", $score = 0.3)
	{
		if (empty($token)) return false;

		$di = \Phalcon\DI::getDefault();

		// get remote address
		$ip = $di->getShared("request")->getServerAddress();

		// verify response
		$response = $this->recaptcha->setExpectedAction($action)
									->setScoreThreshold($score)
									->verify($token ?? "", $ip);

		$di->getShared("logger")->debug("ReCaptcha Helper -> reCaptcha response: ".json_encode($response->toArray()));

		return $response->isSuccess();
	}
}
