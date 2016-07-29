<?php
/**
 * QRMaker: generates readable custom QR images
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 * @link http://phpqrcode.sourceforge.net/
 * @package \CrazyCake\QR\
 */

namespace CrazyCake\Qr;

//imports
use Phalcon\Exception;
//core
use CrazyCake\Phalcon\App;

/**
 * QR Maker Manager
 */
class QRMaker
{
	/* consts */
	const QR_LIB_NAMESPACE = "\\CrazyCake\\Qr\\"; //lib root path
	const QR_HIGH_QUALITY  = true;
	const QR_PNG_MAX_SIZE  = 1024;

	/**
	 * constructor
	 * @param string $log_path - Log directory path, required
	 * @param string $cache_path - Cache directory path, required
	 */
	function __construct($log_path, $cache_path = null)
	{
		if (empty($log_path)) {
			throw new Exception("QRMaker Library -> Log path parameters are required.");
		}
		else if (!is_dir($log_path)){
			throw new Exception("QRMaker Library -> Log path (".$log_path.") not found.");
		}
		else if (!is_dir($cache_path)) {
			throw new Exception("QRMaker Library -> Cache path (".$cache_path.") not found.");
		}

        $this->init($log_path, $cache_path);

		//load QR library
		require_once "src/qrconf.php";
	}

    /**
     * Init and define consts
	 * @param string $log_path - The app log path
     * @param string $cache_path - The app cache path
     */
    protected function init($log_path, $cache_path)
    {
        if (defined("QR_ASSETS_PATH"))
            return;

        //use cache - more disk reads but less CPU power, masks and format templates are stored there
		define("QR_CACHEABLE", (is_null($cache_path) ? false : true));
		define("QR_CACHE_DIR", $cache_path."qr/");
		define("QR_LOG_DIR", $log_path);

		//create cache dir if not exists
		if (!is_dir(QR_CACHE_DIR))
			mkdir(QR_CACHE_DIR, 0775, true);

		//Check if library is running from a Phar file, if does, assets must be copied to cache folder.
		//For reading assets from a phar directly, see: http://php.net/manual/en/phar.webphar.php
		if (\Phar::running()) {
			define("QR_ASSETS_PATH", App::extractAssetsFromPhar("qr/assets/", $cache_path));
		}
		else {
			define("QR_ASSETS_PATH", __DIR__."/assets/");
		}

		//if true, estimates best mask (spec. default, but extremally slow; set to false to significant performance boost but (propably) worst quality code
		if (self::QR_HIGH_QUALITY) {
			define("QR_FIND_BEST_MASK", true);
		}
		else {
			define("QR_FIND_BEST_MASK", false);
			define("QR_DEFAULT_MASK", false);
		}

		//if false, checks all masks available, otherwise value tells count of masks need to be checked, mask id are got randomly
		define("QR_FIND_FROM_RANDOM", false);
		//maximum allowed png image width (in pixels), tune to make sure GD and PHP can handle such big images
		define("QR_PNG_MAXIMUM_SIZE",  self::QR_PNG_MAX_SIZE);
    }

	/**
	 * Generates a QR code
	 * @access private
	 * @param  arrays $params
	 * @return void
	 */
	public function generate($params = [])
	{
		//new QrTag object
		$qr = new QrTag();
		//props
		$qr->bgColor = isset($params["background_color"]) ? $params["background_color"] : "ffffff";
		$qr->text 	 = isset($params["data"]) ? $params["data"] : "CrazyCake QR Code";
		$qr->file 	 = isset($params["savename"]) ? $params["savename"] : die("QR Library -> (generate) must set param savename");

		//shape dot object
		if (isset($params["dot_shape_class"]) && $this->_class_exists($params["dot_shape_class"])) {

			$class     = self::QR_LIB_NAMESPACE.$params["dot_shape_class"];
			$dot_shape = new $class();
		}
		else {
			 //fallback
			$dot_shape = new QrTagDotSquare();
		}

		//set shape dot
		$dot_shape->color = isset($params["dot_shape_color"]) ? $params["dot_shape_color"] : "000000";
		$dot_shape->size  = isset($params["dot_shape_size"]) ? $params["dot_shape_size"] : 14;

		$qr->setDot($dot_shape);

		//frame dot object
		if (isset($params["dot_frame_class"]) && $this->_class_exists($params["dot_frame_class"])) {

			$class     = self::QR_LIB_NAMESPACE.$params["dot_frame_class"];
			$dot_frame = new $class();
		}
		else {
			 //fallback
			$dot_frame = new QrTagFrameDotSquare();
		}

		//set frame dot
		$dot_frame->color = isset($params["dot_frame_color"]) ? $params["dot_frame_color"] : "000000";
		$qr->frameDot     = $dot_frame;

		//main frame object
		if (isset($params["frame_class"]) && $this->_class_exists($params["frame_class"])) {

			$class = self::QR_LIB_NAMESPACE.$params["frame_class"];
			$frame = new $class();
		}
		else {
			//fallback
			$frame = new QrTagFrameSquare();
		}

		$dot_frame->color = isset($params["frame_color"]) ? $params["frame_color"] : "000000";
		$qr->frame = $frame;
	   //var_dump($qr);//exit;

		//generate!
		$qr->generate();

		//embed image?
		if ( isset($params["embed_logo"]) )
			$this->_embedLogo($params["savename"], $params["embed_logo"]);

		return;
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Embed Logo in QR Image
	 * @access private
	 * @param string $qr_path - The QR image path
	 * @param string $embed_img_path - An image to be embedded in the input QR image
	 * @param int $embed_img_width - The embed image width (optional)
	 * @param int $embed_img_height - The embed image height (optional)
	 * @return string
	 */
	private function _embedLogo($qr_path, $embed_img_path, $embed_img_width = 90, $embed_img_height = 90)
	{
		$extension = strtolower(pathinfo($embed_img_path, PATHINFO_EXTENSION));

		//embed image type
		switch ($extension) {
			case "png":
				$embed_img = imagecreatefrompng($embed_img_path);
				break;
			case "jpg":
				$embed_img = imagecreatefromjpeg($embed_img_path);
				break;
			case "gif":
				$embed_img = imagecreatefromgif ($embed_img_path);
				break;
		}

		$real_embed_img_width  = imagesx($embed_img);
		$real_embed_img_height = imagesy($embed_img);

		//qr image
		$qr_img    = imagecreatefrompng($qr_path);
		$qr_width  = imagesx($qr_img);
		$qr_height = imagesy($qr_img);

		//image merging
		$new_embed_img = imagecreatetruecolor($embed_img_width, $embed_img_height);
		imagecopy($new_embed_img, $qr_img, 0, 0, $qr_width/2 - $embed_img_width/2, $qr_height/2 - $embed_img_height/2, $embed_img_width, $embed_img_height);
		imagecopyresized($new_embed_img, $embed_img, 0, 0, 0, 0, $embed_img_width, $embed_img_height, $real_embed_img_width, $real_embed_img_height);
		imagecopymerge($qr_img, $new_embed_img, $qr_width/2 - $embed_img_width/2, $qr_height/2 - $embed_img_height/2, 0, 0, $embed_img_width, $embed_img_height, 100);
		imagepng($qr_img, $qr_path);
	}

	/**
	 * Class Exists in Namespace
	 * @access private
	 * @param  string $class_name - A class name
	 * @return string
	 */
	private function _class_exists($class_name)
	{
		return class_exists(self::QR_LIB_NAMESPACE.$class_name);
	}
}
