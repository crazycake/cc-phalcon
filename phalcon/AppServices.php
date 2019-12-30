<?php
/**
 * Phalcon App Services files (Dependency Injector)
 * @link http://docs.phalconphp.com/en/latest/reference/di.html
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Phalcon;

require "AppPlugins.php";

/**
 * Phalcon Services Loader
 */
class AppServices
{
	/**
	 * Phalcon config object
	 * @var Object
	 */
	private $config;

	/**
	 * Constructor
	 * @param Object $loaderObj - The app loader instance
	 */
	public function __construct($config)
	{
		$this->config = new \Phalcon\Config($config);
	}

	/**
	 * Get the DI
	 * @return Object - The Dependency Injector
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
		$this->_setTranslationServices($di);

		return $di;
	}

	/**
	 * Set DI for Mvc app
	 */
	private function _getDefaultDI()
	{
		// get a new Factory DI
		$di = new \Phalcon\DI\FactoryDefault();

		$this->_setMainServices($di);
		$this->_setDatabaseServices($di);
		$this->_setTranslationServices($di);
		$this->_setViewServices($di);

		if (MODULE_NAME != "api") {

			$this->_setSessionServices($di);
			$this->_setClientServices($di);
		}

		return $di;
	}

	/**
	 * Set Main services
	 * @param Object $di - The DI object
	 */
	private function _setMainServices(&$di)
	{
		$conf = $this->config;

		// set the config
		$di->setShared("config", $conf);

		// the URL component is used to generate all kind of urls in the application
		$di->setShared("url", function() use ($conf) {

			$url = new \Phalcon\Mvc\Url();
			// base URL
			$url->setBaseUri(APP_BASE_URL);

			// get static url
			$static_url = $conf->staticUrl ?? APP_BASE_URL;

			$url->setStaticBaseUri($static_url);

			return $url;
		});

		// global logger adapter
		$di->setShared("logger", function() {

			// date now
			$file = date("d-m-Y");

			return new \Phalcon\Logger\Adapter\File(STORAGE_PATH."logs/$file.log");
		});

		$di->setShared("stdout", function() {

			$stream = new \Phalcon\Logger\Adapter\Stream("php://stdout");

			$ip = php_sapi_name() == "cli" ? "CLI" : \CrazyCake\Core\WebCore::getClientIP();

			$stream->setFormatter(new \Phalcon\Logger\Formatter\Line("[%date%][%type%][$ip] %message%"));

			return $stream;
		});

		// basic http security
		$di->setShared("security", function() {

			$security = new \Phalcon\Security();
			// set the password hashing factor to X rounds
			$security->setWorkFactor(12);
			return $security;
		});

		// extended encryption (cryptography helper)
		$di->setShared("cryptify", function() use ($conf) {

			return new \CrazyCake\Helpers\Cryptify($conf->cryptKey ?? $conf->namespace);
		});

		// kint options
		if (class_exists("\Kint")) {

			\Kint::$max_depth = 0;
			\Kint::$aliases[] = "ss";
		}
	}

	/**
	 * Set Database Services [MySQL, Mongo]
	 * Uses PECL Driver
	 * @param Object $di - The DI object
	 */
	private function _setDatabaseServices(&$di)
	{
		//mongo adapter
		if (!empty($this->config->mongoService))
			$this->_setMongoService($di);

		//mysql adapter
		if (!empty($this->config->mysqlService))
			$this->_setMysqlService($di);
	}

	/**
	 * Set MySQL Service
	 * @param Object $di - The DI object
	 */
	private function _setMysqlService(&$di)
	{
		// mysql adapter
		$di->setShared("mysql", function() {

			return new \Phalcon\Db\Adapter\Pdo\Mysql([
				"host"     => getenv("MYSQL_HOST") ?: "mysql",
				"port"     => 3306,
				"dbname"   => "app",
				"username" => getenv("MYSQL_USER") ?: "root",
				"password" => getenv("MYSQL_PWD") ?: "mysql24681214?",
				"options"  => [\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"]
			]);
		});
	}

	/**
	 * Set Mongo Service
	 * @param Object $di - The DI object
	 */
	private function _setMongoService(&$di)
	{
		$host = getenv("MONGO_HOST") ?: "mongodb://mongo";

		$di->setShared("mongo", function() use ($host) {

			return (new \MongoDB\Client($host))->{getenv("MONGO_DB") ?: "app"};
		});

		$di->setShared("mongoManager", function() use ($host) {

			return new \MongoDB\Driver\Manager($host);
		});
	}

	/**
	 * Set Translation Services
	 * GetText adapter (multi-lang support)
	 * @param Object $di - The DI object
	 */
	private function _setTranslationServices(&$di)
	{
		// check if langs are set
		if (empty($this->config->langs)) return;

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
	 * Set session services
	 * @param Object $di - The DI object
	 */
	private function _setSessionServices(&$di)
	{
		$conf = $this->config;

		// session adapter
		$di->setShared("session", function() use ($conf) {

			$expiration = 3600*($conf->sessionExpiration ?? 8); // hours

			$options = [
				"uniqueId"   => $conf->namespace,
				"lifetime"   => $expiration,
				"prefix"     => "_".strtoupper($conf->namespace)."_",
				"persistent" => false,
				"index"      => 1
			];

			if (!empty($conf->sessionFiles))
				$session = new \Phalcon\Session\Adapter\Files($options);
			else
				$session = new \Phalcon\Session\Adapter\Redis(array_merge($options, ["host" => getenv("REDIS_HOST") ?: "redis"]));

			// set domain for cookie params
			$host = explode('.', parse_url(APP_BASE_URL, PHP_URL_HOST));

			// session exceptions for shared cookies domain
			session_set_cookie_params([
				"lifetime" => $expiration,
				"path"     => "/",
				"domain"   => getenv("SESSION_DOMAIN") ?: implode(".", $host),
				"secure"   => getenv("APP_HTTPS_ONLY") ?: false,
				"httpOnly" => true,
				//"sameSite" => "Strict"
			]);

			// set session name & start
			$session->setName(getenv("SESSION_NAME") ?: $conf->namespace);
			$session->start();

			return $session;
		});

		// flash messages
		$di->setShared("flash", function() {

			$flash = new \Phalcon\Flash\Session();
			// disable auto escape
			$flash->setAutoescape(false);

			return $flash;
		});
	}

	/**
	 * Set Client services
	 * @param Object $di - The DI object
	 */
	private function _setClientServices(&$di)
	{
		// dispatcher event manager
		$di->setShared("dispatcher", function() {

			$manager = new \Phalcon\Events\Manager();
			// handle exceptions and not-found exceptions using Exceptions Plugin
			$manager->attach("dispatch:beforeException", new ExceptionsPlugin);

			$dispatcher = new \Phalcon\Mvc\Dispatcher;
			$dispatcher->setEventsManager($manager);

			return $dispatcher;
		});

		// cookies
		$di->setShared("cookies", function() {

			$cookies = new \Phalcon\Http\Response\Cookies();
			// no encryption
			$cookies->useEncryption(false);

			return $cookies;
		});
	}

	/**
	 * Set View services
	 * @param Object $di - The DI object
	 */
	private function _setViewServices(&$di)
	{
		// setting up the view component
		$engines = [

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
				// binds some PHP functions to volt
				self::setVoltCompilerFunctions($compiler);

				return $volt;
			}
		];

		// simple view service (used for mailing templates)
		$di->setShared("simpleView", function() use (&$engines) {

			//simpleView
			$view = new \Phalcon\Mvc\View\Simple();
			//set directory views
			$view->setViewsDir(PROJECT_PATH."ui/volt/");
			//register volt view engine
			$view->registerEngines($engines);

			return $view;
		});

		// skip api for view service
		if (MODULE_NAME == "api")
			return;

		// set view service
		$di->setShared("view", function() use (&$engines) {

			$view = new \Phalcon\Mvc\View();
			//set directory views
			$view->setViewsDir(PROJECT_PATH."ui/volt/");
			//register volt view engine
			$view->registerEngines($engines);

			return $view;
		});
	}

	/**
	 * Sets volt compiler functions
	 * @param Object $compiler - The compiler object
	 */
	public static function setVoltCompilerFunctions(&$compiler)
	{
		//++ str_replace
		$compiler->addFunction("sreplace", "str_replace");
		//++ preg_replace
		$compiler->addFunction("preg_replace", "preg_replace");
		//++ substr
		$compiler->addFunction("substr", "substr");
		//++ strrpos
		$compiler->addFunction("strrpos", "strrpos");
		//++ strtotime
		$compiler->addFunction("strtotime", "strtotime");
		//++ intval
		$compiler->addFunction("intval", "intval");
		//++ number_format
		$compiler->addFunction("number_format", "number_format");
		//++ in_array
		$compiler->addFunction("in_array", "in_array");
		//++ resizedImagePath
		$compiler->addFunction("resized_image_path", function($resolvedArgs, $exprArgs) {

			return "\CrazyCake\Helpers\Images::resizedImagePath($resolvedArgs)";
		});
	}
}
