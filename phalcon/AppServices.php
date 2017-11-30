<?php
/**
 * Phalcon App Services files (Dependency Injector)
 * @link http://docs.phalconphp.com/en/latest/reference/di.html
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Phalcon;

//phalcon
use Phalcon\Exception;

//import plugins
require "AppPlugins.php";

/**
 * Phalcon Services Loader
 */
class AppServices
{
	/**
	 * Phalcon config object
	 * @var object
	 */
	private $config;

	/**
	 * Constructor
	 * @param object $loaderObj - The app loader instance
	 */
	public function __construct($config)
	{
		// set class vars
		$this->config = new \Phalcon\Config($config);
	}

	/**
	 * Get the DI
	 * @return object - The Dependency Injector
	 */
	public function getDI()
	{
		if (MODULE_NAME == "cli")
			return $this->_getCliDI();

		return $this->_getDefaultDI();
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Set DI for CLI app
	 */
	private function _getCliDI()
	{
		// get a new Micro DI
		$di = new \Phalcon\DI\FactoryDefault\CLI();
		$this->_setMainServices($di);
		$this->_setDatabaseServices($di);
		$this->_setTranslationService($di);

		return $di;
	}

	/**
	 * Set DI for Mvc app
	 */
	private function _getDefaultDI()
	{
		// get a new Micro DI
		$di = new \Phalcon\DI\FactoryDefault();
		$this->_setMainServices($di);
		$this->_setDatabaseServices($di);
		$this->_setTranslationService($di);
		$this->_setSessionService($di);
		$this->_setViewService($di);

		if(MODULE_NAME != "api")
			$this->_setBrowserServices($di);

		return $di;
	}

	/**
	 * Set Main services
	 * @param object $di - The DI object
	 */
	private function _setMainServices(&$di)
	{
		// set the config
		$di->setShared("config", $this->config);

		$conf = $this->config;

		// the URL component is used to generate all kind of urls in the application
		$di->setShared("url", function() use ($conf) {

			$url = new \Phalcon\Mvc\Url();
			// base URL
			$url->setBaseUri(APP_BASE_URL);

			// get static url
			$static_url = $conf->staticUrl ?? false;

			// set static uri for assets, cdn only for production
			if (APP_ENV != "production" || !$static_url)
				$static_url = APP_BASE_URL;

			$url->setStaticBaseUri($static_url);

			return $url;
		});

		// logger adapter
		$di->setShared("logger", function() {

			// date now
			$log_file = date("d-m-Y");

			// special case for cli (log is not saved as 'httpd user' as default)
			if(MODULE_NAME == "cli")
				$log_file = "cli_".$log_file;

			$logger = new \Phalcon\Logger\Adapter\File(STORAGE_PATH."logs/".$log_file.".log");
			return $logger;
		});

		// basic http security
		$di->setShared("security", function() {

			$security = new \Phalcon\Security();
			// set the password hashing factor to X rounds
			$security->setWorkFactor(12);
			return $security;
		});

		// phalcon crypt service
		$di->setShared("crypt", function() use ($conf) {

			$crypt = new \Phalcon\Crypt();
			$crypt->setKey($conf->cryptKey);
			return $crypt;
		});

		// extended encryption, cryptify adapter (cryptography helper)
		if (class_exists("\CrazyCake\Helpers\Cryptify")) {

			$di->setShared("cryptify", function() use ($conf) {
				return new \CrazyCake\Helpers\Cryptify($conf->cryptKey);
			});
		}

		// kint options
		if (class_exists("\Kint")) {

			\Kint::$theme     = "solarized";
			\Kint::$maxLevels = 0;
		}
	}

	/**
	 * Set Database Services [MySQL, Mongo]
	 * Uses PECL Driver
	 * @param object $di - The DI object
	 */
	private function _setDatabaseServices(&$di)
	{
		//mongo adapter
		if(!empty($this->config->mongoService))
			$this->_setMongoService($di);

		//mysql adapter
		if(isset($this->config->mysqlService) && !$this->config->mysqlService)
			return;

		$this->_setMysqlService($di);
	}

	/**
	 * Set MySQL Service
	 * @param object $di - The DI object
	 */
	private function _setMysqlService(&$di)
	{
		// mysql adapter
		$di->setShared("db", function() {

			$db_conf = [
				"host"     => getenv("MYSQL_HOST") ?: "mysql",
				"port"     => 3306,
				"dbname"   => "app",
				"username" => getenv("MYSQL_USER") ?: "root",
				"password" => getenv("MYSQL_PWD") ?: "mysql24681214?",
				"options"  => [\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"]
			];

			return new \Phalcon\Db\Adapter\Pdo\Mysql($db_conf);
		});
	}

	/**
	 * Set Mongo Service
	 * @param object $di - The DI object
	 */
	private function _setMongoService(&$di)
	{
		$di->setShared("mongo", function() {

			$mongo = new \MongoDB\Client(getenv("MONGO_HOST") ?: "mongodb://mongo");

			return $mongo->{getenv("MONGO_DB") ?: "app" };
		});

		$di->setShared("collectionManager", function() {
			return new \Phalcon\Mvc\Collection\Manager();
		});
	}

	/**
	 * Set Translation Service
	 * GetText adapter (multi-lang support)
	 * @param object $di - The DI object
	 */
	private function _setTranslationService(&$di)
	{
		// check if langs are set
		if (empty($this->config->langs))
			return;

		$conf = $this->config;

		$di->setShared("trans", function() use ($conf) {

			return new \CrazyCake\Helpers\GetText([
				"domain"    => "app",
				"supported" => (array)$conf->langs,
				"directory" => APP_PATH."langs/"
			]);
		});
	}

	/**
	 * Set session service
	 * @param object $di - The DI object
	 */
	private function _setSessionService(&$di)
	{
		$conf = $this->config;

		// session adapter
		$di->setShared("session", function() use ($conf) {

			$expiration = 3600*($conf->sessionExpiration ?? 8); //hours

			//default session
			if(empty($conf->redisSession)) {

				$session = new \Phalcon\Session\Adapter\Files([
					"uniqueId" => MODULE_NAME
				]);
			}
			//redis session (requires extension)
			else {

				$session = new \Phalcon\Session\Adapter\Redis([
					"uniqueId" => MODULE_NAME,
					"host"     => getenv("REDIS_HOST") ?: "redis",
					"lifetime" => $expiration,
					"prefix"   => "_".strtoupper($conf->namespace)."_"
				]);
			}

			// set session name (domain & cookie)
			$session->setName($conf->namespace);
			// start session
			if (!$session->isStarted()) {

				//session TTL
				session_set_cookie_params($expiration);
				$session->start();
			}

			return $session;
		});
	}

	/**
	 * Set View services
	 * @param object $di - The DI object
	 */
	private function _setViewService(&$di)
	{
		// setting up the view component
		$view_engines = [
			".volt" => function($view, $di_instance) {
				// instance a new volt engine
				$volt = new \Phalcon\Mvc\View\Engine\Volt($view, $di_instance);
				// set volt engine options
				$volt->setOptions([
					"compiledPath"      => STORAGE_PATH."cache/",
					"compiledSeparator" => "_",
				]);
				// get compiler
				$compiler = $volt->getCompiler();

				//++ Binds some PHP functions to volt

				//++ str_replace
				$compiler->addFunction("replace", "str_replace");
				//++ preg_replace
				$compiler->addFunction("preg_replace", "preg_replace");
				//++ substr
				$compiler->addFunction("substr", "substr");
				//++ strrpos
				$compiler->addFunction("strrpos", "strrpos");
				//++ strtotime
				$compiler->addFunction("strtotime", "strtotime");
				//++ number_format
				$compiler->addFunction("number_format", "number_format");
				//++ in_array
				$compiler->addFunction("in_array", "in_array");
				//++ resizedImagePath
				$compiler->addFunction("resized_image_path", function($resolvedArgs, $exprArgs) {
					return "CrazyCake\Helpers\Images::resizedImagePath(".$resolvedArgs.")";
				});

				return $volt;
			},
			".phtml" => "Phalcon\Mvc\View\Engine\Php",
		];

		// simple view service (used for mailing templates)
		$di->setShared("simpleView", function() use (&$view_engines) {

			//simpleView
			$view = new \Phalcon\Mvc\View\Simple();
			//set directory views
			$view->setViewsDir(PROJECT_PATH."ui/volt/");
			//register volt view engine
			$view->registerEngines($view_engines);

			return $view;
		});

		// skip api for view service
		if(MODULE_NAME == "api")
			return;

		// set view service
		$di->setShared("view", function() use (&$view_engines) {

			$view = new \Phalcon\Mvc\View();
			//set directory views
			$view->setViewsDir(PROJECT_PATH."ui/volt/");
			//register volt view engine
			$view->registerEngines($view_engines);

			return $view;
		});
	}

	/**
	 * Set Browser services
	 * @param object $di - The DI object
	 */
	private function _setBrowserServices(&$di)
	{
		// dispatcher event manager
		$di->setShared("dispatcher", function() {

			$manager = new \Phalcon\Events\Manager;
			//Handle exceptions and not-found exceptions using Exceptions Plugin
			$manager->attach("dispatch:beforeException", new ExceptionsPlugin);

			$dispatcher = new \Phalcon\Mvc\Dispatcher;
			$dispatcher->setEventsManager($manager);

			return $dispatcher;
		});

		// cookies
		$di->setShared("cookies", function() {

			$cookies = new \Phalcon\Http\Response\Cookies();
			//no encryption
			$cookies->useEncryption(false);

			return $cookies;
		});

		// flash messages
		$di->setShared("flash", function() {

			$flash = new \Phalcon\Flash\Session([
				"success" => "success",
				"error"   => "alert",
				"notice"  => "notice",
				"warning" => "warning"
			]);
			// disable auto escape
			$flash->setAutoescape(false);

			return $flash;
		});
	}
}
