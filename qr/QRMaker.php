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

//lib root path
define("QR_LIB_NAMESPACE",'\\CrazyCake\\Qr\\');
define("QR_SRC_PATH", __DIR__ . "/src/");

//lib files
require_once QR_SRC_PATH . "/qrconf.php";

/**
 * QRMaker Class
 * @author Nicolas Pulido
 */
class QRMaker
{
	/* consts */
	const QR_HIGH_QUALITY = true; 
	const PNG_MAX_SIZE    = 1024;

	/**
	 * constructor
	 * @param string $log_path   Log directory path
	 * @param string $cache_path Cache directory path
	 */
	function __construct($log_path, $cache_path = null)
	{
		if (empty($log_path))
			throw new Exception("QRMaker Library -> App Log path parameters are required.");

		if ( !is_dir($log_path) )
			throw new Exception("QRMaker Library -> App Log path (".$log_path.") not found.");

		if ( !is_null($cache_path) && !is_dir($cache_path) )
			throw new Exception("QRMaker Library -> App Cache path (".$cache_path.") not found.");

		// use cache - more disk reads but less CPU power, masks and format templates are stored there
		if (!defined('QR_CACHEABLE')) define('QR_CACHEABLE', (is_null($cache_path) ? false : true));
		
		// used when QR_CACHEABLE === true
		if (!defined('QR_CACHE_DIR')) define('QR_CACHE_DIR', $cache_path);
		
		// default error logs dir
		if (!defined('QR_LOG_DIR')) define('QR_LOG_DIR', $log_path);
		
		// if true, estimates best mask (spec. default, but extremally slow; set to false to significant performance boost but (propably) worst quality code
		if (self::QR_HIGH_QUALITY) {
			if (!defined('QR_FIND_BEST_MASK')) define('QR_FIND_BEST_MASK', true);
		} 
		else {
			if (!defined('QR_FIND_BEST_MASK')) define('QR_FIND_BEST_MASK', false);
			if (!defined('QR_DEFAULT_MASK')) define('QR_DEFAULT_MASK', false);
		}
		
		// if false, checks all masks available, otherwise value tells count of masks need to be checked, mask id are got randomly
		if (!defined('QR_FIND_FROM_RANDOM')) define('QR_FIND_FROM_RANDOM', false);
		
		// maximum allowed png image width (in pixels), tune to make sure GD and PHP can handle such big images
		if (!defined('QR_PNG_MAXIMUM_SIZE')) define('QR_PNG_MAXIMUM_SIZE',  self::PNG_MAX_SIZE);
	}
	
	/**
	 * Generates a QR code
	 * @access private
	 * @param  arrays $params
	 * @return void
	 */ 
	public function generate($params = array())
	{
		//new QrTag object
		$qr = new QrTag();
		$qr->bgColor = isset($params['background_color']) ? $params['background_color'] : "ffffff";
		$qr->text 	 = isset($params['data']) ? $params['data'] : "CrazyCake QR Code";
		$qr->file 	 = isset($params['savename']) ? $params['savename'] : die("QR Library -> (generate) must set param 'savename'.");
	  
		//shape dot object
		if( isset($params['dot_shape_class']) && $this->_class_exists($params['dot_shape_class']) ) {
			$class    = QR_LIB_NAMESPACE.$params['dot_shape_class'];
			$shapeDot = new $class();
		}
		else {
			 //fallback
			$shapeDot = new QrTagDotSquare();
		}

		//set shape dot
		$shapeDot->color = isset($params['dot_shape_color']) ? $params['dot_shape_color'] : "000000";
		$shapeDot->size  = isset($params['dot_shape_size']) ? $params['dot_shape_size'] : 14;
		$qr->setDot($shapeDot);

		//frame dot object
		if( isset($params['dot_frame_class']) && $this->_class_exists($params['dot_frame_class']) ) {
			$class    = QR_LIB_NAMESPACE.$params['dot_frame_class'];
			$frameDot = new $class();
		}
		else {
			 //fallback
			$frameDot = new QrTagFrameDotSquare();
		}

		//set frame dot
		$frameDot->color = isset($params['dot_frame_color']) ? $params['dot_frame_color'] : "000000";
		$qr->frameDot    = $frameDot;

		//main frame object
		if( isset($params['frame_class']) && $this->_class_exists($params['frame_class']) ) {
			$class = QR_LIB_NAMESPACE.$params['frame_class'];
			$frame = new $class();
		}
		else {
			//fallback
			$frame = new QrTagFrameSquare();
		}

		$frameDot->color = isset($params['frame_color']) ? $params['frame_color'] : "000000";
		$qr->frame = $frame;
	   //var_dump($qr);//exit;
		
		//generate!
		$qr->generate();

		//embed image?
		if( isset($params['embed_logo']) )
			$this->_embedLogo($params['savename'], $params['embed_logo']);

		return;
	}
	
	/**
	 * Embed Logo in QR Image
	 * @access private
	 * @param  string $qr_path
	 * @param  string $embed_img_path
	 * @param  int $embed_img_width (optional)
	 * @param  int $embed_img_height (optional)
	 * @return string
	 */	
	private function _embedLogo($qr_path, $embed_img_path, $embed_img_width = 90, $embed_img_height = 90)
	{
		$extension = strtolower(pathinfo($embed_img_path, PATHINFO_EXTENSION));
		
		//embed image type
		switch($extension) {
			case 'png':
				$embed_img = imagecreatefrompng($embed_img_path);
				break;
			case 'jpg':
				$embed_img = imagecreatefromjpeg($embed_img_path);
				break;
			case 'gif':
				$embed_img = imagecreatefromgif($embed_img_path);
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
	 * @param  string $class_name
	 * @return string
	 */
	private function _class_exists($class_name)
	{
		return class_exists(QR_LIB_NAMESPACE.$class_name);
	}
}
