<?php
/**
 * CLI Task Controller, common functions for CLI tasks.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Core;

use Phalcon\CLI\Task;
use Phalcon\Mvc\View\Engine\Volt\Compiler as VoltCompiler;

use CrazyCake\Phalcon\App;
use CrazyCake\Phalcon\AppServices;
use CrazyCake\Controllers\Requester;

/**
 * Common functions for CLI tasks
 */
class TaskCore extends Task
{
	use Requester;

	/**
	 * Main Action Executer
	 */
	public function mainAction()
	{
		$this->colorize($this->config->name." app CLI | usage: main [task]", "NOTE");
		$this->colorize("appConfig \tOutputs app configuration in JSON format", "INFO");
		$this->colorize("revAssets \tGenerates JS & CSS bundles revision files", "INFO");
		$this->colorize("compileVolt \tCompile volt files into cache folder", "INFO");
		$this->colorize("-- § --", "OK");
	}

	/**
	 * Outputs app configuration in JSON format
	 * @param Array $args - The args array, the 1st arg is the filter config property
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
	 * @param Array $args - The input params
	 */
	public function revAssetsAction($args = [])
	{
		$assets_path = PROJECT_PATH."public/assets/";

		if (!is_dir($assets_path) || !is_file($assets_path."app.js") || !is_file($assets_path."app.css"))
			return $this->colorize("Missing dev assets files (app.js & app.css).", "ERROR", true);

		$current_version = (int)$this->config->version;

		// decimal version
		$this->colorize("Current version: $current_version", "NOTE");

		// clean old files
		$files = scandir($assets_path);

		foreach ($files as $f) {

			if (is_dir($f) || strpos($f, ".rev.") === false)
				continue;

			// keep 1st & 2nd-last versions only, numeric validation
			preg_match_all('/\d+/', $f, $file_ver);
			$file_ver = current($file_ver[0]);

			if (empty($current_version - (int)$file_ver))
				continue;

			$this->colorize("Removing asset $assets_path$f", "NOTE");

			unlink($assets_path.$f);
		}

		$new_version = $current_version + 1;

		// update version
		file_put_contents(PROJECT_PATH."version", $new_version);

		// APP JS
		copy($assets_path."app.js", $assets_path."app-".$new_version.".rev.js");
		// APP CSS
		copy($assets_path."app.css", $assets_path."app-".$new_version.".rev.css");

		// clean
		unlink($assets_path."app.js");
		unlink($assets_path."app.css");

		// output
		$this->colorize("Created revision assets for version: ".$new_version, "OK", true);
	}

	/**
	 * Compiles all volt files
	 * @param Array $args - The args array
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

		$path = PROJECT_PATH."ui/volt/";

		if (!is_dir($path)) return $this->colorize("Missing ./ui/volt folder", "ERROR", true);

		// get volt files
		$files = $this->getDirectoryFiles($path );

		$i = 0;
		foreach ($files as $file) {

			$compiler->compile($file);
			$i++;
		}

		$this->colorize("Compiled $i files.", "OK");
	}

	/**
	 * Print Output and finish script
	 * @param String $output - The text message
	 * @param Boolean $json_encode - Sends json encoded output
	 */
	protected function output($output = "OK", $json_encode = false)
	{
		if ($json_encode)
			$output = \CrazyCake\Helpers\JSON::safeEncode($output, JSON_UNESCAPED_SLASHES);

		die($output.PHP_EOL);
	}

	/**
	 * Print Output with Colors
	 * @param String $text - The text message
	 * @param String $type - Options: ["OK", "ERROR", "WARNING", "NOTE"]
	 * @param Boolean $die - Flag to stop script execution
	 */
	protected function colorize($text = "", $type = "OK", $die = false)
	{
		$open  = "";
		$close = "\033[0m";

		switch ($type) {

			case "ERROR":   $open = "\033[91m"; break; // light red
			case "OK":      $open = "\033[92m"; break; // light green
			case "WARNING": $open = "\033[93m"; break; // light yellow
			case "INFO":    $open = "\033[94m"; break; // light blue
			case "NOTE":    $open = "\033[97m"; break; // white

			default: throw new \Exception("CoreTask:_colorize -> invalid message type: ".$type);
		}

		$output = $open.$text.$close."\n";

		if ($die) $this->output($output);

		echo $output;
	}

	/**
	 * Get all files in folder (recursive)
	 * @param String $dir - The input directories
	 * @param Array $results - The recursive results
	 * @return Array - An array of files
	 */
	protected function getDirectoryFiles($dir, &$results = [])
	{
		$files = scandir($dir);

		foreach($files as $key => $value) {

			$path = realpath($dir."/".$value);

			if ($value == "." || $value == "..")
				continue;

			if (is_dir($path))
				$this->getDirectoryFiles($path, $results);
			else
				$results[] = $path;
		}

		return $results;
	}
}
