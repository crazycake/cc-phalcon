<?php
/**
 * Mvc Core Controller, includes basic and helper methods for web & ws core controllers.
 * Requires a Phalcon DI Factory Services
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

//imports
use Phalcon\Mvc\Controller;
use Phalcon\Exception;
//core
use CrazyCake\Phalcon\AppModule;
use CrazyCake\Services\Guzzle;
use CrazyCake\Models\BaseResultset;

/**
 * Web Security Interface
 */
interface WebSecurity
{
    public function _checkCsrfToken();
}

/**
 * App Core for Web and Ws Cores
 */
abstract class MvcCore extends Controller
{
    /* Traits */
    use Core;
    use Guzzle;

    /* consts */
    const HEADER_API_KEY       = 'X_API_KEY'; //HTTP header keys uses '_' for '-' in Phalcon
    const JSON_RESPONSE_STRUCT = '{"response":{"code":"200","status":"ok","payload":@payload}}';

    /**
     * HTTP API codes
     * @var array
     */
    protected $CODES;

    /**
     * HTTP API messages
     * @var array
     */
    protected $MSGS;

    /**
     * on Construct event
     */
    protected function onConstruct()
    {
        //set API Codes
        $this->CODES = [
            //success
            "200" => "ok",
            //client errors
            "400" => "Bad Request, invalid GET or POST data",
            "401" => "Unauthorized",
            "403" => "Forbidden",
            "404" => "Not Found",
            "405" => "Method Not Allowed",
            "406" => "Not Acceptable",
            "408" => "Request Timeout",
            "498" => "Invalid Token",
            //server
            "500" => "Internal Server Error",
            "501" => "Unknown error",
            //db related
            "800" => "Empty result data",
            //resources related
            "900" => "Resource not found",
            "901" => "No files attached",
            "902" => "Invalid format of file attached"
        ];

        //set message keys
        $this->MSGS = [];
    }

    /**
     * Base URL extended function
     * @param string $uri - A given URI
     * @return string - The static URL
     */
    protected function _baseUrl($uri = "")
    {
        return APP_BASE_URL.$uri;
    }

    /**
     * Static URL extended function
     * @param string $uri - A given URI
     * @return string - The static URL
     */
    protected function _staticUrl($uri = "")
    {
        return $this->url->getStaticBaseUri().$uri;
    }

    /**
     * Sends a file to buffer output response
     * @param binary $data - The binary data to send
     * @param string $mime_type - The mime type
     */
    protected function _sendFileToBuffer($data = null, $mime_type = 'application/json')
    {
        //append struct as string if data type is JSON
        if($mime_type == 'application/json')
            $data = str_replace("@payload", $data, self::JSON_RESPONSE_STRUCT);

        if(isset($this->view)) {
            $this->view->disable();
            //return false;
        }

        $this->response->setStatusCode(200, "OK");
        $this->response->setContentType($mime_type);

        //content must be set after content type
        if(!is_null($data))
            $this->response->setContent($data);

        $this->response->send();
        die(); //exit
    }

    /**
     * Get the requested URI
     */
    protected function _getRequestedUri()
    {
        $uri = $this->request->getUri();
        //replaces '*/public/' or first '/'
        $regex = "/^.*\/public\/(?=[^.]*$)|^\//";
        $uri = preg_replace($regex, "", $uri);

        return $uri;
    }

    /**
     * Sends an async tasks as another request
     * Current implementation is a Guzzle async Request.
     * @param array $options - Options: module, controller, action, method, payload, socket, headers
     */
    protected function _asyncRequest($options = array())
    {
        //set base url
        if(empty($options["base_url"]))
            $options["base_url"] = empty($options["module"]) ? $this->_baseUrl() : AppModule::getUrl($options["module"]);

        //set uri
        if(empty($options["uri"]))
            $options["uri"] = $options["controller"]."/".$options["action"]."/";

        //special case for module cross requests
        if(!empty($options["module"]) && $options["module"] == "api") {

            //get API key header name
            $api_key_header_value = AppModule::getProperty("key", "api");
            $api_key_header_name  = str_replace("_", "-", self::HEADER_API_KEY);
            $options["headers"]   = [$api_key_header_name => $api_key_header_value];
        }

        //payload
        if(!empty($options["payload"])) {

            //skip encryption
            if(isset($options["encrypt"]) && !$options["encrypt"])
                $options["payload"] = (array)$options["payload"];
            else
                $options["payload"] = $this->cryptify->encryptData($options["payload"]);
        }

        //log asyn request
        $this->logger->debug("MvcCore::_asyncRequest -> Options: ".json_encode($options, JSON_UNESCAPED_SLASHES));

        //guzzle method
        $this->_newRequest($options);
    }

    /**
     * Sends a JSON response for APIs.
     * The HTTP statusCode is always 200.
     * Codes: ```200, 400, 404, 405, 498, 500, 501, 800, 900, 901, 902```
     * @param string $code - The app message code.
     * @param object $payload - Payload to send
     * @param string $type - (optional) Append a type attr to the response.
     * @param string $namespace - (optional) Append a type namespace to the response.
     * @return string - The response
     */
    protected function _sendJsonResponse($code = 200, $payload = null, $type = "", $namespace = "")
    {
        //if code is not identified, mark as unknown error
        if (!isset($this->CODES[$code]))
            $this->CODES[$code] = $this->CODES[501];

        //set response
        $response = [
            "code"   => (string)$code,
            "status" => $code == 200 ? "ok" : "error"
        ];

        //type
        if(!empty($type))
            $response["type"] = $type;

        //namespace
        if(!empty($namespace))
            $response["namespace"] = $namespace;

        //success data
        if($code == 200) {

            //if data is an object convert to array
            if (is_object($payload))
                $payload = get_object_vars($payload);

            //check redirection action
            if(is_array($payload) && isset($payload["redirect"])) {
                $response["redirect"] = $payload["redirect"];
            }
            //append payload
            else {

                //merge _ext properties for API
                if(MODULE_NAME === "api")
                    BaseResultset::mergeArbitraryProps($payload);

                $response["payload"] = $payload;
            }
        }
        //error data
        else {

            //set payload as objectId for numeric data, for string set as error
            if (is_numeric($payload))
                $response["object_id"] = $payload;
            else if (is_string($payload))
                $response["message"] = $payload;

            //set error for non array
            $response["error"] = is_object($payload) ? $payload : $this->CODES[$code];
        }

        //if a view service is set, disable rendering
        if(isset($this->view))
            $this->view->disable(); //disable view output

        //encode JSON
        $content = json_encode(['response' => $response], JSON_UNESCAPED_SLASHES);

        //output the response
        $this->response->setStatusCode(200, "OK");
        $this->response->setContentType('application/json'); //set JSON as Content-Type header
        $this->response->setContent($content);
        $this->response->send();
        die(); //exit
    }

    /**
     * Sends a simple text response
     * @param  mixed [string|array] $text - Any text string
     */
    protected function _sendTextResponse($text = "OK"){

        if(is_array($text) || is_object($text))
            $text = json_encode($text, JSON_UNESCAPED_SLASHES);

        //if a view service is set, disable rendering
        if(isset($this->view))
            $this->view->disable(); //disable view output

        //output the response
        $this->response->setStatusCode(200, "OK");
        $this->response->setContentType('text/html');
        $this->response->setContent($text);
        $this->response->send();
        die(); //exit
    }

    /**
     * Handle the request params data validating required parameters.
     * Also Check if get/post data is valid, if validation fails send an HTTP code, onSuccess returns a data array.
     * Required field may have a ```@``` prefix to establish that is just an optional field to be sanitized.
     * Types: ```string, email, int, float, alphanum, striptags, trim, lower, upper.```
     * Example: ```{ $data, array( "@name" => "string"), POST }```
     * @link   http://docs.phalconphp.com/en/latest/reference/filter.html#sanitizing-data
     * @param array $req_fields - Required fields
     * @param string $method - HTTP method: [GET, POST, MIXED]
     * @param boolean $check_csrf - Checks the form CSRF token
     * @return array
     */
    protected function _handleRequestParams($req_fields = array(), $method = 'POST', $check_csrf = true)
    {
        //check API module and set special settings
        if(MODULE_NAME === "api") {
            $check_csrf = false;
            $send_json  = true;
        }
        //frontend or backend module
        else {
            $send_json = $this->request->isAjax();
        }

        //set anoymous function for send response
        if($send_json) {
            $sendResponse = function($code) {

                //call send json response
                if(method_exists($this, '_sendJsonResponse'))
                    $this->_sendJsonResponse($code);
                else
                    throw new Exception("MvcCore::_handleRequestParams -> _sendJsonResponse() must be implemented.");

            };
        }
        else {
            $sendResponse = function($code) {
                //otherwise redirect to 400 page
                $this->dispatcher->forward(["controller" => "error", "action" => "badRequest"]);
                $this->dispatcher->dispatch();
            };
        }

        //is post request? (method now allowed)
        if ($method == 'POST' && !$this->request->isPost())
            return $sendResponse(405);

        //is get request? (method now allowed)
        if ($method == 'GET' && !$this->request->isGet())
            return $sendResponse(405);

        //validate always CSRF Token (prevents also headless browsers, POST only and API module excluded)
        if ($check_csrf) {
            //check if method exists
            if(method_exists($this, '_checkCsrfToken') && !$this->_checkCsrfToken())
                return $sendResponse(498);
        }

        //get params data: POST, GET, or mixed
        if($method == 'POST')
            $data = $this->request->getPost();
        else if($method == 'GET')
            $data = $this->request->get();
        else
            $data = array_merge($this->request->get(), $this->request->getPost());

        //clean phalcon data for GET or MIXED method
        if ($method != 'POST')
            unset($data['_url']);

        //if no required fields given, return all POST or GET vars as array
        if (empty($req_fields))
            return $data;

        $invalid_data = false;
        //check require fields
        foreach ($req_fields as $field => $data_type) {

            $is_optional_field = false;
            //check if is a optional field
            if (substr($field, 0, 1) === "@") {
                $is_optional_field = true;
                $field = substr($field, 1);
            }

            //validate field
            if (!array_key_exists($field, $data)) {
                //check if is an optional field
                if (!$is_optional_field) {
                    $invalid_data = true;
                    break;
                }
                else {
                    $data[$field] = null;
                    continue;
                }
            }

            //get value from data array
            if(empty($data_type) || $data_type == 'array') {
                $value = $data[$field];
            }
            else if($data_type == 'json') {
                $value = json_decode($data[$field]); //NULL if cannot be decoded
            }
            else {
                //sanitize
                $value = $this->filter->sanitize($data[$field], $data_type);
                //lower case for email
                if($data_type == "email")
                    $value = strtolower($value);
            }

            //check data (empty fn considers zero value )
            if ($is_optional_field && (is_null($value) || $value == ''))
                $data[$field] = null;
            elseif (is_null($value) || $value == '')
                $invalid_data = true;
            else
                $data[$field] = $value;
        }

        //check for invalid data
        if ($invalid_data)
            return $sendResponse(400);

        return $data;
    }

    /**
     * Validate search number & offset parameters
     * @param int $input_num - Input number
     * @param int $input_off - Input offset
     * @param int $max_num - Maximum number
     * @return array
     */
    protected function _handleNumberAndOffsetParams($input_num = null, $input_off = null, $max_num = null)
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
     * Sends a mail message to user asynchronously
     * @param string $method - The Mailer method to call
     * @param object $data - The data to be passed as args
     * @return object response
     */
    protected function _sendMailMessage($method = null, $data = null)
    {
        //simple input validation
        if (empty($method))
            throw new Exception("MvcCore::_sendMailMessage -> method param is required.");

        //get the mailer controller name
        $mailer_class = AppModule::getClass('mailer_controller');

        //checks that a MailerController exists
        if(!class_exists($mailer_class))
            throw new Exception("MvcCore::_sendMailMessage -> A Mailer Controller is required.");

        $mailer = new $mailer_class();

        //checks that a MailerController exists
        if(!method_exists($mailer, $method))
            throw new Exception("MvcCore::_sendMailMessage -> Method $method is not defined in Mailer Controller.");

        //call mailer class method (reflection)
        $response = $mailer->{$method}($data);

        if(is_array($response))
            $response = json_encode($response);

        //save response only for non production-environment
        if(APP_ENVIRONMENT !== "production")
            $this->logger->debug('MvcCore::_sendMailMessage -> Got response from MailerController:\n' . $response);

        return $response;
    }
}
