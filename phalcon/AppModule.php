<?php
/**
 * App Module Trait
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Phalcon;

/**
 * App Loader Interface
 */
interface AppLoader
{
    /**
     * Loads application
     * @return array
     */
    public function load();
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
    protected static $CORE_DEFAULT_MODULES = ['cli', 'api', 'backend', 'frontend'];

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
        if(empty($mod_name) || empty($config))
            throw new Exception("AppModule::constructor -> invalid input module, check setup.");

        //modules config
        self::$modules_conf = $config["modules"];

        //define APP contants
        define("PROJECT_PATH", $config["projectPath"]);
        define("MODULE_NAME", $mod_name);
        define("MODULE_PATH", PROJECT_PATH.MODULE_NAME."/");
        define("PACKAGES_PATH", PROJECT_PATH."packages/");
        define("COMPOSER_PATH", PACKAGES_PATH."composer/");
        define("PUBLIC_PATH", MODULE_PATH."public/");
        define("APP_PATH", MODULE_PATH."app/");
        define("APP_START", microtime(true));  //for debugging render time

        //this setup configurations
        $this->_setup($config["app"]);

        //call loader
        if(method_exists($this, "load"))
            $this->load();
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

        if(!isset(self::$modules_conf[$module]) || !isset(self::$modules_conf[$module][$prop]))
            return false;

        return self::$modules_conf[$module][$prop];
    }

    /**
     * Get Module Model Class Name
     * A prefix can be set in module options
     * @param string $key - The class module name uncamelize, example: 'some_class'
     * @param boolean $prefix - Append prefix (double slash)
     */
    public static function getClass($key = "", $prefix = true)
    {
        //check for prefix in module settings
        $class_name = \Phalcon\Text::camelize($key);

        //auto prefixes: si la clase no exite, se define un prefijo
        if(!class_exists($class_name)) {

            switch (MODULE_NAME) {
                case 'api':
                    $class_name = "Ws$class_name";
                    break;
                default:
                    break;
            }
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
     * @param  string $type - The url path type: 'base' or 'static'
     * @return string
     */
    public static function getUrl($module = "", $uri = "", $type = "base")
    {
        if(APP_ENVIRONMENT === "production") {

            //production
            $baseUrl   = self::getProperty("baseUrl", $module);
            $staticUrl = self::getProperty("staticUrl", $module);

            if(!$staticUrl)
                $staticUrl = $baseUrl;

            //set URL
            $url = ($type == "static" && $staticUrl) ? $staticUrl : $baseUrl;
        }
        else if(APP_ENVIRONMENT === "local") {

            $url = str_replace(['/api/', '/frontend/', '/backend/'], "/$module/", APP_BASE_URL);
        }
        else {

            $url = str_replace(['.api.', '.frontend.', '.backend.'], ".$module.", APP_BASE_URL);
        }

        return $url.$uri;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    private function _setup($conf = array())
    {
        //langs
        $conf["langs"] = self::getProperty("langs");

        //finally, set app properties
        $this->app_conf["app"] = $conf;
    }
}
