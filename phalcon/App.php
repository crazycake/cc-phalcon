<?php
/**
 * Phalcon APP
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Phalcon;

require "AppServices.php";

/**
 * Phalcon APP
 */
abstract class App
{
	/**
	 * Project Path
	 * @var String
	 */
	const PROJECT_PATH = "/var/www/";

	/**
	 * Core namespace
	 * @var String
	 */
	const CORE_NAMESPACE = "CrazyCake\\";

	/**
	 * App Core default libs
	 * @var Array
	 */
	const CORE_LIBS = ["services", "controllers", "core", "helpers", "models", "account"];

	/**
	 * Config function
	 * @var Array
	 */
	abstract protected function config();

	/**
	 * The App Dependency injector
	 * @var Object
	 */
	private $di;

	/**
	 * Constructor
	 * @param String $mod_name - The input module
	 */
	public function __construct($mod_name = "frontend")
	{
		// define APP contants
		define("MODULE_NAME", strtolower($mod_name));
		define("PROJECT_PATH", self::PROJECT_PATH);
		define("STORAGE_PATH", PROJECT_PATH."storage/");
		define("COMPOSER_PATH", PROJECT_PATH."vendor/");
		define("CORE_PATH", PROJECT_PATH."core/");
		define("PUBLIC_PATH", PROJECT_PATH."public/");
		define("APP_PATH", PROJECT_PATH."app/");
		define("APP_TS", microtime(true)); // for debugging render time

		// composer libraries (no config required)
		$this->loadComposer();

		// environment (loader)
		$this->setEnvironment();

		// set app configurations
		$config = $this->config();

		// set app version
		$config["version"] = is_file(PROJECT_PATH."version") ? trim(file_get_contents(PROJECT_PATH."version")) : "1";

		// load sentry
		if (APP_ENV != "local" && !empty($config["sentry"]) && class_exists('\Sentry\SentrySdk'))
			\Sentry\init(["dsn" => $config["sentry"], "release" => $config["version"], "environment" => APP_ENV]);

		// app classes (loader)
		$this->loadClasses($config);

		// set DI (services)
		$this->di = (new AppServices($config))->getDI();
	}

	/**
	 * Start app module execution
	 * @param Array $argv - Input arguments for CLI
	 */
	public function start($argv = null)
	{
		// set routes function
		$routes_fn = is_file(APP_PATH."config/routes.php") ? include APP_PATH."config/routes.php" : null;

		switch (MODULE_NAME) {

			case "cli": $this->newCli($argv);      break;
			case "api": $this->newApi($routes_fn); break;
			default   : $this->newMvc($routes_fn); break;
		}
	}

	/**
	 * Handle Exception
	 * @param Exception $e - The exception
	 */
	public static function handleException($e)
	{
		$di = \Phalcon\DI::getDefault();

		if ($di && $di->has("stdout")) $di->getShared("stdout")->error($e->getMessage());

		if ($di && $di->has("logger")) $di->getShared("logger")->error($e->getMessage());

		if (APP_ENV != "local" && class_exists('\Sentry\SentrySdk')) \Sentry\captureException($e);

		if (APP_ENV != "production") die($e->getMessage());
	}

	/**
	 * Get Module Model Class Name
	 * @param String $key - The class module name uncamelize, example: "some_class"
	 * @param Boolean $prefix - Append prefix (double slash)
	 * @return String
	 */
	public static function getClass($key = "", $prefix = true)
	{
		// camelized class name
		$name = \Phalcon\Text::camelize(\Phalcon\Text::uncamelize($key));

		return $prefix ? "\\$name" : $name;
	}

	/**
	 * Set Module Environment properties
	 */
	private function setEnvironment()
	{
		// default timezone
		date_default_timezone_set(getenv("APP_TZ") ?: "America/Santiago");

		// error handler
		error_reporting(E_ALL);

		// convert warnings/notices to exceptions
		set_error_handler(function ($errno, $errstr, $errfile, $errline) {

			self::handleException(new \Exception("App Exception: '$errstr' $errfile [$errline]", $errno));
		});

		// fatal errors
		register_shutdown_function(function() {

			$e = (object)error_get_last();

			if (!empty($e->message)) self::handleException(new \Exception("Fatal Exception: '$e->message' $e->file [$e->line]", E_CORE_ERROR));
		});

		// get env-vars
		$env      = getenv("APP_ENV") ?: "local";
		$base_url = "http://localhost/";

		// set BASE_URL for non CLI, CGI apps
		if (php_sapi_name() != "cli") {

			// set default host
			if (!isset($_SERVER["HTTP_HOST"])) $_SERVER["HTTP_HOST"] = "localhost";

			// set scheme and host
			$scheme   = $_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "http"; //aws elb headers
			$host     = $_SERVER["HTTP_HOST"].preg_replace("@/+$@", "", dirname($_SERVER["SCRIPT_NAME"]));
			$base_url = "$scheme://$host";

			// add missing slash?
			if (substr($base_url, -1) != "/") $base_url .= "/";

			// remove default port 80 if set
			$base_url = str_replace(":80/", "/", $base_url);
		}

		// set environment consts & self vars
		define("APP_ENV", $env);
		define("APP_BASE_URL", $base_url);
	}

	/**
	 * Load composer libraries
	 */
	private function loadComposer()
	{
		if (is_file(COMPOSER_PATH."autoload.php"))
			require COMPOSER_PATH."autoload.php";

		//ss(get_declared_classes());exit;
	}

	/**
	 * Load classes
	 * @param Array $config - The config array
	 */
	private function loadClasses($config = [])
	{
		// 1. project dirs
		$dirs = [
			"cli"         => APP_PATH."cli/",
			"controllers" => APP_PATH."controllers/",
			"models"      => APP_PATH."models/"
		];

		$loader = $config["loader"] ?? [];

		foreach ($loader as $dir) {

			$paths      = explode("/", $dir, 2);
			$dirs[$dir] = count($paths) > 1 ? PROJECT_PATH.$paths[0]."/".$paths[1]."/" : APP_PATH.$dir."/";
		}

		// inverted sort
		arsort($dirs);

		// 2. Load app directories (components)
		$loader = new \Phalcon\Loader();
		$loader->registerDirs($dirs);

		// 3. Register core static modules
		$this->loadCoreLibraries($loader);

		//4.- Register phalcon loader
		$loader->register();
	}

	/**
	 * Loads static libraries.
	 * Use Phar::running() to get path of current phar running
	 * Use get_included_files() to see all loaded classes
	 * @param Object $loader - Phalcon loader object
	 */
	private function loadCoreLibraries(&$loader)
	{
		// check if lib is runnning in phar file
		$path = \Phar::running() ?: dirname(__DIR__);

		// set library path => namespaces
		$namespaces = [];

		foreach (self::CORE_LIBS as $lib)
			$namespaces[self::CORE_NAMESPACE.ucfirst($lib)] = "$path/$lib/";

		// register namespaces
		$loader->registerNamespaces($namespaces);
		//ss($namespaces);
	}

	/**
	 * Starts an CLI App
	 * @param Array $argv - Input arguments for CLI
	 */
	private function newCli($argv = null)
	{
		// new cli app
		$app = new \Phalcon\CLI\Console($this->di);

		if (is_null($argv)) die("Phalcon Console -> no args supplied\n");

		$arguments = ["params" => []];

		// set args data
		foreach ($argv as $k => $arg) {

			switch ($k) {

				case 0 : break;
				case 1 : $arguments["task"]       = $arg; break;
				case 2 : $arguments["action"]     = $arg; break;
				default: $arguments["params"][$k] = $arg; break;
			}
		}

		// order params
		if (count($arguments["params"]) > 0) {

			$params = array_values($arguments["params"]);
			$arguments["params"] = $params;
		}

		// define global constants for the current task and action
		define("CLI_TASK",   $argv[1] ?? null);
		define("CLI_ACTION", $argv[2] ?? null);

		// handle incoming arguments
		$app->handle($arguments);
	}

	/**
	 * Starts an API App
	 * @param Function $routes_fn - A routes function
	 */
	private function newApi($routes_fn = null)
	{
		// new micro app
		$app = new \Phalcon\Mvc\Micro($this->di);

		// apply a routes function if param given (must be done before object instance)
		if (is_callable($routes_fn))
			$routes_fn($app);

		// handle the request
		echo $app->handle();
	}

	/**
	 * Starts an MVC App
	 * @param Function $routes_fn - A routes function
	 */
	private function newMvc($routes_fn = null)
	{
		// call routes function?
		if (is_callable($routes_fn)) {

			$router = new \Phalcon\Mvc\Router();
			// remove trailing slashes automatically
			$router->removeExtraSlashes(true);
			// apply a routes function
			$routes_fn($router);

			$this->di->set("router", $router);
		}

		$app = new \Phalcon\Mvc\Application($this->di);

		// set output
		$output = $app->handle()->getContent();

		// handle the request
		if (APP_ENV != "local")
			ob_start([$this,"minifyOutput"]);

		echo $output;
	}

	/**
	* Minifies HTML output
	* @param String $buffer - The input buffer
	*/
	private function minifyOutput($buffer)
	{
		$search  = ["/\>[^\S ]+/s", "/[^\S ]+\</s", "/(\s)+/s"];
		$replace = [">","<","\\1"];

		if (preg_match("/\<html/i",$buffer) == 1 && preg_match("/\<\/html\>/i",$buffer) == 1)
			$buffer = preg_replace($search, $replace, $buffer);

		return $buffer;
	}
}
