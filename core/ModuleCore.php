<?php
/**
 * Module Core Trait
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

/**
 * Used by all modules
 */
trait ModuleCore
{
    /**
     * Gets current Module property value
     * @static
     * @param  string $prop - A input property
     * @param  string $mod_name - The module name
     * @return mixed
     */
    public static function getModuleConfigProp($prop = "", $mod_name = "")
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
    public static function getModuleClass($key = "", $prefix = true)
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
     * Get Module Model Class Name
     * A prefix can be set in module options
     * @param string $key - The class module name uncamelize, example: 'some_class'
     * @param boolean $prefix - Append prefix
     */
    protected function _getModuleClass($key = "", $prefix = true)
    {
        return self::getModuleClass($key, $prefix);
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
    public static function getModuleUrl($module = "", $uri = "", $type = "base")
    {
        if(APP_ENVIRONMENT === "production") {

            //production
            $baseUrl   = self::getModuleConfigProp("baseUrl", $module);
            $staticUrl = self::getModuleConfigProp("staticUrl", $module);

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
}
