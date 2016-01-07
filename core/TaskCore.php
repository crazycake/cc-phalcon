<?php
/**
 * CLI Task Controller: provides common functions for CLI tasks.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

//imports
use Phalcon\CLI\Task;
use Phalcon\Exception;

/**
 * Common functions for CLI tasks
 */
class TaskCore extends Task
{
    /**
     * Main Action Executer
     * @return void
     */
    public function mainAction()
    {
        $this->_colorize($this->config->app->name." CLI App", "NOTE");
        $this->_colorize("Usage: \ncli.php main [param]", "OK");
        $this->_colorize("Valid params:", "WARNING");
        $this->_colorize("appConfig -> Outputs app configuration in JSON format", "WARNING");
        $this->_colorize("getCache [key] -> gets stored data in Cache (Redis)", "WARNING");
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

     /**
     * Outputs app configuration in JSON format
     * @param array $params - The args array, the 1st arg is the filter config property
     */
    public function appConfigAction($params = array())
    {
        if(empty($params))
            echo json_encode($this->config, JSON_UNESCAPED_SLASHES);
        else
            echo json_encode($this->config->{$params[0]}, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Gets cached data set by Cacher library.
     * @param array $params - The input params
     */
    public function getCacheAction($params = array())
    {
        if(empty($params))
            $this->_colorize("Empty key parameter", "ERROR", true);

        try {

            //catcher adapter
            $redis = new \CrazyCake\Services\Redis();
            //get data from cache json-undecoded
            $data = $redis->get($params[0], false);

            //outputs value
            echo $data;
        }
        catch (Exception $e) {
            //outputs error
            die("CLI TaskCore -> Error retrieving cached data for: ".$params[0].", err: ".$e->getMessage());
        }
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

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
            die($output);
        else
            echo $output;
    }

    /**
     * Validates module folder argument
     * @param array $params - The args array
     * @param int $index - The arg index to validate
     * @param boolean $check_folder - Checks if module folder exists
     */
    protected function _validatesModuleArg($params = array(), $index = 0, $check_folder = true)
    {
        if(empty($params) || !isset($params[$index])) {
            $this->_colorize("The argument [module] is missing", "ERROR") . "\n";
            exit;
        }

        //set module
        $module = PROJECT_PATH.$params[$index];

        //check for folder
        if($check_folder && !is_dir($module)) {
            $this->_colorize("The input module folder ($module) was not found", "ERROR") . "\n";
            exit;
        }

        return $module;
    }
}
