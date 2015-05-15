<?php
/**
 * WS Controller : Core WebService controller, includes basic and helper methods for child controllers.
 * Requires a Phalcon DI Factory Services
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

//imports
use Phalcon\Mvc\Controller; //Phalcon Controller
use Phalcon\Exception;

abstract class WsController extends Controller
{
    /* consts */
    const HEADER_API_KEY       = 'X_API_KEY'; //HTTP header keys uses '_' for '-' in Phalcon
    const JSON_RESPONSE_STRUCT = '{"response":{"code":"200","status":"ok","payload":@payload}}';

    /**
     * child required methods
     */
    abstract protected function welcome();

    /**
     * API messages
     * @var array
     * @access protected
     */
    protected $codes_array;

    /**
     * Constructor function
     * @access protected
     */
    protected function onConstruct()
    {
        /** -- API codes -- **/
        $this->codes_array = array(
            //success
            "200" => "ok",
            //client errors
            "400" => "invalid GET or POST data (bad request)",
            "404" => "service not found",
            "405" => "method now allowed",
            "498" => "invalid header API key",
            //server
            "500" => "internal server error",
            "501" => "service unknown error",
            //db related
            "800" => "no db results found",
            //resources related
            "900" => "resource not found",
            "901" => "no files attached",
            "902" => "invalid format of file attached"
        );

        /* API Key Validation */
        if ($this->config->app->apiKeyEnabled)
            $this->_validateApiKey();
    }

    /**
     * Not found service catcher
     * @access public
     */
    public function serviceNotFound()
    {
        $this->_sendJsonResponse(404);
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Sends a JSON response
     * @access protected
     * @param string $code (200, 404, etc)
     * @param null $data Payload to send
     * @return string The response
     */
    protected function _sendJsonResponse($code = 200, $data = null)
    {
        //if code is not identified, send an unknown error
        if (!isset($this->codes_array[$code]))
            $code = 501;

        //is an app error?
        $app_error = ($code != 200) ? true : false;

        //set response
        $response = array();
        $response["code"]   = (string)$code;
        $response["status"] = $app_error ? "error" : "ok";

        //error data
        if ($app_error) {
            //set payload as objectId for numeric data, for string set as error
            if (is_numeric($data))
                $response["object_id"] = $data;
            else if (is_string($data))
                $response["error"] = $data;

            //set error for non array
            if (is_array($data))
                $response["error"] = implode(". ", $data);
            else
                $response["error"] = $this->codes_array[$code];
        }
        //success data
        else {
            //if data is an object convert to array
            if (is_object($data))
                $data = get_object_vars($data);

            //append payload?
            if (!is_null($data))
                $response["payload"] = $data;
        }

        //encode JSON
        $content = json_encode(array('response' => $response), JSON_UNESCAPED_SLASHES);

        //output the response
        $this->response->setStatusCode(200, "OK");
        $this->response->setContentType('application/json'); //set JSON as Content-Type header
        $this->response->setContent($content);
        $this->response->send();
        die(); //exit
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
     * Parse ORM validation messages struct
     * @access protected
     * @param PhalconORMMessage $obj
     * @return array|string
     */
    protected function _parseOrmMessages($obj)
    {
        $data = array();

        if (!method_exists($obj, 'getMessages'))
            return ($data[0] = "Unknown ORM Error");

        foreach ($obj->getMessages() as $msg) {
            array_push($data, $msg->getMessage());
        }

        return $data;
    }

    /**
     * Parse ORM Resulset to send as json objects
     * @access protected
     * @param Simple Resulset $result
     * @return array
     */
    protected function _parseOrmResulsetForJsonStruct($result)
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
     * Handle the request params data validating required parameters.
     * Also Check if get/post data is valid, if validation fails send an HTTP code, onSuccess returns a data array.
     * Required field may have a "_" prefix to establish that is just an optional field to be sanitized.
     * Types: string,email,int,float,alphanum,striptags,trim,lower,upper.
     * @access protected
     * @param array $required_fields
     * @param string $method
     * @example: { $data, array( "_name" => "string"), POST }
     * @link http://docs.phalconphp.com/en/latest/reference/filter.html#sanitizing-data
     * @return array
     */
    protected function _handleRequestParams($required_fields = array(), $method = 'POST')
    {
        //is post request? (method now allowed)
        if ($method == 'POST' && !$this->request->isPost())
            $this->_sendJsonResponse(405);

        //is get request? (method now allowed)
        if ($method == 'GET' && !$this->request->isGet())
            $this->_sendJsonResponse(405);

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
                $field             = substr($field, 1);
            }

            //validate field
            if (!array_key_exists($field, $data)) {
                if (!$is_optional_field) {
                    $invalid_data = true;
                    break;
                }
               
                $data[$field] = null;
                continue;
            }

            //get value from data array & sanitize it
            $value = empty($data_type) ? $data[$field] : $this->filter->sanitize($data[$field], $data_type);

            //check data (empty considers zero value )
            if ($is_optional_field && (is_null($value) || $value == ''))
                $data[$field] = null;
             elseif (is_null($value) || $value == '')
                $invalid_data = true;
            else
                $data[$field] = $value;
        }

        //check invalid data
        if ($invalid_data)
            return $this->_sendJsonResponse(400);
            
        //var_dump($data);exit;
        return $data;
    }

    /**
     * Validate search number & offset parameters
     * @access protected
     * @param null $input_num Input number
     * @param null $input_off Input offset
     * @param null $max_num Maximun number
     * @return array
     */
    protected function _handleNumberAndOffsetParams($_number = null, $_offset = null, $_max_num = null)
    {
        if (!is_null($_number)) {
            $number = $_number;
            $offset = $_offset;

            if ($number < 0 || !is_numeric($number))
                $number = 0;

            if ($offset < 0 || !is_numeric($offset))
                $offset = 1;

            if ((empty($number) && $_max_num >= 1) || ($number > $_max_num))
                $number = $_max_num;
        }
        else {
            $number = empty($_max_num) ? 1 : $_max_num;
            $offset = 0;
        }

        return array("number" => $number, "offset" => $offset);
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */
    
    /**
     * API key Validation
     * @return void
     */
    private function _validateApiKey()
    {
        //get API key from config file & request header Api Key
        $app_api_key    = $this->config->app->apiKey;
        $header_api_key = $this->request->getHeader(self::HEADER_API_KEY);

        //check if keys are equal
        if ($app_api_key !== $header_api_key)
            $this->_sendJsonResponse(498);
    }
}
