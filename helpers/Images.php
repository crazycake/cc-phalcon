<?php
/**
 * Forms Helper
 * Requires Dates for birthday selector
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Helpers;

//imports
use Phalcon\Exception;
use Phalcon\Image\Adapter\GD;

/**
 * Form Helper
 */
class Images
{

	/**
     * Get resized image path.
     * Example: public/uploads/media/dj/IMAGE1.jpg?v=5
     *          public/uploads/media/dj/IMAGE1_TH.jpg?v=5
     * @param  {string} url - An image URL
     * @param  {string} key - The suffix key to append
     * @return string
     */
	public static function resizedImagePath($path = "", $key = "TH")
	{
		return preg_replace("/\\.([0-9a-z]+)(?:[\\?#]|$)/i", "_".$key.".$1?", $path);
	}

    /**
     * Resize input image with config params.
     * @param  string $dest - The destination folder
     * @param  string $file - The input filename
     * @param  array $conf - The key file configuration
     * @return int total saved
     */
    public static function resize($dest = "", $filename = "", $conf = [])
    {
        //full path file
        $file = $dest.$filename;

        if(!is_file($file))
            return false;

        $image = new GD($file);
        $saved = 0;

        //loop resizer
        foreach ($conf as $key => $value) {

            //resize image with % keeping aspect ratio
            try {

                //get extension
                $ext      = pathinfo($file, PATHINFO_EXTENSION);
                $new_name = basename($file, ".$ext")."_$key.".$ext;
                $new_file = $dest.$new_name;
                //s($file, $ext, $new_name, $new_file);exit;

                $image->resize($image->getWidth()*$value/100, $image->getHeight()*$value/100);
                $image->save($new_file);

                $saved++;
            }
            catch(\Exception $e) {

                $di = \Phalcon\DI::getDefault();
                $di->logger->error("Images::resize -> failed resizing image $key: ".$e->getMessage());
            }
        }

        return $saved;
    }
}
