<?php
/**
 * Mvc Core Controller, includes basic and helper methods for WebCore & WsCore controllers.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

//imports
use Phalcon\Mvc\Controller;
use Phalcon\Exception;
//core
use CrazyCake\Phalcon\App;
use CrazyCake\Controllers\Requester;
use CrazyCake\Controllers\Responser;

/**
 * Web Security Interface (optional, only for webcore)
 */
interface WebSecurity
{
    public function checkCsrfToken();
}

/**
 * App Base Core
 */
abstract class BaseCore extends Controller
{
    /* Traits */
    use Debugger;
    use Requester;
    use Responser;

    /**
     * API codes
     * @var array
     */
    protected $CODE;

    /**
     * Core text messages
     * @var array
     */
    protected $MSG;

    /**
     * on Construct event
     */
    protected function onConstruct()
    {
        //set API Codes
        $this->CODE = self::$RESPONSE_CODES;

        //set message keys
        $this->MSG = [];
    }

    /**
     * Base URL extended function
     * @param string $uri - A given URI
     * @return string - The static URL
     */
    protected function baseUrl($uri = "")
    {
        return APP_BASE_URL.$uri;
    }

    /**
     * Static URL extended function
     * @param string $uri - A given URI
     * @return string - The static URL
     */
    protected function staticUrl($uri = "")
    {
        return $this->url->getStaticBaseUri().$uri;
    }

    /**
     * Host URL
     * @param int $port - The host URL
     * @return string - The host URL with port appended
     */
    protected function host($port = 80)
    {
		$host 		= $this->request->getHttpHost();
		$host_parts = explode(":", $host);

		//check if host already has binded a port
		if(count($host_parts) > 1)
			$host = current($host_parts);

        return empty($port) ? $host : $host.":".$port;
    }

    /**
     * Get the requested URI
     */
    protected function getRequestedUri()
    {
        $uri = $this->request->getUri();

        //replaces '*/public/' or first '/'
        $regex = "/^.*\/public\/(?=[^.]*$)|^\//";
        $uri   = preg_replace($regex, "", $uri);

        return $uri;
    }

	/**
     * Sends a mail message to user asynchronously
     * @param string $method - The Mailer method to call
     * @param object $data - The data to be passed as args
     * @return object response
     */
    protected function sendMailMessage($method = null, $data = null)
    {
        //simple input validation
        if (empty($method))
            throw new Exception("BaseCore::sendMailMessage -> method param is required.");

        //get the mailer controller name
        $mailer_class = App::getClass("mailer_controller");

        //checks that a MailerController exists
        if (!class_exists($mailer_class))
            throw new Exception("BaseCore::sendMailMessage -> A Mailer Controller is required.");

        $mailer = new $mailer_class();

        //checks that a MailerController exists
        if (!method_exists($mailer, $method))
            throw new Exception("BaseCore::sendMailMessage -> Method $method is not defined in Mailer Controller.");

        //call mailer class method (reflection)
        $response = $mailer->{$method}($data);

        if (is_array($response))
            $response = json_encode($response);

        //save response only for non production-environment
        $this->logger->debug("BaseCore::sendMailMessage -> Queuing new Mailer Message [$method].");

        return $response;
    }

    /**
     * Sends an async tasks as another request. (MVC struct)
     * @param array $options - Options: module, controller, action, method, payload, socket, headers
     */
    protected function asyncRequest($options = [])
    {
        //set base url
        if (empty($options["base_url"]))
            $options["base_url"] = $this->baseUrl();

        //set uri
        if (empty($options["uri"]))
            $options["uri"] = $options["controller"]."/".$options["action"]."/";

        //special case for module cross requests
        if (!empty($options["module"]) && $options["module"] == "api") {

            //set API key header name
            $api_key_header_value = $this->config->apiKey;
            $api_key_header_name  = str_replace("_", "-", WsCore::HEADER_API_KEY);
            $options["headers"]   = [$api_key_header_name => $api_key_header_value];
        }

        //payload
        if (!empty($options["payload"])) {

            //skip encryption
            if (isset($options["encrypt"]) && !$options["encrypt"])
                $options["payload"] = (array)$options["payload"];
            else
                $options["payload"] = $this->cryptify->encryptData($options["payload"]);
        }

        //requester
        $this->newRequest($options);
    }

    /**
     * Handle the request params data validating required parameters.
     * Also Check if get/post data is valid, if validation fails send an HTTP code, onSuccess returns a data array.
     * Required field may have a ```@``` prefix to establish that is just an optional field to be sanitized.
     * Types: ```string, email, int, float, alphanum, striptags, trim, lower, upper.```
     * Example: ```{ $data, array( "@name" => "string"), POST }```
     * @link   http://docs.phalconphp.com/en/latest/reference/filter.html#sanitizing-data
     * @param array $req_fields - Required fields
     * @param string $method - HTTP method: [GET, POST, MIXED], defaults to GET.
     * @param boolean $check_csrf - Checks the form CSRF token
     * @return array
     */
    protected function handleRequest($req_fields = [], $method = "GET", $check_csrf = true)
    {
        //check API module and set special settings
        if (MODULE_NAME == "api")
            $check_csrf = false;

        //set anoymous function for send response
        $sendResponse = function($code) {

            if(MODULE_NAME == "api" || $this->request->isAjax())
                $this->jsonResponse($code);

            //otherwise redirect to 400 page
            $this->dispatcher->forward(["controller" => "error", "action" => "badRequest"]);
            $this->dispatcher->dispatch();
            return;
        };

        //is post request? (method now allowed)
        if ($method == "POST" && !$this->request->isPost())
            return $sendResponse(405);

        //is get request? (method now allowed)
        if ($method == "GET" && !$this->request->isGet())
            return $sendResponse(405);

        //validate always CSRF Token (prevents also headless browsers, POST only and API module excluded)
        if ($check_csrf) {
            //check if method exists
            if (method_exists($this, 'checkCsrfToken') && !$this->checkCsrfToken())
                return $sendResponse(498);
        }

        //get params data: POST, GET, or mixed
        if ($method == "POST")
            $data = $this->request->getPost();
        else if ($method == "GET")
            $data = $this->request->get();
        else
            $data = array_merge($this->request->get(), $this->request->getPost());

        //clean phalcon data for GET or MIXED method
        if ($method != "POST")
            unset($data["_url"]);

        //if no required fields given, return all POST or GET vars as array
        if (empty($req_fields))
            return $data;

        //check require fields
        foreach ($req_fields as $field => $data_type) {

            $value = $this->_validateField($data, $field, $data_type);

			if($value === false)
                return $sendResponse(400);

            //optional fields
            if($field[0] == "@") {
                unset($data[$field]);
                $field = substr($field, 1);
            }

            $data[$field] = $value;
        }

        return $data;
    }

    /**
     * Validates a request input field
     */
	private function _validateField($data, $field, $data_type)
	{
		$is_optional = false;

		//check if is a optional field
		if (substr($field, 0, 1) == "@") {
			$is_optional = true;
			$field = substr($field, 1);
		}

		//validate field
		if (!array_key_exists($field, $data))
			return $is_optional ? null : false;

		//set value from data array
		if (empty($data_type) || $data_type == "array") {
			$value = $data[$field];
		}
		else if ($data_type == "json") {
			$value = json_decode($data[$field]); //NULL if cannot be decoded
		}
		else {
			//sanitize
			$value = $this->filter->sanitize($data[$field], $data_type);
			//lower case for email
			if ($data_type == "email")
				$value = strtolower($value);
		}

        //(empty fn considers zero value)
        if (!$is_optional && (is_null($value) || $value == ""))
			return false;

		//check optional field
		if ($is_optional && (is_null($value) || $value == ""))
			return null;

		return $value;
	}
}
