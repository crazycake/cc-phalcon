<?php
/**
 * App Loader Trait. Contains Environment Loader Logic.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Phalcon;

/**
 * App Loader Interface
 */
trait AppLoader
{
    /** const **/
    private static $CORE_NAMESPACE = "CrazyCake\\";
    private static $CORE_PROJECT   = "cc-phalcon";

    /**
     * App Core default libs
     * @var array
     */
    protected static $CORE_DEFAULT_LIBS = ["services", "controllers", "core", "helpers", "models", "account"];

    /**
     * Get Module Model Class Name
     * A prefix can be set in module options
     * @access public
     * @param string $key - The class module name uncamelize, example: "some_class"
     * @param boolean $prefix - Append prefix (double slash)
     */
    public static function getClass($key = "", $prefix = true)
    {
        //check for prefix in module settings
        $class_name = \Phalcon\Text::camelize($key);

        //api special case (if class not exists append prefix.)
        if (MODULE_NAME == "api" && !class_exists($class_name))
            $class_name = "Ws$class_name";

        return $prefix ? "\\$class_name" : $class_name;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Set Module Environment properties
     * @access private
     */
    private function setEnvironment()
    {
        //get env-vars
        $env = getenv("APP_ENV") ?: "local"; //default to LOCAL

        //set APP debug environment
        ini_set("display_errors", (int)$env == "local");
        error_reporting(E_ALL);

        $base_url = "./";

        // check for CLI execution & CGI execution
        if (php_sapi_name() != "cli") {

            if (!isset($_REQUEST))
                throw new Exception("App::setEnvironment -> Missing REQUEST data: ".json_encode($_SERVER)." && ".json_encode($_REQUEST));

            // set localhost if host is not set
            if (!isset($_SERVER["HTTP_HOST"]))
                $_SERVER["HTTP_HOST"] = "localhost";

            //set base url
            $base_url = (isset($_SERVER["HTTPS"]) ? "https://" : "http://").
                               $_SERVER["HTTP_HOST"].preg_replace("@/+$@", "", dirname($_SERVER["SCRIPT_NAME"]));

            // append APP port
            if(getenv("APP_PORT")) $base_url .= ":".getenv("APP_PORT");

			// add missing slash
	        if (substr($base_url, -1) != "/") $base_url .= "/";
        }

        //set environment consts & self vars
        define("APP_ENV", $env);
        define("APP_BASE_URL", $base_url);
        //sd(APP_ENV, APP_BASE_URL);exit;
    }

    /**
     * Load classes
     * @access private
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
        //print_r($dirs); exit;

        //inverted sort
        arsort($dirs);

        // 2. Load app directories (components)
        $loader = new \Phalcon\Loader();
        $loader->registerDirs($dirs);

        // 3. Register core static modules
        $this->loadCoreLibraries($loader, $config["core"]);

        // 4. Composer libs auto loader
        if (!is_file(COMPOSER_PATH."autoload.php"))
            die("App::loadClasses -> autoload composer file not found: ".COMPOSER_PATH."autoload.php");

        //autoload composer file
        require COMPOSER_PATH."autoload.php";

        //4.- Register phalcon loader
        $loader->register();
        //sd(get_included_files());
    }

    /**
     * Loads static libraries.
     * Use Phar::running() to get path of current phar running
     * Use get_included_files() to see all loaded classes
     * @access private
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
