<?php
/**
 * Core Controller, includes basic and helper methods for child controllers.
 * Requires a Phalcon DI Factory Services
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

//imports
use Phalcon\Exception;
//phalcon imports
use Phalcon\Assets\Filters\Cssmin;  //CSS resources minification
use Phalcon\Assets\Filters\Jsmin;   //JS resources minification
//CrazyCake Utils & Traits
use CrazyCake\Utils\UserAgent;      //User Agent identifier
use CrazyCake\Services\Guzzle;

/**
 * WebCore for backend or frontend modules
 */
abstract class WebCore extends AppCore implements WebSecurity
{
    /* consts */
    const ASSETS_MIN_FOLDER_PATH = 'assets/';
    const JS_LOADER_FUNCTION     = 'core.loadModules';

    /**
     * abstract required methods
     */
    abstract protected function setAppJavascriptProperties($js_app);
    abstract protected function checkBrowserSupport($browser, $version);

    /* traits */
    use Guzzle;

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
        //set message keys
        $this->MSGS = array();

        //set client object with its properties (User-Agent)
        $this->_setClientObject();
    }
    /** ---------------------------------------------------------------------------------------------------------------
     * Init function,'$this' is the dependency injector reference
     * --------------------------------------------------------------------------------------------------------------- **/
    protected function initialize()
    {
        //Skip web core initialize for api module includes
        //Load view data only for non-ajax requests
        if ($this->request->isAjax() || MODULE_NAME == "api")
            return;

        //Set App common vars (this must be set before render any page)
        $this->view->setVar("app", $this->config->app); //app configuration vars
        $this->view->setVar("client", $this->client);   //client object
    }
    /** ---------------------------------------------------------------------------------------------------------------
     * After Execute Route: Triggered after executing the controller/action method
     * --------------------------------------------------------------------------------------------------------------- **/
    protected function afterExecuteRoute()
    {
        //Load view data only for non-ajax requests
        if ($this->request->isAjax())
            return;

        //set javascript vars in view
        $this->_setAppJavascriptObjectsForView();
        //load app assets
        $this->_loadAppAssets();
        //update client object property, request uri afterExecuteRoute event.
        $this->_updateClientObjectProp('requested_uri', $this->_getRequestedUri());
        //check browser is supported (child method)
        $supported = $this->checkBrowserSupport($this->client->browser, $this->client->short_version);
        //prevents loops
        if(!$supported && !$this->dispatcher->getPreviousControllerName()) {
            $this->dispatcher->forward(['controller' => 'errors', 'action' => 'oldBrowser']);
            $this->dispatcher->dispatch();
        }
    }
    /* --------------------------------------------------- § -------------------------------------------------------- */

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
            "404" => "Not Found",
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
     * Redirect to given uri as GET method
     * @param string $uri The URI to redirect
     * @param array $params The GET params (optional)
     */
    protected function _redirectTo($uri = "", $params = array())
    {
        //parse get params & append them to the URL
        if(!empty($params)) {

            //anonymous function
            $parser = function (&$item, $key) {
                $item = $key."=".$item;
            };

            array_walk($params, $parser);
            $uri .= "?".implode("&", $params);
        }

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
     * Check for non Ajax request, redirects to notFound error page
     */
    protected function _onlyAjax()
    {
        if(!$this->request->isAjax())
            $this->_redirectToNotFound();

        return true;
    }

    /**
     * Dispatch to Internal Error
     * @param string $message The human error message
     * @param string $go_back_url A go-back link URL
     * @param string $log_error The debug message to log
     *
     */
    protected function _dispatchInternalError($message = null, $go_back_url = null, $log_error = "n/a")
    {
        //dispatch to internal
        $this->logger->info("WebCore::_dispatchInternalError -> Something ocurred (message: ".$message."). Error: ".$log_error);

        //set message
        if(!is_null($message))
            $this->view->setVar("error_message", str_replace(".", ".<br/>", $message));

        if(!is_null($go_back_url))
            $this->view->setVar("go_back", $go_back_url);

        $this->dispatcher->forward(["controller" => "errors", "action" => "internal"]);
        $this->dispatcher->dispatch();
    }

    /**
     * Validate CSRF token. Basta con generar un tokenKey y token por sesión.
     * @access private
     */
    public function _checkCsrfToken()
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
     * Loads app assets, files are located in each module config file
     * @access protected
     */
    protected function _loadAppAssets()
    {
        //CSS Core files, already minified
        $this->_loadCssFiles($this->config->app->cssCore, 'css_core');

        //check specials cases for legacy js files
        if($this->client->isLegacy)
            return;

        //JS Core files, already minified
        $this->_loadJavascriptFiles($this->config->app->jsCore, 'js_core');

        //join and minify collections
        $this->_joinAssetsCollections([
            "css_core" => false,
            "js_core" => false,
            "js_dom" => true
        ],
        self::ASSETS_MIN_FOLDER_PATH, $this->config->app->deployVersion);
    }

    /**
     * Load Javascript files into a assets Collection
     * @access protected
     * @param array $files CSS Files to be loaded
     * @param string $collection Name of the collection
     */
    protected function _loadCssFiles($files = array(), $collection = "css_core")
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

            //append min suffix for non dev environment
            if(APP_ENVIRONMENT !== "local" && $file === "app.css")
                $file = str_replace(".css", ".min.css", $file);

            $this->assets->collection($collection)->addCss("assets/$file");
        }
    }

    /**
     * Load Javascript files into a assets Collection
     * @access protected
     * @param array $files JS Files to be loaded
     * @param string $collection Name of the collection
     */
    protected function _loadJavascriptFiles($files = array(), $collection = "js_core")
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

            //append min suffix for non dev environment
            if(APP_ENVIRONMENT !== "local" && $file === "app.js")
                $file = str_replace(".js", ".min.js", $file);

            //has dynamic params? (for example file_name.{property}.js, useful for js lang files)
            /*if (preg_match("/^(.{1,})\\{([a-z]{1,})\\}(.{1,})$/", $file, $regex)) {
                //lang case
                if ($regex[2] === "lang")
                    $file = $regex[1].$this->client->lang.$regex[3];
            }*/

            //TODO: append cdn prefix?
            //$this->assets->collection($collection)->setPrefix('http://cdn.liveon.cl/');

            $this->assets->collection($collection)->addJs("assets/$file");
        }
    }

    /**
     * Loads javascript modules. This method must be called once.
     * @param  array $modules An array of modules => args
     * @param  array $fn The loader function
     */
    protected function _loadJavascriptModules($modules = array(), $fn = self::JS_LOADER_FUNCTION)
    {
        //skip for legacy browsers
        if($this->client->isLegacy || empty($modules))
            return;

        $param = json_encode($modules, JSON_UNESCAPED_SLASHES);
        $script = "$fn($param);";
        //send javascript vars to view as JSON enconded
        $this->view->setVar("js_loader", $script);
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Set the client (user agent) object with its properties
     * @access private
     */
    private function _setClientObject()
    {
        //for API make a API simple client object
        if(MODULE_NAME == "api") {
            $this->client = (object)[
                "lang"     => "en",
                "protocol" => isset($_SERVER["HTTPS"]) ? "https://" : "http://"
            ];
            return;
        }

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
     * Update client object property and save again in session
     * @access private
     */
    private function _updateClientObjectProp($prop = "", $value = "")
    {
        if(empty($prop))
            return false;

        //set value
        $this->client->{$prop} = $value;
        //save in session
        $this->session->set("client", $this->client);
    }

    /**
     * Set javascript vars for rendering view, call child method for customization.
     * @access private
     */
    private function _setAppJavascriptObjectsForView()
    {
        //set javascript global objects
        $js_app = new \stdClass();
        $js_app->name    = $this->config->app->name;
        $js_app->version = $this->config->app->deployVersion;
        $js_app->baseUrl = $this->_baseUrl();
        $js_app->dev     = (APP_ENVIRONMENT == 'production') ? 0 : 1;

        //set custom properties
        $this->setAppJavascriptProperties($js_app);

        //set APP.UI properties?
        if(isset($this->config->app->uiSettings))
            $js_app->UI = (object)$this->config->app->uiSettings;

        //set translations?
        if(class_exists("TranslationsController"))
            $js_app->TRANS = \TranslationsController::getJavascriptTranslations();

        //send javascript vars to view as JSON enconded
        $this->view->setVar("js_app", json_encode($js_app, JSON_UNESCAPED_SLASHES));
        $this->view->setVar("js_client", json_encode($this->client, JSON_UNESCAPED_SLASHES));
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
            catch (Exception $e) {
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
                $this->assets->collection($cname)->addFilter(($props[0] == "css") ? new Cssmin() : new Jsmin());
            else
                $this->assets->collection($cname)->addFilter(new \CrazyCake\Phalcon\MinifiedFilter());

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
