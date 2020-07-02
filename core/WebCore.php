<?php
/**
 * Core Controller
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Core;

use CrazyCake\Phalcon\App;
use CrazyCake\Controllers\Translations;
use CrazyCake\Helpers\UserAgent;
use CrazyCake\Helpers\JSON;

/**
 * WebCore for frontend modules
 */
abstract class WebCore extends HttpCore implements WebSecurity
{
	/**
	 * Checks Browser Support
	 * @param String $browser - The browser family [MSIE, Chrome, Firefox, Opera, Safari]
	 * @param Integer $version - The browser short version
	 *
	 */
	abstract protected function checkBrowserSupport($browser, $version);

	/**
	 * User agent properties
	 * @var Object
	 */
	public $client;

	/**
	 * BeforeExecuteRoute event
	 */
	public function beforeExecuteRoute()
	{
		// set client object with its properties (User-Agent)
		$this->_setClient();

		// browser support
		$supported = $this->checkBrowserSupport($this->client->browser, $this->client->shortVersion);

		// redirect if not supported
		if (!$supported && $this->router->getControllerName() != "error")
			$this->redirectTo("error/oldBrowser");

		// set CSRF
		$this->_setCSRF();

		// event (for session)
		if (method_exists($this, "onBeforeInitialize"))
			$this->onBeforeInitialize();
	}

	/**
	 * After Execute Route: Triggered after executing the controller/action method
	 */
	public function afterExecuteRoute()
	{
		// load view data only for non-ajax requests
		if ($this->request->isAjax())
			return;

		// set app assets
		$this->_setAppAssets();
		// set js & volt vars in view
		$this->_setAppViewVars();

		// event
		if (method_exists($this, "onBeforeRender"))
			$this->onBeforeRender();
	}

	/**
	 * Redirect to given URI as GET method
	 * @param String $uri - The URI to redirect
	 * @param Integer $code - The http 3xx code
	 */
	protected function redirectTo($uri = "/", $code = 302)
	{
		// set url
		$url = substr($uri, 0, 4) == "http" ? $uri : ($this->baseUrl($uri[0] == "/" ? substr($uri, 1) : $uri));
		// ss($url);

		// validate URI
		if (filter_var($url, FILTER_VALIDATE_URL) === false) {

			$this->logger->debug("WebCore::redirectTo -> got an invalid URL: $url");
			$url = $this->baseUrl("error/notFound");
		}

		// is ajax?
		if ($this->request->isAjax() || MODULE_NAME == "api")
			return $this->jsonResponse(200, ["redirect" => $url]);

		$this->response->redirect($url, true, $code);
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
	 * @return Boolean
	 */
	protected function onlyAjax()
	{
		if (!$this->request->isAjax())
			$this->redirectToNotFound();

		return true;
	}

	/**
	 * Validate CSRF token. One client token per session.
	 * @return Boolean
	 */
	public function checkCsrfToken()
	{
		if (!$this->request->isPost())
			return true;

		if (empty($this->client->csrfToken))
			return false;

		return $this->client->csrfToken == $this->request->getHeader('X-Csrf-Token');
	}

	/**
	 * Load js application
	 * @param Array $store - The store data
	 * @return String
	 */
	protected function initJsApp($store = null)
	{
		// set js global object
		$data = (object)[
			"env"       => APP_ENV,
			"version"   => $this->config->version,
			"name"      => $this->config->name,
			"baseUrl"   => $this->baseUrl(),
			"staticUrl" => $this->staticUrl(),
			"flash"     => $this->flash->getMessages() ?: []
		];

		// set user agent
		$data->UA = $this->client;

		// don't expose sensitive data
		unset($data->UA->csrfToken);

		// set translations
		$data->TRANS = Translations::defaultJsTranslations();

		$output = "`".JSON::safeEncode($store ?: (object)[])."`";

		$js = "APP = JSON.parse(`".JSON::safeEncode($data)."`);\n";

		$js .= "document.addEventListener('DOMContentLoaded', function() { init($output); }, false);\n";

		$js .= "console.log(`App ".$this->config->version." [".\Phalcon\Version::get()." => ".CORE_VERSION."] ".number_format((float)(microtime(true) - APP_TS), 3, ".", "")." s.`);";

		return $js;
	}



	/**
	 * Set the client (user agent) object with its properties
	 */
	private function _setClient()
	{
		// parse user agent
		$ua = (new UserAgent($this->request->getUserAgent()))->parseUserAgent();

		// create a client object
		$this->client = (object)[
			"platform"     => $ua["platform"],
			"browser"      => $ua["browser"],
			"version"      => $ua["version"],
			"shortVersion" => $ua["short_version"],
			"isMobile"     => $ua["is_mobile"],
			"lang"         => $this->trans->getLanguage(),
			"bundle"       => $this->config->version,
			"requestedUri" => $this->getRequestedUri()
		];
	}

	/**
	 * Set CSRF token key and value
	 */
	private function _setCSRF()
	{
		// check if CSRF was already created
		if (!$this->session->has("csrfToken")) {

			$token = $this->security->getToken();

			// save it in session
			$this->session->set("csrfToken", $token);

			// ! remove old approach
			if ($this->session->has("csrf")) $this->session->remove("csrf");
		}
		else {

			$token = $this->session->get("csrfToken");
		}

		// update client props
		$this->client->csrfToken = $token;
	}

	/**
	 * Set app assets (app.css & app.js)
	 */
	private function _setAppAssets()
	{
		$version = $this->config->version;

		$css_url = $this->staticUrl("assets/app.css");
		$js_url  = $this->staticUrl("assets/app.js");

		// set revision file for non local env
		if (!is_file(PUBLIC_PATH."assets/app.js") || !is_file(PUBLIC_PATH."assets/app.css")) {

			$version = str_replace(".", "", $version);
			$css_url = str_replace(".css", "-$version.rev.css", $css_url);
			$js_url  = str_replace(".js", "-$version.rev.js", $js_url);
		}
		// for dev always set a random version
		else {

			$css_url .= "?v=".uniqid();
			$js_url  .= "?v=".uniqid();
		}
		//ss($css_url, $js_url);

		$this->view->setVars([
			"css_url" => $css_url,
			"js_url"  => $js_url
		]);
	}

	/**
	 * Set javascript vars for rendering view, call child method for customization.
	 */
	private function _setAppViewVars()
	{
		$this->view->setVars([
			"config" => $this->config,
			"client" => $this->client,
		]);
	}
}
