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
     * Set App Javascript Properties for global scope
     * @param object $js_app - The javascript app object reference
     */
    abstract protected function setAppJavascriptProperties($js_app);

    /**
     * Checks Browser Support
     * @param string $browser - The browser family [MSIE, Chrome, Firefox, Opera, Safari]
     * @param int $version - The browser short version
     *
     */
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

    /**
     * on Construct event
     */
    protected function onConstruct()
    {
        //set message keys
        $this->MSGS = array();

        //set client object with its properties (User-Agent)
        $this->_setClientObject();
    }

    /**
     * Called if the event ‘beforeExecuteRoute’ is executed with success
     */
    protected function initialize()
    {
        //Skip web core initialize for api module includes
        //Load view data only for non-ajax requests
        if ($this->request->isAjax() || MODULE_NAME == "api")
            return;

        //Set App common vars (this must be set before render any page)
        $this->view->setVars([
            "app"    => $this->config->app,  //app configuration vars
            "client" => $this->client        //client object
        ]);
    }

    /**
     * After Execute Route: Triggered after executing the controller/action method
     */
    protected function afterExecuteRoute()
    {
        //Load view data only for non-ajax requests
        if ($this->request->isAjax())
            return;

        //set javascript vars in view
        $this->_setAppJavascriptObjectsForView();
        //set app assets
        $this->_setAppAssets();
        //update client object property, request uri afterExecuteRoute event.
        $this->_updateClientObjectProp('requestedUri', $this->_getRequestedUri());
        //check browser is supported (child method)
        $supported = $this->checkBrowserSupport($this->client->browser, $this->client->shortVersion);
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
     * @param string $status_code - HTTP Response code like 200, 404, 500, etc.
     * @param mixed $payload - Payload to send, array or string.
     * @param bool $error_type - Can be ```success, warning, info, alert, secondary```.
     * @param mixed [string|null] $error_namespace - For Javascript event handlers.
     */
    protected function _sendJsonResponse($status_code = 200, $payload = null, $error_type = false, $error_namespace = null)
    {
        $msg_code = [
            "200" => "OK",
            "400" => "Bad Request",
            "403" => "Forbidden",
            "404" => "Not Found",
            "405" => "Method Not Allowed",
            "498" => "Token expired/invalid",
            "500" => "Server Error"
        ];

        //is payload an app error?
        if ($error_type) {

            //only one error at a time
            if (is_array($payload))
                $payload = $payload[0];

            //set warning as default error type
            if (!is_string($error_type))
                $error_type = "warning";

            //set payload
            $payload = [
                "error"     => $payload,
                "type"      => $error_type,
                "namespace" => $error_namespace
            ];
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
     * Sends a simple text response
     * @param  mixed [string|array] $text - Any text string
     */
    protected function _sendTextResponse($text = "OK"){

        if(is_array($text) || is_object($text))
            $text = json_encode($text, JSON_UNESCAPED_SLASHES);

        //output the response
        $this->view->disable(); //disable view output
        $this->response->setStatusCode(200, "OK");
        $this->response->setContentType('text/html');
        $this->response->setContent($text);
        $this->response->send();
        die(); //exit
    }

    /**
     * Redirect to given uri as GET method
     * @param string $uri - The URI to redirect
     * @param array $params - The GET params (optional)
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
     * @param string $message-  The human error message
     * @param string $go_back_url - A go-back link URL
     * @param string $log_error - The debug message to log
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
     * Validate CSRF token. One client token per session.
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
     * Set app assets (app.css & app.js)
     * @access protected
     */
    protected function _setAppAssets()
    {
        $version = isset($this->config->app->deployVersion) ? $this->config->app->deployVersion : "0.0.1";

        $css_url = $this->_baseUrl(self::ASSETS_MIN_FOLDER_PATH."app.min.css?v=$version");
        $js_url  = $this->_baseUrl(self::ASSETS_MIN_FOLDER_PATH."app.min.js?v=$version");

        if(APP_ENVIRONMENT === "local") {
            $css_url = str_replace(".min.css", ".css", $css_url);
            $js_url  = str_replace(".min.js", ".js", $js_url);
        }

        //set vars
        $this->view->setVars([
            "css_url" => $css_url,
            "js_url"  => $js_url
        ]);
    }

    /**
     * Loads javascript modules. This method must be called once.
     * @param array $modules - An array of modules => args
     * @param string $fn - The loader function name
     */
    protected function _loadJavascriptModules($modules = array(), $fn = self::JS_LOADER_FUNCTION)
    {
        //skip for legacy browsers
        if($this->client->isLegacy || empty($modules))
            return;

        $param  = json_encode($modules, JSON_UNESCAPED_SLASHES);
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
        $this->client->shortVersion  = $userAgent['short_version'];
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
     * @param string $prop - The property name
     * @param string $value - The property value
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
        $js_app->dev     = (int)(APP_ENVIRONMENT === "production");

        //set custom properties
        $this->setAppJavascriptProperties($js_app);

        //set translations?
        if(class_exists("TranslationsController"))
            $js_app->TRANS = \TranslationsController::getJavascriptTranslations();

        //send javascript vars to view as JSON enconded
        $this->view->setVars([
            "js_app"    => json_encode($js_app, JSON_UNESCAPED_SLASHES),
            "js_client" => json_encode($this->client, JSON_UNESCAPED_SLASHES)
        ]);
    }
}
