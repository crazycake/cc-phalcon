<?php
/**
 * CLI Task Controller, provides common functions for CLI tasks.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

//phalcon imports
use Phalcon\CLI\Task;
use Phalcon\Exception;
use Phalcon\Mvc\View\Engine\Volt\Compiler as VoltCompiler;
//core
use CrazyCake\Phalcon\App;
use CrazyCake\Phalcon\AppServices;
use CrazyCake\Controllers\Requester;

/**
 * Common functions for CLI tasks
 */
class TaskCore extends Task
{
	/* traits */
	use Debugger;
	use Requester;

	/**
	 * Main Action Executer
	 * @return void
	 */
	public function mainAction()
	{
		$this->colorize($this->config->name." app CLI", "NOTE");
		$this->colorize("Usage: main [param]", "OK");
		$this->colorize("--------------------", "NOTE");
		$this->colorize("appConfig: Outputs app configuration in JSON format", "WARNING");
		$this->colorize("revAssets: Generates JS & CSS bundles revision files", "WARNING");
		$this->colorize("compileVolt: Compile volt files into cache folder", "WARNING");
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

		if(!is_file($assets_path."app.min.js") || !is_file($assets_path."app.min.css"))
			$this->colorize("Missing minified assets files.", "ERROR", true);

		//decimal version
		$ver = str_replace(".", "", $this->config->version);
		$this->colorize("Ok, version $ver", "NOTE");

		//clean old files
		$files = scandir($assets_path);

		foreach ($files as $f) {

			if(strpos($f, ".rev.") === false)
				continue;

			//keep 1st & 2nd-last versions only, get ony decimals
			preg_match_all('/\d+/', $f, $file_ver);
			$file_ver = $file_ver[0];

			if((int)$ver - (int)$file_ver[0] <= 1)
				continue;

			$this->colorize("Removing asset $assets_path$f", "NOTE");
			unlink($assets_path.$f);
		}

		//APP JS
		copy($assets_path."app.min.js", $assets_path."app-".$ver.".rev.js");
		//APP CSS
		copy($assets_path."app.min.css", $assets_path."app-".$ver.".rev.css");
		//LAZY CSS
		if(is_file($assets_path."lazy.min.css"))
			copy($assets_path."lazy.min.css", $assets_path."lazy-".$ver.".rev.css");

		//remove min files
		foreach(glob($assets_path."*.min.*") as $f)
			unlink($f);

		//print output
		$this->colorize("Created revision assets for version: ".$this->config->version, "OK", true);
	}

	/**
	 * Compiles all volt files
	 * @param array $args - The args array
	 */
	public function compileVoltAction($args = [])
	{
		$conf = $this->config;

		// new volt compiler
		$compiler = new VoltCompiler();
		$compiler->setOptions([
			"compiledPath"      => STORAGE_PATH."cache/",
			"compiledSeparator" => "_",
		]);
		// extend functions
		AppServices::setVoltCompilerFunctions($compiler);

		// get volt files
		$files = $this->getDirectoryFiles(PROJECT_PATH."ui/volt/");

		$i = 0;
		foreach ($files as $file) {
			$compiler->compile($file);
			$i++;
		}

		$this->colorize("Compiled $i files.", "OK");
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
		//return output
		$output = $open.$text.$close."\n";

		//echo output
		if ($die)
			$this->output($output);
		else
			echo $output;
	}

	/**
	 * Async Request (CLI struct)
	 * @param  array $options - The HTTP options
	 * @return object - The requester object
	 */
	protected function coreRequest($options = [])
	{
		$options = array_merge([
			"base_url" => "",
			"uri"      => "",
			"module"   => "",
			"payload"  => "",
			"method"   => "GET",
			"socket"   => false,
			"encrypt"  => false
		], $options);

		//check base url
		if (empty($options["base_url"]))
			$this->colorize("Base URL is required", "ERROR", true);

		//validate URL
		if (filter_var($options["base_url"], FILTER_VALIDATE_URL) === false)
			$this->colorize("Option 'base_url' is not a valid URL", "ERROR", true);

		//special case for module cross requests
		if ($options["module"] == "api") {

			//set API key header name
			$api_key_header_value = $this->config->apiKey;
			$api_key_header_name  = str_replace("_", "-", WsCore::HEADER_API_KEY);
			$options["headers"]   = [$api_key_header_name => $api_key_header_value];
		}

		//payload
		if (!empty($options["payload"]) && $options["encrypt"])
			$options["payload"] = $this->cryptify->encryptData($options["payload"]);

		//requester
		$this->newRequest($options);
	}

	/**
	 * Get all files in folder (recursive)
	 * @param  string $dir - The input directories
	 * @param  array $results - The recursive results
	 * @return array - An array of files
	 */
	protected function getDirectoryFiles($dir, &$results = [])
	{
		$files = scandir($dir);

		foreach($files as $key => $value) {

			$path = realpath($dir."/".$value);

			if($value == "." || $value == "..")
				continue;

			if(is_dir($path))
				$this->getDirectoryFiles($path, $results);
			else
				$results[] = $path;
		}

		return $results;
	}
}
