<?php
/**
 * Images Helper
 * Requires Imagick PHP extension
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Helpers;

//imports
use Phalcon\Exception;

/**
 * Form Helper
 */
class Images
{
	/**
     * Get resized image path.
     * Example: ./media/dj/IMAGE1.jpg?v=5
     *          ./media/dj/IMAGE1_TH.jpg?v=5
     * @param  {string} url - An image URL
     * @param  {string} key - The suffix key to append
     * @return string
     */
	public static function resizedImagePath($path = "", $key = "TN")
	{
		$new_url = preg_replace("/\\.([0-9a-z]+)(?:[\\?#]|$)/i", "_".$key.".$1?", $path);

        //remove single question marks
        if(substr($new_url, -1) == "?")
            $new_url = substr($new_url, 0, strlen($new_url) - 1);

        return $new_url;
	}

    /**
     * Resize input image with config params.
     * @param  string $src - The source folder
     * @param  string $filepath - The input filename
     * @param  array $conf - The key file configuration
     * @return int total saved
     */
    public static function resize($filepath = "", $conf = [])
    {
        if(!is_file($filepath))
            throw new Exception("Images::resize -> File not found: $filepath");

        $src     = dirname($filepath)."/";
        $resized = [];
        //loop resizer
        foreach ($conf as $key => $array) {

            try {

                //new image object from original file
                $image    = new \Phalcon\Image\Adapter\Imagick($filepath);
                $ratio    = $image->getWidth() / $image->getHeight();
                //get file name & ext
                $ext      = pathinfo($filepath, PATHINFO_EXTENSION);
                $new_name = basename($filepath, ".$ext")."_$key.".$ext;
                $new_file = $src.$new_name;
                //s($filepath, $ext, $new_name, $new_file);exit;

                //++ Resizes (keeping aspect ratio)
                //percentage
                if(isset($array["p"])) {
                    $image->resize($image->getWidth()*$array["p"]/100, $image->getHeight()*$array["p"]/100);
                }
                //width px, keep ratio
                else if(isset($array["w"])) {
                    $image->resize($array["w"], $array["w"] / $ratio);
                }
                //height px, keep ratio
                else if(isset($array["h"])) {
                    $image->resize($array["h"] * $ratio, $array["h"]);
                }
                //height px, keep ratio
                else if(isset($array["c"])) {
                    $image->crop($array["c"][0], $array["c"][1], $array["c"][2], $array["c"][3]);
                }

                //++ EFXs
                //blur
                if(isset($array["b"]))
                    $image->blur($array["b"]);
                //rotate
                if(isset($array["r"]))
                    $image->rotate($array["r"]);

                //save image (default 100%)
				$quality = isset($array["q"]) ? $array["q"] : 100;

                $image->save($new_file, $quality);

                $resized[] = $new_file;
            }
            catch(\Exception $e) {

                $di = \Phalcon\DI::getDefault();

                $di->getShared("logger")->error("Images::resize -> failed resizing image $key: ".$e->getMessage());
            }
        }

        return $resized;
    }
}
