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

        //modules config
        self::$modules_conf = $config["modules"];

        //define APP contants
        define("PROJECT_PATH", $config["projectPath"]);
        define("MODULE_NAME", strtolower($mod_name));
        define("MODULE_PATH", PROJECT_PATH.MODULE_NAME."/");
        define("STORAGE_PATH", PROJECT_PATH."storage/");
        define("COMPOSER_PATH", PROJECT_PATH."vendor/");
        define("CORE_PATH", PROJECT_PATH."core/");
        define("PUBLIC_PATH", MODULE_PATH."public/");
        define("APP_PATH", MODULE_PATH."app/");
        define("APP_START", microtime(true));  //for debugging render time

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

        //api special case
        if (MODULE_NAME == "api" && !class_exists($class_name)) {

            //if class not exists append prefix.
            $class_name = "Ws$class_name";
        }

        return $prefix ? "\\$class_name" : $class_name;
    }

    /**
     * Get a module URL from current environment
     * For production use defined URIS, for dev local folders path
     * and for staging or testing URI replacement
     * @static
     * @param  string $module - The module name
     * @param  string $uri - A uri to be appended
     * @param  string $type - The url path type: "base" or "static"
     * @return string
     */
    public static function getUrl($module = "", $uri = "", $type = "base")
    {
        //get module
        $module = empty($module) ? MODULE_NAME : strtolower($module);
        //get base uri
        $app_base_uri = getenv("APP_URI_".strtoupper($module));
        //set base URL
        $base_url = empty($app_base_uri) ? null : (isset($_SERVER["HTTPS"]) ? "https://" : "http://").$app_base_uri;

        //environments
        switch (APP_ENVIRONMENT) {

            case "staging":
            case "production":

                //check if static url is set
                $static_url = self::getProperty("staticUrl", $module);

                if (empty($base_url))
                    $base_url = self::getProperty("baseUrl", $module);

                //set URL
                $base_url = ($type == "static" && $static_url) ? $static_url : $base_url;
                break;

            case "local":

                if (empty($base_url))
                    $base_url = str_replace(["/api/", "/frontend/", "/backend/"], "/$module/", APP_BASE_URL);

                break;

            default:

                if (empty($base_url))
                    $base_url = str_replace([".api.", ".frontend.", ".backend."], ".$module.", APP_BASE_URL);

                break;
        }

        //add missing slash
        if (substr($base_url, -1) !== "/") $base_url .= "/";

        return $base_url.$uri;
    }

    /**
     * Super helper to get quick upload path
     * @param string $module_name - The module name
     * @param string $entity - The object entity
     * @param int $object_id - The object ID
     * @return string
     */
    public static function getUploadUrl($module_name = null, $entity = "", $object_id = 0)
    {
        if(empty($module_name))
            $module_name = MODULE_NAME;

        if(!empty($entity))
            $entity .= "/";

        $di = \Phalcon\Di::getDefault();

        //get upload path
        $id_hashed = empty($object_id) ? "" : $di->getShared("cryptify")->encryptHashId($object_id)."/";
        //get URL
        $url = AppModule::getUrl($module_name, "uploads/$entity$id_hashed", "static");

        return $url;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Set Module Environment properties
     * @access private
     */
    private function _environment()
    {
        //load .env file configuration with Dotenv
        $envfile = PROJECT_PATH.".env";
        $dotenv  = new \Dotenv\Dotenv(PROJECT_PATH);

        if (is_file($envfile))
            $dotenv->load();

        //get env-vars
        $debug        = getenv("APP_DEBUG");
        $environment  = getenv("APP_ENV");
        $app_base_uri = getenv("APP_URI_".strtoupper(MODULE_NAME));

        //set APP debug environment
        if ($debug) {
            ini_set("display_errors", 1);
            error_reporting(E_ALL);
        }
        else {
            ini_set("display_errors", 0);
            error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
        }

        //set base URL (project path for CGI & CLI)
        $base_url = empty($app_base_uri) ? PROJECT_PATH : (isset($_SERVER["HTTPS"]) ? "https://" : "http://").$app_base_uri;

        //Check for CLI execution & CGI execution
        if (php_sapi_name() !== "cli") {

            if (!isset($_REQUEST))
                throw new Exception("AppModule -> Missing REQUEST data: ".json_encode($_SERVER)." && ".json_encode($_REQUEST));

            //set localhost if host is not set
            if (!isset($_SERVER["HTTP_HOST"]))
                $_SERVER["HTTP_HOST"] = "127.0.0.1";

            //fallback for missing env var
            if (empty($app_base_uri)) {
                $base_url = (isset($_SERVER["HTTPS"]) ? "https://" : "http://").
                                       $_SERVER["HTTP_HOST"].preg_replace("@/+$@", "", dirname($_SERVER["SCRIPT_NAME"]))."/";
            }
        }

        //add missing slash
        if (substr($base_url, -1) !== "/") $base_url .= "/";

        //set environment consts & self vars
        define("APP_ENVIRONMENT", $environment);
        define("APP_BASE_URL", $base_url);
        //var_dump(APP_ENVIRONMENT, APP_BASE_URL);exit;
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
