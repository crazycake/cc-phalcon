<?php
/**
 * CLI Task Controller: provides common functions for CLI tasks.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

//phalcon imports
use Phalcon\CLI\Task;
use Phalcon\Exception;
//core
use CrazyCake\Phalcon\AppModule;
use CrazyCake\Services\Guzzle;

/**
 * Common functions for CLI tasks
 */
class TaskCore extends Task
{
    /* traits */
    use Core;
    use Guzzle;

    /**
     * Main Action Executer
     * @return void
     */
    public function mainAction()
    {
        $this->_colorize($this->config->app->name." CLI App", "NOTE");
        $this->_colorize("Usage: \ncli.php main [param]", "OK");
        $this->_colorize("--------------------", "NOTE");
        $this->_colorize("appConfig: Outputs app configuration in JSON format", "WARNING");
        $this->_colorize("getCache [key]: Gets stored data in Cache (Redis)", "WARNING");
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

     /**
     * Outputs app configuration in JSON format
     * @param array $args - The args array, the 1st arg is the filter config property
     */
    public function appConfigAction($args = array())
    {
        $conf = $this->config;

        //protect env vars
        if(isset($conf->database))
            unset($conf->database);

        if(empty($args))
            $this->_output($conf, true);

        if(!isset($conf->{$args[0]}))
            $this->_colorize("No value found for argument.", "ERROR", true);

        $this->_output($conf->{$args[0]}, true);
    }

    /**
     * Gets cached data set by Cacher library.
     * @param array $args - The input params
     */
    public function getCacheAction($args = array())
    {
        if(empty($args))
            $this->_colorize("Empty key argument", "ERROR", true);

        try {

            //catcher adapter
            $redis = new \CrazyCake\Services\Redis();
            //get data from cache json-undecoded
            $data = $redis->get($args[0], false);

            //outputs value
            $this->_output($data);
        }
        catch (Exception $e) {
            //outputs error
            die("CLI TaskCore -> Error retrieving cached data for: ".$args[0].", err: ".$e->getMessage());
        }
    }

    /**
     * Generates revision assets names inside public assets module folder
     * @param array $args - The input params
     */
    public function revAssetsAction($args = array())
    {
        if(empty($args) || !in_array($args[0], ["frontend", "backend"]))
            $this->_colorize("Invalid module argument", "ERROR", true);

        $module_name = $args[0];

        //set paths
        $assets_path = WebCore::ASSETS_MIN_FOLDER_PATH;
        $assets_path = PROJECT_PATH.$module_name."/public/".$assets_path."/";

        if(!is_dir($assets_path))
            $this->_colorize("Assets path not found: $assets_path", "ERROR", true);

        $version = AppModule::getProperty("version", $module_name);

        if(!$version)
            $this->_colorize("Invalid version for $module_name", "ERROR", true);

        $version_stripped = str_replace(".", "", $version);

        //CSS
        copy($assets_path."app.min.css", $assets_path."app-".$version_stripped.".rev.css");
        //JS
        copy($assets_path."app.min.js", $assets_path."app-".$version_stripped.".rev.js");

        //print output
        $this->_colorize("Created revision assets: $version", "OK", true);
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Print Output
     * @param string $output - The text message
     * @param boolean $json_encode - Sends json encoded output
     */
    protected function _output($output = "OK", $json_encode = false)
    {
        if($json_encode)
            $output = json_encode($output, JSON_UNESCAPED_SLASHES);

        die($output.PHP_EOL);
    }

    /**
     * Print Output with Colors
     * @param string $text - The text message
     * @param string $type - Options: ["OK", "ERROR", "WARNING", "NOTE"]
     * @param boolean $die - Flag to stop script execution
     */
    protected function _colorize($text = "", $type = "OK", $die = false)
    {
        $open  = "";
        $close = "\033[0m";

        switch ($type) {
            case "OK":
                $open = "\033[92m";  //Green color
                break;
            case "ERROR":
                $open = "\033[91m";  //Red color
                break;
            case "WARNING":
                $open = "\033[93m";  //Yellow color
                break;
            case "NOTE":
                $open = "\033[94m";  //Blue color
                break;
            default:
                throw new Exception("CoreTask:_colorize -> invalid message type: ".$type);
        }
        //return output, chr(27) -> escape key
        $output = chr(27) . $open . $text . chr(27) . $close . "\n";

        //echo output
        if($die)
            $this->_output($output);
        else
            echo $output;
    }

    /**
     * Validates module folder argument
     * @param array $args - The args array
     * @param int $index - The arg index to validate
     * @param boolean $check_folder - Checks if module folder exists
     */
    protected function _validatesModuleArg($args = array(), $index = 0, $check_folder = true)
    {
        if(empty($args) || !isset($args[$index]))
            $this->_colorize("The argument [module] is missing", "ERROR", true);

        //set module
        $module = PROJECT_PATH.$args[$index];

        //check for folder
        if($check_folder && !is_dir($module))
            $this->_colorize("The input module folder ($module) was not found", "ERROR", true);

        return $module;
    }
}
