<?php
/**
 * WS Core Controller
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

use CrazyCake\Phalcon\App;

/**
 * Common functions for API WS
 */
abstract class WsCore extends BaseCore
{
	/**
	 * Header API Key name
	 * @var String
	 */
	const HEADER_API_KEY = "API-KEY";

	/**
	 * Welcome message for API server status
	 */
	abstract protected function welcome();

	/**
	 * Not found service catcher
	 */
	public function serviceNotFound()
	{
		$this->jsonResponse(404);
	}

	/**
	 * Unauthorize service (API KEY)
	 */
	public function unauthorize()
	{
		$this->jsonResponse(498);
	}

	/**
	 * API key Validation
	 */
	public static function validateApiKey()
	{
		$di      = \Phalcon\DI::getDefault();
		$config  = $di->getShared("config");
		$request = $di->getShared("request");

		if (empty($config->key))
			return true;

		// check if keys are equal
		return $config->key === $request->getHeader(self::HEADER_API_KEY);
	}
}
