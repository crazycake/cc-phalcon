<?php
/**
 * Core Controller, includes basic and helper methods for child controllers.
 * Requires a Phalcon DI Factory Services
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

//imports
use Phalcon\Exception;
//core
use CrazyCake\Phalcon\AppModule;
use CrazyCake\Helpers\UserAgent;

/**
 * WebCore for backend or frontend modules
 */
abstract class WebCore extends MvcCore implements WebSecurity
{
    /* consts */
    const ASSETS_MIN_FOLDER_PATH = "assets/";
    const JS_LOADER_FUNCTION     = "core.loadModules";

    /**
     * Set App Javascript Properties for global scope
     * @param object $js_app - The javascript app object reference
     */
    abstract protected function setAppJsProperties($js_app);

    /**
     * Checks Browser Support
     * @param string $browser - The browser family [MSIE, Chrome, Firefox, Opera, Safari]
     * @param int $version - The browser short version
     *
     */
    abstract protected function checkBrowserSupport($browser, $version);

    /**
     * User agent properties
     * @var object
     * @access public
     */
    public $client;

    /**
     * on Construct event
     */
    protected function onConstruct()
    {
        parent::onConstruct();

        //check enable SSL option
        $enableSSL = AppModule::getProperty("enableSSL");

        //set client object with its properties (User-Agent)
        $this->_setClient();

        //if enabledSSL, force redirect for non-https request
        if ( APP_ENVIRONMENT === "production"
            && isset($_SERVER["HTTP_HOST"])
            && $enableSSL
            && !$this->request->isSecureRequest()) {

            $url = "https://".$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
            $this->response->redirect($url);
            return;
        }
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
        $this->_setAppJsViewVars();
        //set app assets
        $this->_setAppAssets();
        //update client object property, request uri afterExecuteRoute event.
        $this->_updateClientProp('requestedUri', $this->getRequestedUri());

        //check browser is supported (child method)
        $supported = $this->checkBrowserSupport($this->client->browser, $this->client->shortVersion);
        //prevents loops
        if (!$supported && !$this->dispatcher->getPreviousControllerName()) {
            $this->dispatcher->forward(["controller" => "error", "action" => "oldBrowser"]);
            $this->dispatcher->dispatch();
        }
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Redirect to given uri as GET method
     * @param string $uri - The URI to redirect
     * @param array $params - The GET params (optional)
     */
    protected function redirectTo($uri = "", $params = [])
    {
        //parse get params & append them to the URL
        if (!empty($params)) {

            //anonymous function
            $parser = function (&$item, $key) {
                $item = $key."=".$item;
            };

            array_walk($params, $parser);
            $uri .= "?".implode("&", $params);
        }

        $this->response->redirect($this->baseUrl($uri), true);
        $this->response->send();
        die();
    }

    /**
     * Redirect to notFound error page
     */
    protected function redirectToNotFound()
    {
        $this->redirectTo("error/notFound");
    }

    /**
     * Check for non Ajax request, redirects to notFound error page
     */
    protected function onlyAjax()
    {
        if (!$this->request->isAjax())
            $this->redirectToNotFound();

        return true;
    }

    /**
     * Dispatch to Internal Error
     * @param string $message-  The human error message
     * @param string $go_back_url - A go-back link URL
     * @param string $log_error - The debug message to log
     *
     */
    protected function internalError($message = null, $go_back_url = null, $log_error = "n/a")
    {
        //dispatch to internal
        $this->logger->info("WebCore::internalError -> Something ocurred (message: ".$message."). Error: ".$log_error);

        //set message
        if (!is_null($message))
            $this->view->setVar("error_message", str_replace(".", ".<br/>", $message));

        if (!is_null($go_back_url))
            $this->view->setVar("go_back", $go_back_url);

        $this->dispatcher->forward(["controller" => "error", "action" => "internal"]);
        $this->dispatcher->dispatch();
    }

    /**
     * Validate CSRF token. One client token per session.
     * @access private
     */
    public function checkCsrfToken()
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
     * Loads javascript modules. This method must be called once.
     * @param array $modules - An array of modules => args
     * @param string $fn - The loader function name
     */
    protected function loadJsModules($modules = [], $fn = self::JS_LOADER_FUNCTION)
    {
        //skip for legacy browsers
        if ($this->client->isLegacy || empty($modules))
            return;

        $param  = json_encode($modules, JSON_UNESCAPED_SLASHES);
        $script = "$fn($param);";
        //send javascript vars to view as JSON enconded
        $this->view->setVar("js_loader", $script);
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Set app assets (app.css & app.js)
     * @access protected
     */
    private function _setAppAssets()
    {
        $version   = AppModule::getProperty("version");
        $staticUrl = AppModule::getProperty("staticUrl");

        $css_url = $this->staticUrl(self::ASSETS_MIN_FOLDER_PATH."app.css");
        $js_url  = $this->staticUrl(self::ASSETS_MIN_FOLDER_PATH."app.js");

        //set no-min assets for local dev
        if (APP_ENVIRONMENT === "local") {

            $css_url .= "?v=".$version;
            $js_url  .= "?v=".$version;
        }
        //special case for cdn staging or production
        else if ($staticUrl && in_array(APP_ENVIRONMENT, ["staging", "production"])) {

            $version = str_replace(".", "", $version);
            //set paths
            $css_url = str_replace(".css", "-$version.rev.css", $css_url);
            $js_url  = str_replace(".js", "-$version.rev.js", $js_url);
        }
        else {

            $css_url = str_replace(".css", ".min.css", $css_url)."?v=".$version;
            $js_url  = str_replace(".js", ".min.js", $js_url)."?v=".$version;
        }
        //s($css_url, $js_url);exit;

        //set vars
        $this->view->setVars([
            "css_url" => $css_url,
            "js_url"  => $js_url
        ]);
    }

    /**
     * Set the client (user agent) object with its properties
     * @access private
     */
    private function _setClient()
    {
        //for API make a API simple client object
        if (MODULE_NAME == "api") {

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
            $this->trans->setLanguage($this->client->lang);
            //set HTTP protocol
            $this->client->protocol = isset($_SERVER["HTTPS"]) ? "https://" : "http://";

            return;
        }

        //first-time properties. This static properties are saved in session.
        //set language, if only one lang is supported, force it.
        if (count($this->config->app->langs) > 1)
            $this->trans->setLanguage($this->request->getBestLanguage());
        else
            $this->trans->setLanguage($this->config->app->langs[0]);

        //parse user agent
        $userAgent = new UserAgent($this->request->getUserAgent());
        $userAgent = $userAgent->parseUserAgent();

        //create a client object
        $this->client = (object)[
            //props
            "lang"     => $this->trans->getLanguage(),
            //CSRF
            "tokenKey" => $this->security->getTokenKey(),
            "token"    => $this->security->getToken(),
            //UA
            "platform"     => $userAgent['platform'],
            "browser"      => $userAgent['browser'],
            "version"      => $userAgent['version'],
            "shortVersion" => $userAgent['short_version'],
            "isMobile"     => $userAgent['is_mobile'],
            "isLegacy"     => $userAgent['is_legacy'],
            //set vars to distinguish pecific platforms
            "isIE" => ($userAgent['browser'] == "MSIE") ? true : false,
            //set HTTP protocol
            "protocol" => isset($_SERVER["HTTPS"]) ? "https://" : "http://"
        ];

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
    private function _updateClientProp($prop = "", $value = "")
    {
        if (empty($prop))
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
    private function _setAppJsViewVars()
    {
        //set javascript global objects
        $js_app = (object)[
            "name"      => $this->config->app->name,
            "baseUrl"   => $this->baseUrl(),
            "staticUrl" => $this->staticUrl(),
            "dev"       => (APP_ENVIRONMENT === "production") ? 0 : 1,
            "version"   => AppModule::getProperty("version")
        ];

        //set custom properties
        $this->setAppJsProperties($js_app);

        //set translations?
        if (class_exists("TranslationController"))
            $js_app->TRANS = \TranslationController::getJsTranslations();

        //send javascript vars to view as JSON enconded
        $this->view->setVars([
            "js_app"    => json_encode($js_app, JSON_UNESCAPED_SLASHES),
            "js_client" => json_encode($this->client, JSON_UNESCAPED_SLASHES)
        ]);
    }
}
