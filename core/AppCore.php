<?php
/**
 * App Core Controller, includes basic and helper methods for web & ws core controllers.
 * Requires a Phalcon DI Factory Services
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

//imports
use Phalcon\Mvc\Controller;  //Phalcon Controller

//security interface
interface webSecurity
{
    public function _checkCsrfToken();
}

abstract class AppCore extends Controller
{
    /* consts */
    const JSON_RESPONSE_STRUCT = '{"response":{"code":"200","status":"ok","payload":@payload}}';

    /**
     * abstract required methods
     */
    abstract protected function _sendJsonResponse();

    /**
     * Base URL extended function
     * @access protected
     * @param string $uri A given URI
     * @return string The static URL
     */
    protected function _baseUrl($uri = "")
    {
        return $this->url->getStaticBaseUri().$uri;
    }

    /**
     * Sends a file to buffer output response
     * @param integer $code [description]
     */
    protected function _sendFileToBuffer($data = "", $type = 'application/json')
    {
        //append struct as string if data type is JSON
        if($type == 'application/json')
            $data = str_replace("@payload", $data, self::JSON_RESPONSE_STRUCT);

        $this->response->setStatusCode(200, "OK");
        $this->response->setContentType($type);
        $this->response->setContent($data);
        $this->response->send();
        die(); //exit
    }

    /**
     * Get the requested URI
     * @access protected
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
     * Handle the request params data validating required parameters.
     * Also Check if get/post data is valid, if validation fails send an HTTP code, onSuccess returns a data array.
     * Required field may have a "_" prefix to establish that is just an optional field to be sanitized.
     * Types: string,email,int,float,alphanum,striptags,trim,lower,upper.
     * @access protected
     * @param array $req_fields Required fields
     * @param string $method
     * @param boolean $check_csrf Checks a form CSRF token
     * @example { $data, array( "_name" => "string"), POST }
     * @link   http://docs.phalconphp.com/en/latest/reference/filter.html#sanitizing-data
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
                $this->_sendJsonResponse($code);
            };
        }
        else {
            $sendResponse = function($code) {
                //otherwise redirect to 400 page
                $this->dispatcher->forward(array("controller" => "errors", "action" => "badRequest"));
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

        //get POST or GET data
        $data = ($method == 'POST') ? $this->request->getPost() : $this->request->get();

        //clean phalcon data for GET method
        if ($method == 'GET')
            unset($data['_url']);

        //if no required fields given, return all POST or GET vars as array
        if (empty($req_fields))
            return $data;

        //missing data?
        if (!empty($req_fields) && empty($data))
            return $sendResponse(400);

        //dont filter data just return it
        if (empty($req_fields))
            return $data;

        $invalid_data = false;
        //compare keys
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

            //get value from data array & sanitize it
            if(empty($data_type) || $data_type == 'array')
                $value = $data[$field];
            else
                $value = $this->filter->sanitize($data[$field], $data_type);

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
     * @access protected
     * @param int $input_num Input number
     * @param int $input_off Input offset
     * @param int $max_num Maximun number
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
     * Dump a phalcon object for debugging
     * @param  object $object Any object
     * @param  boolean $exit Flag for exit script execution
     * @return mixed
     */
    protected static function _varDump($object, $exit = true)
    {
        $object = (new \Phalcon\Debug\Dump())->toJson($object);

        if($exit) {
            print_r($object);
            exit;
        }

        return $object;
    }
}
