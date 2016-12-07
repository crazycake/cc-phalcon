<?php
/**
 * App Module Trait. Contains Module Environment Logic.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Phalcon;

/**
 * App Loader Interface
 */
interface AppLoader
{
    /**
     * Loads app classes
     * @return array
     */
    public function loadClasses();

    /**
     * Sets Dependency Injector
     * @return array
     */
    public function setDI();
}

/**
 * Used by all modules
 */
abstract class AppModule
{
    /**
     * Set App config (required)
     * @return array
     */
    abstract protected function config();

    /**
     * App Core default modules
     * @var array
     */
    protected static $CORE_DEFAULT_MODULES = ["cli", "api", "backend", "frontend"];

    /**
     * Modules Config
     * @var array
     */
    public static $modules_conf;

    /**
     * The App configuration array
     * @var array
     */
    public $app_conf;

    /**
     * Constructor
     * @access public
     * @param string $mod_name - The input module
     */
    public function __construct($mod_name = null)
    {
        //set app configurations
        $config = $this->config();

        //validations
        if (empty($mod_name) || empty($config))
            throw new Exception("AppModule::constructor -> invalid input module, check setup.");

        //define APP contants
        define("PROJECT_PATH", $config["projectPath"]);
        define("MODULE_NAME", strtolower($mod_name));
        define("STORAGE_PATH", PROJECT_PATH."storage/");
        define("COMPOSER_PATH", PROJECT_PATH."vendor/");
        define("CORE_PATH", PROJECT_PATH."core/");
        define("PUBLIC_PATH", PROJECT_PATH."public/");
        define("APP_PATH", PROJECT_PATH."app/");
        define("APP_START", microtime(true));  //for debugging render time

        //set modules config
        self::$modules_conf = $config["modules"];
        //call class loader
        $this->loadClasses();
        //module setup configurations
        $this->_environment();
        //app setup
        $this->_setup($config["app"]);
        //set DI
        $this->setDI();
    }

    /**
     * Gets current Module property value
     * @static
     * @param  string $prop - A input property
     * @param  string $mod_name - The module name
     * @return mixed
     */
    public static function getProperty($prop = "", $mod_name = "")
    {
        $module = empty($mod_name) ? MODULE_NAME : $mod_name;

        if (!isset(self::$modules_conf[$module]) || !isset(self::$modules_conf[$module][$prop]))
            return false;

        return self::$modules_conf[$module][$prop];
    }

    /**
     * Get Module Model Class Name
     * A prefix can be set in module options
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

    /**
     * Get a module URL from current environment
     * @static
     * @param  string $module - The module name
     * @param  string $uri - A uri to be appended
     * @param  string $type - The url path type: "base" or "static"
     * @return string
     */
    public static function getUrl($module = "", $uri = "", $type = "base")
    {
		//set base URL
        $url = "./";
        //get module
        $module = empty($module) ? MODULE_NAME : strtolower($module);

        //get static URL?
        if ($type == "static" && $static_url = self::getProperty("staticUrl", $module))
            $url = $static_url;

        //add missing slash
        if (substr($url, -1) !== "/") $url .= "/";

        return $url.$uri;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Set Module Environment properties
     * @access private
     */
    private function _environment()
    {
        //get env-vars
        $env = getenv("APP_ENV") ?: "local"; //default to LOCAL

        //set APP debug environment
        if ($env == "local") {
            ini_set("display_errors", 1);
            error_reporting(E_ALL);
        }
        else {
            ini_set("display_errors", 0);
            error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
        }

        $base_url = "./";

        //Check for CLI execution & CGI execution
        if (php_sapi_name() !== "cli") {

            if (!isset($_REQUEST))
                throw new Exception("AppModule -> Missing REQUEST data: ".json_encode($_SERVER)." && ".json_encode($_REQUEST));

            //set localhost if host is not set
            if (!isset($_SERVER["HTTP_HOST"]))
                $_SERVER["HTTP_HOST"] = "localhost";

            //fallback for missing env var
            if (empty($app_base_uri)) {
                $base_url = (isset($_SERVER["HTTPS"]) ? "https://" : "http://").
                                   $_SERVER["HTTP_HOST"].preg_replace("@/+$@", "", dirname($_SERVER["SCRIPT_NAME"]))."/";
            }

			//add missing slash
	        if (substr($base_url, -1) !== "/") $base_url .= "/";
        }

        //set environment consts & self vars
        define("APP_ENV", $env);
        define("APP_BASE_URL", $base_url);
        //sd(APP_ENV, APP_BASE_URL);exit;
    }

    /**
     * App configs
     * @param  array $conf - the input configuration
     */
    private function _setup($conf = [])
    {
        //langs
        $conf["langs"] = self::getProperty("langs");

        //finally, set app properties
        $this->app_conf["app"] = $conf;
    }
}
