<?php
/**
 * Core Controller, includes basic and helper methods for child controllers.
 * Requires a Phalcon DI Factory Services
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

//imports
use Phalcon\Mvc\Controller;     //Phalcon Controller
use Phalcon\Exception;
//CrazyCake Utils
use CrazyCake\Utils\UserAgent;  //User Agent identifier

abstract class WebController extends Controller
{
    /**
     * child required methods
     */
    abstract protected function loadAppAssets();
    abstract protected function checkBrowserSupport();
    abstract protected function sendAsyncRequest();

    /**
     * User agent properties
     * @var object
     * @access public
     */
    public $client;

    /**
     * Translations Message Keys
     * @var array
     * @access protected
     */
    protected $MSG_KEYS;

    /** ---------------------------------------------------------------------------------------------------------------
     * Constructor function
     * --------------------------------------------------------------------------------------------------------------- **/
    protected function onConstruct()
    {
        //set client object with its properties (User-Agent)
        $this->_setClientObject();
    }
    /** ---------------------------------------------------------------------------------------------------------------
     * Init function,'$this' is the dependency injector reference
     * --------------------------------------------------------------------------------------------------------------- **/
    protected function initialize()
    {
        //Load view data only for non-ajax requests
        if (!$this->request->isAjax()) {

            //check browser (child method)
            $this->checkBrowserSupport();

            //Set App common vars
            $this->view->setVar("app", $this->config->app); //app configuration vars
            $this->view->setVar("client", $this->client);   //client object
            $this->view->setVar("url", $this->url);         //URL service object

            //CSRF, dont regenerate tokens for AJAX request
            $this->view->setVar("csrf_key", $this->client->tokenKey);
            $this->view->setVar("csrf_token", $this->client->token);
        }
    }
    /** ---------------------------------------------------------------------------------------------------------------
     * After Execute Route: Triggered after executing the controller/action method
     * --------------------------------------------------------------------------------------------------------------- **/
    protected function afterExecuteRoute()
    {
        //for non-ajax only
        if (!$this->request->isAjax()) {
            //load app assets (child method)
            $this->loadAppAssets();
            //extend session data
            $this->_extendsClientSessionData();
        }
    }
    /* --------------------------------------------------- § -------------------------------------------------------- */

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
     * Parse ORM validation messages struct
     * @access protected
     * @param object $obj
     * @return mixed array|string
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
     * Filter ORM null properties and returns a simple array
     * @access protected
     * @param object $result Phalcon Resulset
     * @return mixed array|boolean
     */
    protected function _filterOrmResultset($result)
    {
        if(!method_exists($result,'count'))
            return false;

        if(empty($result->count()))
            return false;

        $objects = array();
        foreach ($result as $object) {
            $object = (object) array_filter((array) $object);
            array_push($objects, $object);
        }
        return $objects;
    }

    /**
     * Get the requested URI as array
     * @access protected
     */
    protected function _getRequestedUrlAsArray()
    {
        $uris = explode("/", $this->request->getURI());

        return empty($uris) ? false : $uris;
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
     * Handle the request params data validating required parameters.
     * Also Check if get/post data is valid, if validation fails send an HTTP code, onSuccess returns a data array.
     * Required field may have a "_" prefix to establish that is just an optional field to be sanitized.
     * Types: string,email,int,float,alphanum,striptags,trim,lower,upper.
     * @access protected
     * @param array $required_fields
     * @param string $method
     * @param string $send_json Sends json response for ajax calls
     * @example { $data, array( "_name" => "string"), POST }
     * @link   http://docs.phalconphp.com/en/latest/reference/filter.html#sanitizing-data
     * @return array
     */
    protected function _handleRequestParams($required_fields = array(), $method = 'POST', $send_json = true)
    {
        //is post request? (method now allowed)
        if ($method == 'POST' && !$this->request->isPost())
            $this->_sendJsonResponse(405);

        //is get request? (method now allowed)
        if ($method == 'GET' && !$this->request->isGet())
            $this->_sendJsonResponse(405);

        //validate always CSRF Token (prevents also headless browsers, POST only)
        if (!$this->_checkCsrfToken())
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
            $value = empty($data_type) ? $data[$field] : $this->filter->sanitize($data[$field], $data_type);

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
            return $send_json ? $this->_sendJsonResponse(400) : false;
        
        return $data;
    }

    /**
     * Send a JSON response
     * @access protected
     * @param string $status_code HTTP Response code like 200, 404, 500, etc.
     * @param mixed $payload Payload to send, array or string.
     * @param bool $error_type Can be 'success', 'warning', 'info', 'alert', 'secondary'.
     * @param mixed $error_namespace for Javascript event manipulations, string or null value.
     */
    protected function _sendJsonResponse($status_code = 200, $payload = null, $error_type = false, $error_namespace = null)
    {
        $msg_code = array(
            "200" => "OK",
            "400" => "Bad Request",
            "403" => "Forbidden",
            "405" => "Method Not Allowed",
            "498" => "Token expired/invalid",
            "500" => "Server Error"
        );

        //is payload an app error?
        if ($error_type) {

            //only one error at a time
            if (is_array($payload))
                $payload = $payload[0];

            //set warning as default error type
            if (!is_string($error_type))
                $error_type = "warning";

            //set payload
            $payload = array(
                "error"     => $payload,
                "type"      => $error_type,
                "namespace" => $error_namespace
            );
        }
        else {
            //convert object to associative array
            if (is_string($payload))
                $payload = array("payload" => $payload);
            elseif (is_object($payload))
                $payload = get_object_vars($payload);
        }

        //if response is successful an payload is empty, append the OK message
        if($status_code == 200 && empty($payload))
            $payload = $msg_code[$status_code];

        //encode JSON
        $content = json_encode($payload, JSON_UNESCAPED_SLASHES);

        //output the response
        $this->view->disable(); //disable view output
        $this->response->setStatusCode($status_code, $msg_code[$status_code]);
        $this->response->setContentType('application/json'); //set JSON as Content-Type header
        $this->response->setContent($content);
        $this->response->send();
        die(); //exit
    }

    /**
     * Redirect to notFound error page
     * @access protected
     */
    protected function _redirectToNotFound()
    {
        $this->response->redirect($this->_baseUrl("not_found"), true);
        $this->response->send();
    }

    /**
     * Sends a mail message to user asynchronously
     * @access protected
     * @param string $method
     * @param object $data
     * @param boolean $as_action Calls the Action method in Controller
     * @throws Exception
     * @return object response
     */
    protected function _sendAsyncMailMessage($method = null, $data = null, $as_action = false)
    {
        //simple input validation
        if (empty($method))
            throw new Exception("WebController::_sendAsyncMailMessage -> method param is required.");

        if($as_action)
        {
            if(is_array($data))
                $data = json_encode($data);

            $encrypted_data = $this->cryptify->encryptForGetRequest($data);
            //guzzle request async with promise
            $url  = $this->_baseUrl("mailer/$method/$encrypted_data");

            if(APP_ENVIRONMENT == "development")
                $this->logger->debug('WebController::_sendAsyncMailMessage -> Method: '.$method.' & URL: ' . $url);

            //child method
            $this->sendAsyncRequest($url, $method);
        }
        else {
            //checks that a MailerController exists
            if(!class_exists('MailerController'))
                throw new Exception("WebController::_sendAsyncMailMessage -> A Mailer Controller is required.");

            $mailer = new \MailerController();

            //checks that a MailerController exists
            if(!method_exists($mailer, $method))
                throw new Exception("WebController::_sendAsyncMailMessage -> Method $method is not defined in Mailer Controller.");

            //call mailer class method (reflection)
            $response = $mailer->{$method}($data);

            if(is_array($response))
                $response = json_encode($response);

            //save response only for non production-environment
            if(APP_ENVIRONMENT !== "production")
                $this->logger->debug('WebController::_sendAsyncMailMessage -> Got response from MailerController:\n' . $response);

            return $response;
        } 
    }

    /**
     * Load Javascript files in Core Collection
     * @access public
     * @param array $files CSS Files to be loaded
     * @param string $collection Name of the collection
     */
    public function _loadCssFiles($files = array(), $collection = "css_view")
    {
        if (empty($files))
            return;

        //loop through CSS files
        foreach ($files as $file) {
            //check for mobile prefix
            if (!$this->client->isMobile && $file[0] === "@")
                continue;
            else
                $file = str_replace("@", "", $file);

            $this->assets->collection($collection)->addCss("css/$file");
        }
    }

    /**
     * Load Javascript files in Core Collection
     * @access public
     * @param array $files JS Files to be loaded
     * @param string $collection Name of the collection
     */
    public function _loadJavascriptFiles($files = array(), $collection = "js_view")
    {
        if (empty($files))
            return;

        //loop through JS files
        foreach ($files as $file) {
            //check for mobile prefix
            if (!$this->client->isMobile && $file[0] === "@")
                continue;
            else
                $file = str_replace("@", "", $file);

            //has dynamic params? (for example file_name.{property}.js, useful for js lang files)
            if (preg_match("/^(.{1,})\\{([a-z]{1,})\\}(.{1,})$/", $file, $regex)) {
                //lang case
                if ($regex[2] === "lang")
                    $file = $regex[1] . $this->client->lang . $regex[3];
            }

            $this->assets->collection($collection)->addCss("js/$file");
        }
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Set the client (user agent) object with its properties
     * @access private
     */
    private function _setClientObject()
    {
        //set client object from session if was already created (little perfomance)
        if ($this->session->has("client")) {
            //get client from session
            $this->client = $this->session->get("client");
            //in each request set language
            $this->translate->setLanguage($this->client->lang);
            //set HTTP protocol
            $this->client->protocol = isset($_SERVER["HTTPS"]) ? "https://" : "http://";

            return;
        }

        //first-time properties. This static properties are saved in session.
        //set language, if only one lang is supported, force it.
        if(count($this->config->app->langs) > 1)
            $this->translate->setLanguage($this->request->getBestLanguage());
        else
            $this->translate->setLanguage($this->config->app->langs[0]);

        //create a client object
        $this->client           = new \stdClass();
        $this->client->ua       = $this->request->getUserAgent();
        $this->client->lang     = $this->translate->getLanguage();
        $this->client->tokenKey = $this->security->getTokenKey();  //CSRF token key
        $this->client->token    = $this->security->getToken();     //CSRF token

        //parse user agent
        $userAgent = new UserAgent($this->request->getUserAgent($this->client->ua));
        $userAgent = $userAgent->parseUserAgent();
        //set properties
        $this->client->platform      = $userAgent['platform'];
        $this->client->browser       = $userAgent['browser'];
        $this->client->version       = $userAgent['version'];
        $this->client->short_version = $userAgent['short_version'];
        $this->client->isMobile      = $userAgent['mobile'];
        //set vars to distinguish pecific platforms
        $this->client->isIE = ($this->client->browser == "MSIE") ? true : false;
        //set legacy property
        $this->client->isLegacy = false;
        //set HTTP protocol
        $this->client->protocol = isset($_SERVER["HTTPS"]) ? "https://" : "http://";

        //save in session
        $this->session->set("client", $this->client);

        return;
    }

    /**
     * Extends client session data to set custom app properties
     * @access private
     */
    private function _extendsClientSessionData()
    {
        //get session previously created and set extended properties
        $this->client = $this->session->get("client");
        //last visited url
        $last_uri = $this->_getRequestedUrlAsArray();
        $this->client->last_uri = end($last_uri);
        //save in session
        $this->session->set("client", $this->client);
    }

    /**
     * Validate CSRF token. Basta con generar un tokenKey y token por sesión.
     * @access private
     */
    private function _checkCsrfToken()
    {
        if (!$this->request->isPost())
            return true;

        //get token from saved client session
        $session_token = isset($this->client->token) ? $this->client->token : null;
        //get sent token (crsf key is the same always)
        $sent_token = $this->request->getPost($this->client->tokenKey);

        return ($session_token === $sent_token) ? true : false;
    }
}
