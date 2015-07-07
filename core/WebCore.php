<?php
/**
 * Core Controller, includes basic and helper methods for child controllers.
 * Requires a Phalcon DI Factory Services
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

//imports
use Phalcon\Mvc\Controller;         //Phalcon Controller
use Phalcon\Exception;              //Phalcon Exception
//phalcon imports
use Phalcon\Assets\Filters\Cssmin;  //CSS resources minification
use Phalcon\Assets\Filters\Jsmin;   //JS resources minification
//CrazyCake Utils
use CrazyCake\Utils\UserAgent;      //User Agent identifier

abstract class WebCore extends Controller
{
    /* consts */
    const ASSETS_MIN_FOLDER_PATH = 'assets/';

    /**
     * abstract required methods
     */
    abstract protected function getModuleClassName($key);
    abstract protected function setAppJavascriptProperties($app_js);
    abstract protected function checkBrowserSupport();
    abstract protected function sendAsyncRequest($url = null, $method = null);

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
    protected $MSGS;

    /** ---------------------------------------------------------------------------------------------------------------
     * Constructor function
     * --------------------------------------------------------------------------------------------------------------- **/
    protected function onConstruct()
    {
        //set client object with its properties (User-Agent)
        $this->_setClientObject();
        //set message keys
        $this->MSGS = array();
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
        }
    }
    /** ---------------------------------------------------------------------------------------------------------------
     * After Execute Route: Triggered after executing the controller/action method
     * --------------------------------------------------------------------------------------------------------------- **/
    protected function afterExecuteRoute()
    {
        //for non-ajax only
        if (!$this->request->isAjax()) {
            //extend session data, last visited uri must be set here in afterExecuteRoute
            $this->_extendsClientSessionData();
            //load app assets
            $this->_loadAppAssets();
            //set javascript vars in view
            $this->_setAppJavascriptObjectForView();
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
        //is post request? (method now allowed)
        if ($method == 'POST' && !$this->request->isPost())
            $this->_sendJsonResponse(405);

        //is get request? (method now allowed)
        if ($method == 'GET' && !$this->request->isGet())
            $this->_sendJsonResponse(405);

        //validate always CSRF Token (prevents also headless browsers, POST only)
        if ($check_csrf_token && !$this->_checkCsrfToken())
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
        if ($invalid_data)
            return $send_json ? $this->_sendJsonResponse(400) : false;

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
     * Redirect to given uri
     * @param string $uri The URI to redirect
     */
    protected function _redirectTo($uri = "")
    {
        $this->response->redirect($this->_baseUrl($uri), true);
        $this->response->send();
        die();
    }

    /**
     * Redirect to notFound error page
     */
    protected function _redirectToNotFound()
    {
        $this->_redirectTo("errors/notFound");
    }

    /**
     * Dispatch to Internal Error
     * @param string $message The human error message
     * @param string $go_back_url A go-back link URL
     * @param string $object_id An option id for logic flux
     * @param string $log_error The debug message to log
     *
     */
    protected function _dispatchInternalError($message = null, $go_back_url = null, $object_id = 0, $log_error = "n/a")
    {
        //dispatch to internal
        $this->logger->info("WebCore::_dispatchInternalError -> Something ocurred (object_id: ".$object_id."). Error: ".$log_error);
        //set message
        if(!is_null($message))
            $this->view->setVar("error_message", str_replace(".", ".<br/>", $message));

        if(!is_null($go_back_url))
            $this->view->setVar("go_back", $go_back_url);

        $this->dispatcher->forward(array("controller" => "errors", "action" => "internal"));
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
            throw new Exception("WebCore::_sendAsyncMailMessage -> method param is required.");

        if($as_action) {

            if(is_array($data))
                $data = json_encode($data);

            $encrypted_data = $this->cryptify->encryptForGetRequest($data);
            //set url
            $url = $this->_baseUrl("mailer/$method/$encrypted_data");

            if(APP_ENVIRONMENT == "development")
                $this->logger->debug('WebCore::_sendAsyncMailMessage -> Method: '.$method.' & URL: ' . $url);

            //child method
            $this->sendAsyncRequest($url, $method);
        }
        else {

            //get the mailer controller name
            $mailer_class = $this->getModuleClassName("mailer");

            //checks that a MailerController exists
            if(!class_exists(str_replace('\\', '', $mailer_class)))
                throw new Exception("WebCore::_sendAsyncMailMessage -> A Mailer Controller is required.");

            $mailer = new $mailer_class();

            //checks that a MailerController exists
            if(!method_exists($mailer, $method))
                throw new Exception("WebCore::_sendAsyncMailMessage -> Method $method is not defined in Mailer Controller.");

            //call mailer class method (reflection)
            $response = $mailer->{$method}($data);

            if(is_array($response))
                $response = json_encode($response);

            //save response only for non production-environment
            if(APP_ENVIRONMENT !== "production")
                $this->logger->debug('WebCore::_sendAsyncMailMessage -> Got response from MailerController:\n' . $response);

            return $response;
        }
    }

    /**
     * Loads app assets, files are located in each module config file
     * @access protected
     */
    protected function _loadAppAssets()
    {
        //CSS Head files, already minified
        $this->_loadCssFiles($this->config->app->cssHead, 'css_head');
        //CSS libs files (for js libs)
        $this->_loadCssFiles($this->config->app->cssLibs, 'css_libs');

        //check specials cases for legacy js files
        if($this->router->getControllerName() == "errors") {
            $this->_loadJavascriptFiles($this->config->app->jsHead, 'js_head');
            $this->_loadJavascriptFiles($this->config->app->jsLegacy, 'js_libs');
            return;
        }

        //JS files loaded in head tag (already minified)
        $this->_loadJavascriptFiles($this->config->app->jsHead, 'js_head');
        //load JS lib files (already minified, loads at bottom of page)
        $this->_loadJavascriptFiles($this->config->app->jsLibs, 'js_libs');
        //load JS core files (webapp core file)
        $this->_loadJavascriptFiles($this->config->app->jsCore, 'js_core');
        //join and minify collections
        $this->_joinAssetsCollections(array(
            "css_head" => false,
            "css_libs" => false,
            "js_head" => false,
            "js_libs" => false,
            "js_core" => true,
            "js_models" => true,
            "js_dom" => true
        ), self::ASSETS_MIN_FOLDER_PATH, $this->config->app->deploy_version);
    }


    /**
     * Load Javascript files in Core Collection
     * @access protected
     * @param array $files CSS Files to be loaded
     * @param string $collection Name of the collection
     */
    protected function _loadCssFiles($files = array(), $collection = "css_libs")
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
     * @access protected
     * @param array $files JS Files to be loaded
     * @param string $collection Name of the collection
     */
    protected function _loadJavascriptFiles($files = array(), $collection = "js_models")
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
                    $file = $regex[1].$this->client->lang.$regex[3];
            }

            $this->assets->collection($collection)->addJs("js/$file");
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
        $this->client->lang     = $this->translate->getLanguage();
        $this->client->tokenKey = $this->security->getTokenKey();  //CSRF token key
        $this->client->token    = $this->security->getToken();     //CSRF token

        //parse user agent
        $userAgent = new UserAgent($this->request->getUserAgent());
        $userAgent = $userAgent->parseUserAgent();
        //set properties
        $this->client->platform      = $userAgent['platform'];
        $this->client->browser       = $userAgent['browser'];
        $this->client->version       = $userAgent['version'];
        $this->client->short_version = $userAgent['short_version'];
        $this->client->isMobile      = $userAgent['is_mobile'];
        $this->client->isLegacy      = $userAgent['is_legacy'];
        //set vars to distinguish pecific platforms
        $this->client->isIE = ($this->client->browser == "MSIE") ? true : false;
        //set HTTP protocol
        $this->client->protocol = isset($_SERVER["HTTPS"]) ? "https://" : "http://";

        //save in session
        $this->session->set("client", $this->client);

        return;
    }

    /**
     * Extends client session data
     * @access private
     */
    private function _extendsClientSessionData()
    {
        //get session previously created and set extended properties
        $this->client = $this->session->get("client");
        //get last request uri
        $this->client->requested_uri = $this->_getRequestedUri();
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

    /**
     * Set javascript vars for rendering view, call child method for customization.
     * @access private
     */
    private function _setAppJavascriptObjectForView()
    {
        //set javascript global objects
        $app_js = new \stdClass();
        $app_js->name    = $this->config->app->name;
        $app_js->baseUrl = APP_BASE_URL;
        $app_js->dev     = (APP_ENVIRONMENT == 'production') ? 0 : 1;

        //set UI properties?
        if(isset($this->config->app->ui_settings))
            $app_js->UI = (object)$this->config->app->ui_settings;

        //set custom properties
        $this->setAppJavascriptProperties($app_js);

        //send javascript vars to view as JSON enconded
        $this->view->setVar("app_js", json_encode($app_js, JSON_UNESCAPED_SLASHES));
        $this->view->setVar("client_js", json_encode($this->client, JSON_UNESCAPED_SLASHES));
    }

    /**
     * Join and Minify Assets collections for view output
     * @access private
     * @param array $collections Phalcon Assets collections, the key is a boolean minimize flag.
     * @param string $cache_path The cache path
     */
    private function _joinAssetsCollections($collections = array(), $cache_path = null, $deploy_version = "0.1")
    {
        if (empty($collections) || empty($cache_path))
            return;

        //loop through collections
        foreach ($collections as $cname => $minify) {
            $collection_exists = true;
            //handle exceptions
            try {
                $this->assets->get($cname);
            }
            catch (\Exception $e) {
                $collection_exists = false;
            }
            //check collection exists
            if (!$collection_exists)
                continue;

            $props = explode("_", $cname);
            $fname = $props[1].".".$props[0];
            $path  = PUBLIC_PATH.$cache_path.$fname;
            $uri   = $cache_path."$fname?v=".$deploy_version;

            //set assets props
            $this->assets->collection($cname)->setTargetPath($path)->setTargetUri($uri)->join(true);

            //minify assets?
            if($minify)
                $this->assets->collection($cname)->addFilter(($props[0] == "css" ? new Cssmin() : new Jsmin()));
            else
                $this->assets->collection($cname)->addFilter(new \minifiedFilter());

            //for js_dom, generate file & supress output (echo calls)
            if ($cname == "js_dom") {
                ob_start();
                $this->assets->outputJs($cname);
                ob_end_clean();
                $this->assets->js_dom = file_get_contents($path);
            }
        }
    }
}
