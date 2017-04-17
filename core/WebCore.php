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
use CrazyCake\Phalcon\App;
use CrazyCake\Helpers\UserAgent;

/**
 * WebCore for backend or frontend modules
 */
abstract class WebCore extends BaseCore implements WebSecurity
{
    /* consts */
    const JS_LOADER_FUNCTION = "core.start";

    /**
     * Set App Javascript Properties for global scope
     * @param object $js_app - The javascript app object reference
     */
    abstract protected function setAppJsProperties(&$js_app);

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
     * BeforeExecuteRoute event
     */
    protected function beforeExecuteRoute()
    {
        //set client object with its properties (User-Agent)
        $this->_setClient();

        //set language translations
        $this->_setLanguage();

        //set CSRF
        $this->_setCSRF();

        //check enable SSL option and force it if enabled
        $this->_handleSSL();
    }

    /**
     * After Execute Route: Triggered after executing the controller/action method
     */
    protected function afterExecuteRoute()
    {
        //Load view data only for non-ajax requests
        if ($this->request->isAjax())
            return;

        //set app assets
        $this->_setAppAssets();
        //set js,volt vars in view
        $this->_setAppViewVars();

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

        //set url
        $url = $this->baseUrl($uri);

        //validate URI
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {

            $this->logger->debug("WebCore::redirectTo -> got an invalid URL: $url");
            $this->redirectToNotFound();
        }

        $this->response->redirect($url, true);
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
     * @param string $title -  The error title message
     * @param string $message -  The human error message
     * @param string $go_back_url - A go-back link URL
     * @param string $log_error - The debug message to log
     *
     */
    protected function internalError($title = null, $message = null, $go_back_url = null, $log_error = "n/a")
    {
        //log error
        $this->logger->info("WebCore::internalError -> something ocurred (message: ".$message."). Error: ".$log_error);

        //special case for ajax
        if ($this->request->isAjax())
            $this->jsonResponse(200, $message, "error");

        //set title
        if (!is_null($title))
            $this->view->setVar("error_title", $title);

        //set message
        if (!is_null($message))
            $this->view->setVar("error_message", str_replace(".", ".<br/>", $message));

        //go back url
        if (!is_null($go_back_url))
            $this->view->setVar("go_back", $go_back_url);

        //dispatch
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

        return $session_token == $sent_token;
    }

    /**
     * Loads javascript modules. This method must be called once.
     * @param array $modules - An array of modules => args
     * @param string $fn - The loader function name
     */
    protected function loadJsModules($modules = [], $fn = self::JS_LOADER_FUNCTION)
    {
        //skip for legacy browsers
        if ($this->client->isLegacy)
            return;

        $param  = empty($modules) ? '' : json_encode($modules, JSON_UNESCAPED_SLASHES);
        $script = "$fn($param);";
        //send javascript vars to view as JSON enconded
        $this->view->setVar("js_loader", $script);
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Set the client (user agent) object with its properties
     * @access private
     */
    private function _setClient()
    {
        //parse user agent
        $ua = new UserAgent($this->request->getUserAgent());
        $ua = $ua->parseUserAgent();

        //create a client object
        $this->client = (object)[
            //UA
            "platform"     => $ua["platform"],
            "browser"      => $ua["browser"],
            "version"      => $ua["version"],
            "shortVersion" => $ua["short_version"],
            "isMobile"     => $ua["is_mobile"],
            "isLegacy"     => $ua["is_legacy"],
            "requestedUri" => $this->getRequestedUri(),
            "protocol"     => isset($_SERVER["HTTPS"]) ? "https://" : "http://"
        ];
    }

    /**
     * Set app language for translations
     * @access private
     */
    private function _setLanguage()
    {
        // get langs config (set by App)
        $langs = (array)$this->config->langs;

        // set default lang if only one available
        if (count($langs) == 1) {
            $lang = current($langs);
        }
        else {

            // load lang from session?
            if($this->session->has("lang"))
                $lang = $this->session->get("lang");
            else
                $lang = $this->request->getBestLanguage();
        }

		//filter lang
		$lang = substr(trim(strtolower($lang)), 0, 2);

        // set client language
        $this->client->lang = $lang;

        // set translation service
        if(!is_null($this->trans)) {
            $this->trans->setLanguage($lang);
        }
    }

    /**
     * Set CSRF token key and value
     * @access private
     */
    private function _setCSRF()
    {
        //check if CSRF was already created
        if (!$this->session->has("csrf")) {

            $key   = $this->security->getTokenKey();
            $value = $this->security->getToken();

            //save it in session
            $this->session->set("csrf", [$key, $value]);
        }
        else {
            //set vars
            list($key, $value) = $this->session->get("csrf");
        }

        //update client props
        $this->client->tokenKey = $key;
        $this->client->token    = $value;
    }

    /**
     * Handles SSL connections
     * @access private
     */
    private function _handleSSL()
    {
		$scheme = getenv("APP_SCHEME") ?: false;

        if($this->request->isSecure() || $scheme != "https")
            return;

        //if enabledSSL, force redirect for non-https request
        $url = "https://".$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
        $this->response->redirect($url);
    }

    /**
     * Set app assets (app.css & app.js)
     * @access protected
     */
    private function _setAppAssets()
    {
        $version = $this->config->version;

        $css_url = $this->staticUrl("assets/app.css");
        $js_url  = $this->staticUrl("assets/app.js");

        //set revision file for non local env
        if (!is_file(PUBLIC_PATH."assets/app.js")) {

            $version = (int)str_replace(".", "", $version);
            $css_url = str_replace(".css", "-$version.rev.css", $css_url);
            $js_url  = str_replace(".js", "-$version.rev.js", $js_url);
            //$css_url = str_replace(".css", ".min.css", $css_url)."?v=".$version;
            //$js_url  = str_replace(".js", ".min.js", $js_url)."?v=".$version;
        }
        //s($css_url, $js_url);exit;

        //set vars
        $this->view->setVars([
            "css_url" => $css_url,
            "js_url"  => $js_url
        ]);
    }

    /**
     * Set javascript vars for rendering view, call child method for customization.
     * @access private
     */
    private function _setAppViewVars()
    {
        //set javascript global objects
        $js_app = (object)[
            "dev"       => (APP_ENV == "production") ? 0 : 1,
            "version"   => $this->config->version,
            "name"      => $this->config->name,
            "baseUrl"   => $this->baseUrl(),
            "staticUrl" => $this->staticUrl(),
        ];

        //set custom properties
        $this->setAppJsProperties($js_app);

        //css lazy loading properties
        if(isset($js_app->cssLazy) && $js_app->cssLazy)
            $js_app->cssLazy = str_replace("/app", "/lazy", $this->view->getVar("css_url"));

        //set translations?
        if (class_exists("TranslationController"))
            $js_app->TRANS = \TranslationController::getJsTranslations();

        //send javascript vars to view as JSON enconded
        $this->view->setVars([
            "config"    => $this->config, //app configuration vars
            "client"    => $this->client,  //client object
            "js_app"    => json_encode($js_app, JSON_UNESCAPED_SLASHES),
            "js_client" => json_encode($this->client, JSON_UNESCAPED_SLASHES)
        ]);
    }
}
