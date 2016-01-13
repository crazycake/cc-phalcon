<?php
/**
 * App Core Controller, includes basic and helper methods for web & ws core controllers.
 * Requires a Phalcon DI Factory Services
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

//imports
use Phalcon\Mvc\Controller;
use Phalcon\Exception;

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
abstract class AppCore extends Controller
{
    /* consts */
    const JSON_RESPONSE_STRUCT = '{"response":{"code":"200","status":"ok","payload":@payload}}';

    /**
     * Sends an async request
     * @param array $options - Options: controller, action, method, payload, socket, headers
     */
    abstract protected function _sendAsyncRequest($options = array());

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
     * Get Module Model Class Name
     * A prefix can be set in module options
     * @param string $key - The class module name uncamelize, example: 'some_class'
     */
    protected function _getModuleClass($key = "")
    {
        //get module class prefix
        $class_map = isset($this->config->app->classMap) ? $this->config->app->classMap : [];

        //check for prefix in module settings
        $class_name = isset($class_map[$key]) ? $class_map[$key] : $key;

        $camelized_class_name = \Phalcon\Text::camelize($class_name);

        return "\\$camelized_class_name";
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
        //encode data
        if(!empty($options["payload"]))
            $options["payload"] = $this->cryptify->encryptForGetRequest($options["payload"]);

        //set base url
        if(empty($options["base_url"]))
            $options["base_url"] = empty($options["module"]) ? $this->_baseUrl() : \CrazyCake\Phalcon\AppLoader::getModuleURL($options["module"]);

        //set uri
        if(empty($options["uri"]))
            $options["uri"] = $options["controller"]."/".$options["action"]."/";

        //special case for module cross requests
        if(!empty($options["module"]) && $options["module"] == "api") {

            //get API key header name
            $api_key_header_name = str_replace("_", "-", \CrazyCake\Core\WsCore::HEADER_API_KEY);
            $options["headers"]  = [ $api_key_header_name => $this->config->app->api->key];
        }

        //log asyn request
        $this->logger->debug("AppCore::_asyncRequest -> Options: ".json_encode($options, JSON_UNESCAPED_SLASHES));

        //child method
        $this->_sendAsyncRequest($options);
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
            throw new Exception("AppCore::_sendMailMessage -> method param is required.");

        //get the mailer controller name
        $mailer_class = $this->_getModuleClass('mailer_controller');

        //checks that a MailerController exists
        if(!class_exists($mailer_class))
            throw new Exception("AppCore::_sendMailMessage -> A Mailer Controller is required.");

        $mailer = new $mailer_class();

        //checks that a MailerController exists
        if(!method_exists($mailer, $method))
            throw new Exception("AppCore::_sendMailMessage -> Method $method is not defined in Mailer Controller.");

        //call mailer class method (reflection)
        $response = $mailer->{$method}($data);

        if(is_array($response))
            $response = json_encode($response);

        //save response only for non production-environment
        if(APP_ENVIRONMENT !== "production")
            $this->logger->debug('AppCore::_sendMailMessage -> Got response from MailerController:\n' . $response);

        return $response;
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
                    throw new Exception("AppCore::_handleRequestParams -> _sendJsonResponse() must be implemented.");

            };
        }
        else {
            $sendResponse = function($code) {
                //otherwise redirect to 400 page
                $this->dispatcher->forward(["controller" => "errors", "action" => "badRequest"]);
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

        return array("number" => $number, "offset" => $offset);
    }

    /**
     * Logs database query & statements with phalcon event manager
     * @param string $logFile - The log file name
     */
    protected function _logDatabaseStatements($logFile = "db.log")
    {
        //Listen all the database events
        $eventsManager = new \Phalcon\Events\Manager();
        $logger        = new \Phalcon\Logger\Adapter\File(APP_PATH."logs/".$logFile);

        $eventsManager->attach('db', function ($event, $connection) use ($logger) {
            //log SQL
            if ($event->getType() == 'beforeQuery')
                $logger->debug("AppCore:_logDatabaseStatements -> SQL:\n".$connection->getSQLStatement());
        });
        // Assign the eventsManager to the db adapter instance
        $this->db->setEventsManager($eventsManager);
    }

    /**
     * Dump a phalcon object for debugging
     * For printing uses Kint library if available
     * @param object $object - Any object
     * @param boolean $exit - Flag for exit script execution
     * @return mixed
     */
    protected function _dump($object, $exit = true)
    {
        $object = (new \Phalcon\Debug\Dump())->toJson($object);

        //print output
        class_exists("\\Kint") ? s($object) : print_r($object);

        if($exit) exit;

        return $object;
    }
}
