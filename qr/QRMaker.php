<?php
/**
 * QRMaker, generates readable custom QR images. Uses custom fonts in assets folder.
 * @link http://phpqrcode.sourceforge.net/
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Qr;

use Phalcon\Exception;

/**
 * QR Maker Manager
 */
class QRMaker
{
	/**
	 * QR library namespace
	 * @var String
	 */
	const QR_LIB_NAMESPACE = "\\CrazyCake\\Qr\\";

	/**
	 * QR high quality (debug only)
	 * @var Boolean
	 */
	const QR_HIGH_QUALITY  = true;

	/**
	 * QR PNG max size
	 * @var Integer
	 */
	const QR_PNG_MAX_SIZE  = 1024;

	/**
	 * constructor
	 * @param String $log_path - Log directory path, required
	 * @param String $cache_path - Cache directory path, required
	 */
	function __construct($log_path, $cache_path = null)
	{
		if (empty($log_path) || !is_dir($log_path))
			throw new Exception("QRMaker Library -> Log path ($log_path) not found.");

		if (empty($cache_path) || !is_dir($cache_path))
			throw new Exception("QRMaker Library -> Cache path ($cache_path) not found.");

		$this->init($log_path, $cache_path);

		// load QR library
		require_once "src/qrconf.php";
	}

	/**
	 * Init and define consts
	 * @param String $log_path - The app log path
	 * @param String $cache_path - The app cache path
	 */
	protected function init($log_path = "", $cache_path = "")
	{
		if (defined("QR_ASSETS_PATH"))
			return;

		// use cache - more disk reads but less CPU power, masks and format templates are stored there
		define("QR_CACHEABLE", !is_null($cache_path));
		define("QR_CACHE_DIR", $cache_path."qr/");
		define("QR_LOG_DIR", $log_path);

		// create folder?
		if (!is_dir(QR_CACHE_DIR))
			mkdir(QR_CACHE_DIR, 0775, true);

		// check if library is running from a Phar file, if does, assets must be copied to cache folder.
		define("QR_ASSETS_PATH", \Phar::running() ? $this->_extractAssetsFromPhar($cache_path) : __DIR__."/assets/");

		// if true, estimates best mask (spec. default, but extremally slow; set to false to significant performance boost but (propably) worst quality code
		if (self::QR_HIGH_QUALITY) {
			define("QR_FIND_BEST_MASK", true);
		}
		else {
			define("QR_FIND_BEST_MASK", false);
			define("QR_DEFAULT_MASK", false);
		}

		// if false, checks all masks available, otherwise value tells count of masks need to be checked, mask id are got randomly
		define("QR_FIND_FROM_RANDOM", false);
		// maximum allowed png image width (in pixels), tune to make sure GD and PHP can handle such big images
		define("QR_PNG_MAXIMUM_SIZE",  self::QR_PNG_MAX_SIZE);
	}

	/**
	 * Generates a QR code
	 * @param Arrays $params - The input parameters
	 */
	public function generate($params = [])
	{

		$qr = new QrTag();
		$qr->bgColor = $params["background_color"] ?? "ffffff";
		$qr->text    = $params["data"] ?? "CrazyCake QR Code";
		$qr->file    = $params["savename"] ?? die("QR Library -> (generate) must set param savename");

		// shape dot object
		if (!empty($params["dot_shape_class"]) && $this->_classExists($params["dot_shape_class"])) {

			$class     = self::QR_LIB_NAMESPACE.$params["dot_shape_class"];
			$dot_shape = new $class();
		}
		// fallback
		else {

			$dot_shape = new QrTagDotSquare();
		}

		// set shape dot
		$dot_shape->color = $params["dot_shape_color"] ?? "000000";
		$dot_shape->size  = $params["dot_shape_size"] ?? 14;

		$qr->setDot($dot_shape);

		// frame dot object
		if (!empty($params["dot_frame_class"]) && $this->_classExists($params["dot_frame_class"])) {

			$class     = self::QR_LIB_NAMESPACE.$params["dot_frame_class"];
			$dot_frame = new $class();
		}
		// fallback
		else {

			$dot_frame = new QrTagFrameDotSquare();
		}

		// set frame dot
		$dot_frame->color = $params["dot_frame_color"] ?? "000000";
		$qr->frameDot     = $dot_frame;

		// main frame object
		if (!empty($params["frame_class"]) && $this->_classExists($params["frame_class"])) {

			$class = self::QR_LIB_NAMESPACE.$params["frame_class"];
			$frame = new $class();
		}
		// fallback
		else {

			$frame = new QrTagFrameSquare();
		}

		$dot_frame->color = $params["frame_color"] ?? "000000";
		$qr->frame = $frame;

		$qr->generate();

		// embed image?
		if (!empty($params["embed_logo"]))
			$this->_embedLogo($params["savename"], $params["embed_logo"]);

		return;
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Embed Logo in QR Image
	 * @param String $qr_path - The QR image path
	 * @param String $embed_img_path - An image to be embedded in the input QR image
	 * @param Int $embed_img_width - The embed image width (optional)
	 * @param Int $embed_img_height - The embed image height (optional)
	 * @return String
	 */
	private function _embedLogo($qr_path, $embed_img_path, $embed_img_width = 90, $embed_img_height = 90)
	{
		$extension = strtolower(pathinfo($embed_img_path, PATHINFO_EXTENSION));

		// embed image type
		switch ($extension) {
			case "png": $embed_img = imagecreatefrompng($embed_img_path);  break;
			case "jpg": $embed_img = imagecreatefromjpeg($embed_img_path); break;
		}

		$real_embed_img_width  = imagesx($embed_img);
		$real_embed_img_height = imagesy($embed_img);

		// qr image
		$qr_img    = imagecreatefrompng($qr_path);
		$qr_width  = imagesx($qr_img);
		$qr_height = imagesy($qr_img);

		// image merging
		$new_embed_img = imagecreatetruecolor($embed_img_width, $embed_img_height);

		imagecopy($new_embed_img, $qr_img, 0, 0, $qr_width/2 - $embed_img_width/2, $qr_height/2 - $embed_img_height/2, $embed_img_width, $embed_img_height);
		imagecopyresized($new_embed_img, $embed_img, 0, 0, 0, 0, $embed_img_width, $embed_img_height, $real_embed_img_width, $real_embed_img_height);
		imagecopymerge($qr_img, $new_embed_img, $qr_width/2 - $embed_img_width/2, $qr_height/2 - $embed_img_height/2, 0, 0, $embed_img_width, $embed_img_height, 100);
		imagepng($qr_img, $qr_path);
	}

	/**
	 * Class Exists in Namespace
	 * @param String $class_name - A class name
	 * @return String
	 */
	private function _classExists($class_name)
	{
		return class_exists(self::QR_LIB_NAMESPACE.$class_name);
	}

	/**
	 * Extract assets inside the phar file
	 * @param String $cache_path - The app cache path, must end with a slash
	 * @param String $force_extract - Forces extraction not validating contents in given cache path
	 * @return Mixed [boolean|string] - The absolute include cache path
	 */
	private function _extractAssetsFromPhar($cache_path = null, $force_extract = false)
	{
		// folder validation
		if (is_null($cache_path) || !is_dir($cache_path))
			throw new Exception("extractAssetsFromPhar -> assets and cache path must be valid paths.");

		// set phar assets path
		$output_path = $cache_path."qr/assets/";

		// get files in directory & exclude ".", ".." directories
		$files = scandir(__DIR__."/assets");

		unset($files["."], $files[".."]);

		// skip if files were already copied
		if (!$force_extract && is_file($output_path.current($files)))
			return $output_path;

		$assets = [];

		// fill the asset array
		foreach ($files as $file)
			array_push($assets, "qr/assets/".$file);

		// instance a phar file object
		$phar = new \Phar(\Phar::running());

		// extract all files in a given directory
		$phar->extractTo($cache_path, $assets, true);

		return $output_path;
	}
}
