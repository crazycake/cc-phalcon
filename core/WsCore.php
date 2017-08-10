<?php
/**
 * WS Core Controller
 * Core Webservice controller, basic and helper methods for child controllers.
 * Requires a Phalcon DI Factory Services
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

//imports
use Phalcon\Exception;
//core
use CrazyCake\Phalcon\App;

/**
 * Common functions for API WS
 */
abstract class WsCore extends BaseCore
{
	/**
	 * Header API Key name
	 * @var string
	 */
	const HEADER_API_KEY = "API-KEY";

	/**
	 * Webservice response cache path
	 * @var string
	 */
	const WS_RESPONSE_CACHE_PATH = STORAGE_PATH."cache/response/";

	/**
	 * Welcome message for API server status
	 * @return json response
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
	 * API key Validation
	 */
	public static function validateApiKey()
	{
		$di      = \Phalcon\DI::getDefault();
		$config  = $di->getShared("config");
		$request = $di->getShared("request");

		if (empty($config->key))
			return true;

		//check if keys are equal
		return $config->key === $request->getHeader(self::HEADER_API_KEY);
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Handles id property validation from a given object
	 * @param string $prop - The object property name
	 * @param boolean $optional - Parameter optional flag
	 * @param boolean $method - HTTP method, default is GET
	 * @return mixed [object|boolean]
	 */
	protected function handleIdInput($prop = "object_id", $optional = false, $method = "GET")
	{
		$scheme = explode("_", strtolower($prop));
		//unset last prop
		array_pop($scheme);
		//get class name
		$class_name = \Phalcon\Text::camelize(implode($scheme, "_"));

		$p = $optional ? "@" : "";
		//get request param
		$data = $this->handleRequest([
			"$p$prop"  => "int"
		], $method);

		$value = isset($data[$prop]) ? $data[$prop] : null;

		//get model data
		$object = empty($value) ? null : $class_name::findFirst(["id = ?1", "bind" => [1 => $value]]);

		if (!$optional && !$object)
			$this->jsonResponse(400);
		else
			return $object;
	}

	/**
	 * Validate search number & offset parameters
	 * @param int $input_num - Input number
	 * @param int $input_off - Input offset
	 * @param int $max_num - Maximum number
	 * @return array
	 */
	protected function handleLimitInput($input_num = null, $input_off = null, $max_num = null)
	{
		if (!is_null($input_num)) {
			$number = $input_num;
			$offset = $input_off;

			if ($number < 0 || !is_numeric($number))
				$number = 0;

			if ($offset < 0 || !is_numeric($offset))
				$offset = 1;

			if ((empty($number) && $max_num >= 1) || ($number > $max_num))
				$number = $max_num;
		}
		else {
			$number = empty($max_num) ? 1 : $max_num;
			$offset = 0;
		}

		return ["number" => $number, "offset" => $offset];
	}

	/**
	 * Handles a cache response
	 * @param string $key - The key for saving cached data
	 * @param mixed $data - The data to be cached or served
	 * @param boolean $bust - Forces a cache update
	 */
	protected function handleCacheResponse($key = "response", $data = null, $bust = false)
	{
		//prepare input data
		$hash = sha1($key);

		if (empty($hash) || empty($data))
			$this->jsonResponse(800);

		$json_file = self::WS_RESPONSE_CACHE_PATH."$hash.json";

		//get data for API struct
		if (!$bust && is_file($json_file)) {
			$this->sendFileToBuffer(file_get_contents($json_file));
			return;
		}

		//check dir
		if (!is_dir(self::WS_RESPONSE_CACHE_PATH))
			mkdir(self::WS_RESPONSE_CACHE_PATH, 0775);

		//save file to disk
		file_put_contents($json_file, json_encode($data, JSON_UNESCAPED_SLASHES));

		//send response
		$this->jsonResponse(200, $data);
	}

	/**
	 * Cleans json cache files
	 */
	protected function cleanCacheResponse()
	{
		if (!is_dir(self::WS_RESPONSE_CACHE_PATH))
			return;

		foreach (glob(self::WS_RESPONSE_CACHE_PATH."/*.json") as $filename) {

			if (is_file($filename))
				unlink($filename);
		}
	}
}
