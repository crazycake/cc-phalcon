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
use CrazyCake\Phalcon\App;
use CrazyCake\Controllers\Requester;

/**
 * Common functions for CLI tasks
 */
class TaskCore extends Task
{
    /* traits */
    use Core;
    use Requester;

    /**
     * Main Action Executer
     * @return void
     */
    public function mainAction()
    {
        $this->colorize($this->config->name." CLI App", "NOTE");
        $this->colorize("Usage: \ncli.php main [param]", "OK");
        $this->colorize("--------------------", "NOTE");
        $this->colorize("appConfig: Outputs app configuration in JSON format", "WARNING");
        $this->colorize("revAssets: Generates JS & CSS bundles revision files", "WARNING");
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

     /**
     * Outputs app configuration in JSON format
     * @param array $args - The args array, the 1st arg is the filter config property
     */
    public function appConfigAction($args = [])
    {
        $conf = $this->config;

        if (empty($args))
            $this->output($conf, true);

        if (!isset($conf->{$args[0]}))
            $this->colorize("No value found for argument.", "ERROR", true);

        $this->output($conf->{$args[0]}, true);
    }

    /**
     * Generates revision assets names inside public assets module folder
     * @param array $args - The input params
     */
    public function revAssetsAction($args = [])
    {
        //set paths
        $assets_path = PROJECT_PATH."public/assets/";

        if (!is_dir($assets_path))
            $this->colorize("Assets path not found: $assets_path", "ERROR", true);

        $version_stripped = (int)str_replace(".", "", $this->config->version);

		//clean old files
        $files = scandir($assets_path);

        foreach ($files as $f) {

            if(strpos($f, ".rev.") === false)
                continue;

            //keep last version
			if(strpos($f, "-".($version_stripped - 1)) === false) {
				$this->colorize("Removing asset $assets_path$f", "NOTE");
            	unlink($assets_path.$f);
			}
        }

        //APP CSS
        copy($assets_path."app.min.css", $assets_path."app-".$version_stripped.".rev.css");
        //APP JS
        copy($assets_path."app.min.js", $assets_path."app-".$version_stripped.".rev.js");
		//LAZY CSS
		if(is_file($assets_path."lazy.min.css"))
			copy($assets_path."lazy.min.css", $assets_path."lazy-".$version_stripped.".rev.css");

        //print output
        $this->colorize("Created revision assets: ".$this->config->version, "OK", true);
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Print Output and finish script
     * @param string $output - The text message
     * @param boolean $json_encode - Sends json encoded output
     */
    protected function output($output = "OK", $json_encode = false)
    {
        if ($json_encode)
            $output = json_encode($output, JSON_UNESCAPED_SLASHES);

        die($output.PHP_EOL);
    }

    /**
     * Print Output with Colors
     * @param string $text - The text message
     * @param string $type - Options: ["OK", "ERROR", "WARNING", "NOTE"]
     * @param boolean $die - Flag to stop script execution
     */
    protected function colorize($text = "", $type = "OK", $die = false)
    {
        $open  = "";
        $close = "\033[0m";

        switch ($type) {
            case "OK":
                $open = "\033[92m"; //Green color
                break;
            case "ERROR":
                $open = "\033[91m"; //Red color
                break;
            case "WARNING":
                $open = "\033[35m"; //Magenta color
                break;
            case "NOTE":
                $open = "\033[94m"; //Blue color
                break;
            default:
                throw new Exception("CoreTask:_colorize -> invalid message type: ".$type);
        }
        //return output, chr(27) -> escape key
        $output = chr(27).$open.$text.chr(27).$close."\n";

        //echo output
        if ($die)
            $this->output($output);
        else
            echo $output;
    }

    /**
     * Async Request (CLI struct)
     * @param  array $options - The HTTP options
     */
    protected function asyncRequest($options = [])
    {
        //special case for module cross requests
        if (!empty($options["module"]) && $options["module"] == "api") {

            //set API key header name
            $api_key_header_value = $this->config->apiKey;
            $api_key_header_name  = str_replace("_", "-", WsCore::HEADER_API_KEY);
            $options["headers"]   = [$api_key_header_name => $api_key_header_value];
        }

        //check base url
        if (empty($options["base_url"]))
            $this->colorize("Base URL is required", "ERROR", true);

        //add missing slash
        if (substr($options["base_url"], -1) !== "/")
            $options["base_url"] .= "/";

        //validate URL
        if (filter_var($options["base_url"], FILTER_VALIDATE_URL) === false)
            $this->colorize("Option 'base_url' is not a valid URL", "ERROR", true);

        //log async request
        $this->logger->debug("TaskCore::asyncRequest -> Options: ".json_encode($options, JSON_UNESCAPED_SLASHES));

        //requester
        $this->newRequest($options);
    }
}
