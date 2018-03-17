<?php
/**
 * App Loader Trait. Contains classes loader logic & environment setup
 * Env vars: APP_ENV, APP_PORT, APP_TZ, APP_HTTPS_ONLY
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Phalcon;

/**
 * App Loader Interface
 */
trait AppLoader
{
	/**
	 * Core namespace
	 * @static
	 * @var string
	 */
	private static $CORE_NAMESPACE = "CrazyCake\\";

	/**
	 * Core project name
	 * @static
	 * @var string
	 */
	private static $CORE_PROJECT = "cc-phalcon";

	/**
	 * App Core default libs
	 * @static
	 * @var array
	 */
	protected static $CORE_DEFAULT_LIBS = ["services", "controllers", "core", "helpers", "models", "account"];

	/**
	 * Get Module Model Class Name
	 * A prefix can be set in module options
	 * @static
	 * @param string $key - The class module name uncamelize, example: "some_class"
	 * @param boolean $prefix - Append prefix (double slash)
	 * @return string
	 */
	public static function getClass($key = "", $prefix = true)
	{
		//check for prefix in module settings
		$class_name = \Phalcon\Text::camelize($key);

		return $prefix ? "\\$class_name" : $class_name;
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Set Module Environment properties
	 */
	private function setEnvironment()
	{
		//default timezone
		date_default_timezone_set(getenv("APP_TZ") ?: "America/Santiago");

		//get env-vars
		$env = getenv("APP_ENV") ?: "local"; //default to 'local'

		//display errors?
		ini_set("display_errors", (int)($env != "production"));
		error_reporting(E_ALL);

		$base_url = null;

		// set BASE_URL for non CLI, CGI apps
		if (php_sapi_name() != "cli") {

			// set default host
			if (!isset($_SERVER["HTTP_HOST"]))
				$_SERVER["HTTP_HOST"] = "localhost";

			// set scheme and host
			$scheme   = $_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "http"; //aws elb headers
			$host     = $_SERVER["HTTP_HOST"].preg_replace("@/+$@", "", dirname($_SERVER["SCRIPT_NAME"]));
			$base_url = "$scheme://$host";

			//set port?
			$port = getenv("APP_PORT") ?: "";

			if(!empty($port))
				$base_url = str_replace(":$port", "", $base_url).":$port";

			// add missing slash?
			if (substr($base_url, -1) != "/")
				$base_url .= "/";

			// remove default port 80 if set
			$base_url = str_replace(":80/", "/", $base_url);
		}

		//set environment consts & self vars
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
	}

	/**
	 * Load classes
	 * @param array $config - The config array
	 */
	private function loadClasses($config = [])
	{
		// 1. project dirs
		$dirs = [
			"cli"         => APP_PATH."cli/",
			"controllers" => APP_PATH."controllers/",
			"models"      => APP_PATH."models/"
		];

		foreach ($config["loader"] as $dir) {

			$paths = explode("/", $dir, 2);
			//set directory path
			$dirs[$dir] = count($paths) > 1 ? PROJECT_PATH.$paths[0]."/".$paths[1]."/" : APP_PATH.$dir."/";
		}

		//inverted sort
		arsort($dirs);

		// 2. Load app directories (components)
		$loader = new \Phalcon\Loader();
		$loader->registerDirs($dirs);

		// 3. Register core static modules
		$this->loadCoreLibraries($loader, $config["core"]);

		//4.- Register phalcon loader
		$loader->register();
		//s(get_included_files());
	}

	/**
	 * Loads static libraries.
	 * Use Phar::running() to get path of current phar running
	 * Use get_included_files() to see all loaded classes
	 * @param object $loader - Phalcon loader object
	 * @param array $libraries - Libraries required
	 */
	private function loadCoreLibraries($loader, $libraries = [])
	{
		if (!is_array($libraries))
			$libraries = [];

		//merge libraries with defaults
		$libraries = array_merge(self::$CORE_DEFAULT_LIBS, $libraries);

		//check if lib is runnning in phar file
		$class_path = \Phar::running() ?: dirname(__DIR__);

		//set library path => namespaces
		$namespaces = [];
		foreach ($libraries as $lib)
			$namespaces[self::$CORE_NAMESPACE.ucfirst($lib)] = "$class_path/$lib/";

		//register namespaces
		$loader->registerNamespaces($namespaces);
		//var_dump($namespaces);exit;
	}
}
