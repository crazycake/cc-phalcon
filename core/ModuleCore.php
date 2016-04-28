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
}
