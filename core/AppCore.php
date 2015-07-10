<?php
/**
 * App Core Controller, includes basic and helper methods for web & ws core controllers.
 * Requires a Phalcon DI Factory Services
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

//imports
use Phalcon\Mvc\Controller;  //Phalcon Controller
use Phalcon\Exception;       //Phalcon Exception

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
     * Split ORM Resulset object properties
     * @access protected
     * @param object $obj
     * @param boolean $json_encode Returns a json string
     * @return mixed array|string
     */
    protected function _parseOrmMessages($obj, $json_encode = false)
    {
        $data = array();

        if (!method_exists($obj, 'getMessages'))
            return ($data[0] = "Unknown ORM Error");

        foreach ($obj->getMessages() as $msg)
            array_push($data, $msg->getMessage());

        if($json_encode)
            $data = json_encode($data);

        return $data;
    }

    /**
     * Parse ORM properties and returns a simple array
     * @access protected
     * @param object $result Phalcon Resulset
     * @param boolean $split Split objects flag
     * @return mixed array
     */
    protected function _parseOrmResultset($result, $split = false)
    {
        if(!method_exists($result,'count') || empty($result->count()))
            return array();

        $objects = array();
        foreach ($result as $object) {
            $object = (object) array_filter((array) $object);
            array_push($objects, $object);
        }

        return $split ? $this->_splitOrmResulset($objects) : $objects;
    }

    /**
     * Parse ORM resultset for Json Struct
     * @access protected
     * @param array $result
     */
    protected function _splitOrmResulset($result)
    {
        if(!$result)
            return array();

        $objects = array();
        //loop each object
        foreach ($result as $obj) {
            //get object properties
            $props = get_object_vars($obj);

            if(empty($props))
                continue;

            $new_obj = new \stdClass();

            foreach ($props as $k => $v) {
                //filter properties than has a class prefix
                $namespace = explode("_", $k);

                //validate property namespace, check if class exists in models (append plural noun)
                if(empty($namespace) || !class_exists(ucfirst($namespace[0]."s")))
                    continue;

                $type = $namespace[0];
                $prop = str_replace($type."_","",$k);

                //creates the object struct
                if(!isset($new_obj->{$type}))
                    $new_obj->{$type} = new \stdClass();

                //set props
                $new_obj->{$type}->{$prop} = $v;
            }

            //check for a non-props object
            if(empty(get_object_vars($new_obj)))
                continue;

            array_push($objects, $new_obj);
        }

        return $objects;
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
     * @param array $required_fields
     * @param string $method
     * @param string $send_json Sends json response for ajax calls
     * @param boolean $check_csrf_token Checks a form CSRF token
     * @example { $data, array( "_name" => "string"), POST }
     * @link   http://docs.phalconphp.com/en/latest/reference/filter.html#sanitizing-data
     * @return array
     */
    protected function _handleRequestParams($required_fields = array(), $method = 'POST', $send_json = true, $check_csrf_token = true)
    {
        //check is API module
        $isApiModule = MODULE_NAME === "api" ?: false;

        //is post request? (method now allowed)
        if ($method == 'POST' && !$this->request->isPost())
            $this->_sendJsonResponse(405);

        //is get request? (method now allowed)
        if ($method == 'GET' && !$this->request->isGet())
            $this->_sendJsonResponse(405);

        //validate always CSRF Token (prevents also headless browsers, POST only and API module excluded)
        if (!$isApiModule && $check_csrf_token && !$this->_checkCsrfToken())
            $this->_sendJsonResponse(498);

        //get POST or GET data
        $data = ($method == 'POST' ? $this->request->getPost() : $this->request->get());

        //clean phalcon data for GET method
        if ($method == 'GET')
            unset($data['_url']);

        //if no required fields given, return all POST or GET vars as array
        if (empty($required_fields))
            return $data;

        //missing data?
        if (!empty($required_fields) && empty($data))
            $this->_sendJsonResponse(400);

        //dont filter data just return it
        if (empty($required_fields))
            return $data;

        $invalid_data = false;
        //compare keys
        foreach ($required_fields as $field => $data_type) {
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
        if ($invalid_data) {

            if($isApiModule)
                return $this->_sendJsonResponse(400);

            //otherwise redirect to 400 page
            $this->dispatcher->forward(array("controller" => "errors", "action" => "badRequest"));
        }

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
