<?php
/**
 * BaseCore, shared methods for WebCore & WsCore.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Core;

use Phalcon\Mvc\Controller;
use Phalcon\Exception;

use CrazyCake\Phalcon\App;
use CrazyCake\Controllers\Requester;
use CrazyCake\Controllers\Responser;

/**
 * Web Security Interface (optional, only for webcore)
 */
interface WebSecurity
{
	/**
	 * Check CSRF Token value
	 * @return Boolean
	 */
	public function checkCsrfToken();
}

/**
 * App Base Core
 */
abstract class BaseCore extends Controller
{
	use Requester;
	use Responser;

	/**
	 * Base URL extended function
	 * @param String $uri - A given URI
	 * @return String - The static URL
	 */
	protected function baseUrl($uri = "")
	{
		return APP_BASE_URL.$uri;
	}

	/**
	 * Static URL extended function
	 * @param String $uri - A given URI
	 * @return String - The static URL
	 */
	protected function staticUrl($uri = "")
	{
		return $this->url->getStaticBaseUri().$uri;
	}

	/**
	 * Host URL
	 * @param Int $port - The input port
	 * @return String - The host URL with port appended
	 */
	protected function host($port = 80)
	{
		$host       = $this->request->getHttpHost();
		$host_parts = explode(":", $host);

		// check if host already has binded a port
		if (count($host_parts) > 1)
			$host = current($host_parts);

		$host_url = empty($port) ? $host : $host.":".$port;

		// remove default port if set
		if (substr($host_url, -3) == ":80")
			$host_url = substr($host_url, 0, strlen($host_url) - 3);

		return $host_url;
	}

	/**
	 * Get scheme
	 * @return String - http or https
	 */
	protected function getScheme()
	{
		return $_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "http"; // aws elb headers
	}

	/**
	 * Get the requested URI
	 */
	protected function getRequestedUri()
	{
		$uri = $this->request->getUri();

		// replaces '*/public/' or first '/'
		$regex = "/^.*\/public\/(?=[^.]*$)|^\//";
		$uri   = preg_replace($regex, "", $uri);

		return $uri;
	}

	/**
	 * Sends a mail message to user asynchronously
	 * @param String $method - The Mailer method to call
	 * @param Mixed $data - The data to be passed as args
	 * @return Object
	 */
	protected function sendMailMessage($method = null, $data = null)
	{
		// simple input validation
		if (empty($method))
			throw new Exception("BaseCore::sendMailMessage -> method param is required.");

		$mailer = new \MailerController();

		// checks that a MailerController exists
		if (!method_exists($mailer, $method))
			throw new Exception("BaseCore::sendMailMessage -> method $method is not defined in Mailer Controller.");

		// call mailer class method (reflection)
		$mailer->{$method}($data);

		$this->logger->debug("BaseCore::sendMailMessage -> queued mailer message [$method]");
	}

	/**
	 * Handle the request params data validating required parameters.
	 * Also Check if get/post data is valid, if validation fails send an HTTP code, onSuccess returns a data array.
	 * Required field may have a `@` prefix to establish that is just an optional field to be sanitized.
	 * Types: `string, email, int, float, alphanum, striptags, trim, lower, upper.`
	 * @param Array $req_fields - Required fields
	 * @param String $method - HTTP method: [GET, POST], defaults to GET.
	 * @param Boolean $check_csrf - Checks the form CSRF token
	 * @return Array
	 */
	protected function handleRequest($req_fields = [], $method = "GET", $check_csrf = true)
	{
		// set anoymous function for send response
		$sendResponse = function($code) {

			if (MODULE_NAME == "api" || $this->request->isAjax())
				$this->jsonResponse($code);

			// otherwise redirect to 400 page
			$this->dispatcher->forward(["controller" => "error", "action" => "badRequest"]);
			$this->dispatcher->dispatch();
		};

		// is post request? (method now allowed)
		if ($method == "POST" && !$this->request->isPost())
			return $sendResponse(404);

		// is get request? (method now allowed)
		if ($method == "GET" && !$this->request->isGet())
			return $sendResponse(404);

		// validate always CSRF Token (POST only and API module excluded)
		if (MODULE_NAME != "api" && $check_csrf && !$this->checkCsrfToken())
			return $sendResponse(498);

		// get params data (POST, GET)
		$data = $method == "POST" ? $this->request->getPost() : $this->request->get();

		// if no required fields given, return all POST or GET vars as array
		if (empty($req_fields))
			return $data;

		// check require fields
		foreach ($req_fields as $field => $data_type) {

			$value = $this->_validateParam($data, $field, $data_type);

			if ($value === false)
				return $sendResponse(404);

			// optional fields
			if ($field[0] == "@") {

				unset($data[$field]);
				$field = substr($field, 1);
			}

			$data[$field] = $value;
		}

		return $data;
	}

	/**
	 * Validates a request parameter
	 * @param Array $data - The input data
	 * @param String $field - The field name
	 * @param String $data_type - The data type (int, string, array, json, email)
	 * @return Mixed
	 */
	private function _validateParam($data, $field, $data_type)
	{
		$optional = false;

		// check if is a optional field
		if ($field[0] == "@") {

			$optional = true;
			$field    = substr($field, 1);
		}

		// validate field
		if (!array_key_exists($field, $data))
			return $optional ? null : false;

		// set value from data array
		if ($data_type != "raw") {

			$value = $this->filter->sanitize($data[$field], $data_type);

			if ($data_type == "email")
				$value = strtolower($value);
		}
		else
			$value = $data[$field]; // raw

		// empty function considers zero value
		if (!$optional && (is_null($value) || $value == ""))
			return false;

		// check optional field
		if ($optional && (is_null($value) || $value == ""))
			return null;

		return $value;
	}
}
