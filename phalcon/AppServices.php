<?php
/**
 * Phalcon App Services files (Dependency Injector)
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
	 * @param Object $config - The app config
	 */
	public function __construct($config)
	{
		$this->config = $config;
	}

	/**
	 * Get the DI
	 * @return Object - The Dependency Injector
	 */
	public function getDI()
	{
		// get a new Factory DI
		$di = MODULE_NAME == "cli" ? new \Phalcon\DI\FactoryDefault\CLI() : new \Phalcon\DI\FactoryDefault();

		$this->_setMainServices($di);
		$this->_setMongoService($di);
		$this->_setTranslationServices($di);
		$this->_setViewServices($di);

		if (MODULE_NAME == "frontend") {

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
		$config = $this->config;

		// set the config
		$di->setShared("config", $config);

		// the URL component is used to generate all kind of urls in the application
		$di->setShared("url", function() use ($config) {

			$url = new \Phalcon\Url();
			// base URL
			$url->setBaseUri(APP_BASE_URL);

			// set static url
			$url->setStaticBaseUri($config->staticUrl ?? APP_BASE_URL);

			return $url;
		});


		// global logger adapter
		$di->setShared("logger", function() {

			// CLI
			if (MODULE_NAME == "cli" ) {

				$main = new \Phalcon\Logger\Adapter\Stream("php://stdout");

				$main->setFormatter(new \Phalcon\Logger\Formatter\Line("[%date%][%type%][CLI] %message%"));

				$adapters = ["main" => $main];
			}
			// Http
			else {

				$ip = \CrazyCake\Core\HttpCore::getClientIP();

				$main = new \Phalcon\Logger\Adapter\Stream(STORAGE_PATH."logs/".date("d-m-Y").".log");

				$main->setFormatter(new \Phalcon\Logger\Formatter\Line("[%date%][%type%][CLI] %message%"));

				$stdout = new \Phalcon\Logger\Adapter\Stream("php://stdout");

				$stdout->setFormatter(new \Phalcon\Logger\Formatter\Line("[%date%][%type%][$ip] %message%"));

				$adapters = ["main" => $main, "stdout" => $stdout];
			}


			return new \Phalcon\Logger("messages", $adapters);
		});

		// basic http security
		$di->setShared("security", function() {

			$security = new \Phalcon\Security();
			// set the password hashing factor to X rounds
			$security->setWorkFactor(12);
			return $security;
		});

		// extended encryption (cryptography helper)
		$di->setShared("cryptify", function() use ($config) {

			return new \CrazyCake\Helpers\Cryptify($config->cryptKey ?? $config->namespace);
		});

		// kint options
		if (class_exists("\Kint")) {

			\Kint::$max_depth = 0;
			\Kint::$aliases[] = "ss";
		}
	}

	/**
	 * Set Mongo Service
	 * @param Object $di - The DI object
	 */
	private function _setMongoService(&$di)
	{
		$config = $this->config;

		// check for no-mongo apps
		if (isset($config->mongo) && empty($config->mongo)) return;

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
		$config = $this->config;

		$di->setShared("trans", function() use ($config) {

			$langs = $config->langs;

			$trans = new \CrazyCake\Helpers\GetText([

				"domain"    => "app",
				"supported" => $langs,
				"directory" => APP_PATH."langs/"
			]);

			// default language
			$trans->setLanguage($langs[0]);

			return $trans;
		});
	}

	/**
	 * Set session services
	 * @param Object $di - The DI object
	 */
	private function _setSessionServices(&$di)
	{
		$config = $this->config;

		// check for no-session apps
		if (isset($config->session) && empty($config->session)) return;

		// session adapter
		$di->setShared("session", function() use ($config) {

			$expiration = 3600*($config->sessionExpiration ?? 8); // hours

			$factory = new \Phalcon\Storage\AdapterFactory(new \Phalcon\Storage\SerializerFactory());

			$adapter = new RedisAdapter($factory, [

				"host"       => getenv("REDIS_HOST") ?: "redis",
				"uniqueId"   => $config->namespace,
				"prefix"     => "_PHCR_".strtoupper($config->namespace)."_",
				"lifetime"   => $expiration,
				"persistent" => false,
				"index"      => 1
			]);

			// set cookies params
			$cookie = [

				"lifetime" => $expiration,
				"path"     => "/",
				"secure"   => \CrazyCake\Core\HttpCore::getScheme() == "https",
				"httponly" => true,
				"samesite" => "Lax"
			];

			if (!empty(getenv("APP_COOKIE_DOMAIN")))
				$cookie["domain"] = getenv("APP_COOKIE_DOMAIN");

			session_set_cookie_params($cookie);

			// session instance
			$session = new \Phalcon\Session\Manager();

			$session->setAdapter($adapter);
			$session->setName(getenv("APP_COOKIE_NAME") ?: $config->namespace);
			$session->start();
			//ss($adapter, $session);

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
		// volt service
		$di->setShared("volt", function(\Phalcon\Mvc\ViewBaseInterface $view) {

			// new volt engine
			$volt = new \Phalcon\Mvc\View\Engine\Volt($view, $this);

			// set volt engine options
			$volt->setOptions([
				"path"      => STORAGE_PATH."cache/",
				"separator" => "_",
			]);

			// get compiler
			$compiler = $volt->getCompiler();
			// binds some PHP functions to volt
			self::setVoltCompilerFunctions($compiler);

			return $volt;

		});

		// simple view service
		if (is_dir(PROJECT_PATH."ui/volt/")) {

			// simple view service (used for mailing templates)
			$di->setShared("simpleView", function() {

				//simpleView
				$view = new \Phalcon\Mvc\View\Simple();
				//set directory views
				$view->setViewsDir(PROJECT_PATH."ui/volt/");
				//register volt view engine
				$view->registerEngines([".volt" => "volt"]);

				return $view;
			});
		}

		// view service only for frontend applications
		if (MODULE_NAME == "frontend") {

			// set view service
			$di->setShared("view", function() {

				$view = new \Phalcon\Mvc\View();
				//set directory views
				$view->setViewsDir(PROJECT_PATH."ui/volt/");
				//register volt view engine
				$view->registerEngines([".volt" => "volt"]);

				return $view;
			});
		}
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

/**
 * Redis Adapter
 * ! Temporary class for Redis Adapter (prefix hardcoded bug)
 */
class RedisAdapter extends \Phalcon\Session\Adapter\Redis
{
	/**
	 * constructor
	 */
	public function __construct($factory, $options)
	{
		$this->adapter = $factory->newInstance("redis", $options);
	}
}
