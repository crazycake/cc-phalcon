<?php
/**
 * Uploader Adapter
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 * @link https://docs.phalconphp.com/en/latest/api/Phalcon_Image_Adapter_Imagick.html
 */

namespace CrazyCake\Controllers;

//imports
use Phalcon\Exception;
use Phalcon\Image\Adapter\GD;
use CrazyCake\Helpers\Slug;
use CrazyCake\Helpers\Images;

/**
 * Uploader Adapter Handler.
 */
trait Uploader
{
    /**
     * Default upload max size
     * @var integer
     */
    protected static $DEFAULT_MAX_SIZE = 5120; //KB

    /**
     * Default upload file type
     * @var array
     */
    protected static $DEFAULT_FILE_TYPE = ["csv"];

    /**
     * Header Name for file checking
     * @var string
     */
    protected static $HEADER_NAME = "File-Key";

    /**
     * Root upload path. Files are saved in a temporal public user folder.
     * @var string
     */
    public static $ROOT_UPLOAD_PATH = STORAGE_PATH."uploads/";

    /**
	 * Config var
	 * @var array
	 */
	protected $uploader_conf;

    /**
	 * Request headers
	 * @var array
	 */
	private $headers;

    /**
     * This method must be call in constructor parent class
     * @param array $conf - The config array
     */
    protected function initUploader($conf = [])
    {
        //set conf
        $this->uploader_conf = $conf;

        if(empty($conf["files"]))
            throw new Exception("Uploader requires files array config.");

        //set request headers
        $this->headers = $this->request->getHeaders();

        //get session user id or temp dir
        $subdir = ($user_session = $this->session->get("user")) ? $user_session["id"] : microtime();

        //set upload path
        $this->uploader_conf["path"] = self::$ROOT_UPLOAD_PATH."temp/".$subdir."/";

        //create dir if not exists
        if(!is_dir($this->uploader_conf["path"]))
            mkdir($this->uploader_conf["path"], 0755, true);

        //set data for view
        $this->view->setVar("upload_files", $this->uploader_conf["files"]);
    }

    /**
     * Dispatch Event, cleans upload folder for non ajax requests
     */
    protected function afterExecuteRoute()
    {
        parent::afterExecuteRoute();

        if(!$this->request->isAjax())
            $this->cleanUploadFolder();
    }

    /**
     * Action - Uploads a file
     */
    public function uploadAction()
    {
        $uploaded = [];
        $errors   = [];

        //check if user has uploaded files
        if (!$this->request->hasFiles())
            $this->jsonResponse(901);

        //check header
        if(empty($this->headers[self::$HEADER_NAME]))
            $this->jsonResponse(406);

        // loop through uploaded files
        $files = $this->request->getUploadedFiles();

        foreach ($files as $file) {

            //validate file
            $new_file = $this->_validateUploadedFile($file, $this->headers[self::$HEADER_NAME]);

            //check for error
            if ($new_file["error"]) {
                array_push($errors, $new_file["error"]);
                continue;
            }

            //set file saved name
            $namespace = $new_file["key"]."-".time();
            $save_name = $namespace.".".$new_file["ext"];
            //append resource url
            $new_file["url"]            = $this->baseUrl("uploads/temp/".$save_name);
            $new_file["save_name"]      = $save_name;
            $new_file["save_namespace"] = $namespace;

            //move file into temp folder
            $file->moveTo($this->uploader_conf["path"].$save_name);
            //push to array
            array_push($uploaded, $new_file);
        }

		//response
		$this->jsonResponse(200, [
            "uploaded" => $uploaded,
            "errors"   => $errors
        ]);
    }

    /**
     * Ajax POST Action - Removes a file in uploader folder
     */
    public function removeUploadedFileAction()
    {
        $this->onlyAjax();

        //validate and filter request params data, second params are the required fields
        $data = $this->handleRequest([
            "uploaded_file" => "array"
        ], "POST");

        $file = $data["uploaded_file"];

        //get file path
        $file_path = $this->uploader_conf["path"].$file["save_name"];

        //check if exists
        if(is_file($file_path))
            unlink($file_path);

        $this->jsonResponse(200);
    }

    /**
     * Cleans upload folder
     * @param string $path - The target path to delete
     */
    protected function cleanUploadFolder($path = "")
    {
        if(empty($path))
            $path = $this->uploader_conf["path"];

        if(!is_dir($path))
            return;

        //cleans folder
        array_map('unlink', glob($path."*"));
        rmdir($path);
    }

    /**
     * Gets uploaded files in temp directory
     * @param boolean $absolute_path - Append absolute path to each image
     * @return string
     */
    protected function getUploadedFiles($absolute_path = true)
    {
        //exclude hidden files
        $files = preg_grep('/^([^.])/', scandir($this->uploader_conf["path"]));

        if(!$absolute_path)
            return $files;

        return array_map(function($f) { return $this->uploader_conf["path"].$f; }, $files);
    }

    /**
     * Move Uploaded files
     * @param mixed[int|string] $dest_path - Can be an object ID or a path. If is numeric, ID gets encrypted.
     */
    protected function moveUploadedFiles($dest_path = "")
    {
        $uploaded_files = $this->getUploadedFiles(false);

        if(empty($uploaded_files))
            return;

        $moved_files = [];

        foreach ($this->uploader_conf["files"] as $conf) {

            foreach ($uploaded_files as $file) {

                if(empty($conf["key"])) {

                    $this->logger->error("Couldn't move file ".$file.". Configuration exception: ".json_encode($conf));

                    throw new Exception("Hay archivos aún cargándose, profavor inténtalo nuevamente.");
                }

                //check key if belongs
                if(strpos($file, $conf["key"]) === false)
                    continue;

                //set source path
                $src = $this->uploader_conf["path"].$file;

                //add missing slash to destination path?
                if (substr($dest_path, -1) != "/") $dest_path .= "/";

                //append destination to array
                $moved_files[$conf["key"]] = $dest_path.$file;

                //append fullpath
                $dest = self::$ROOT_UPLOAD_PATH.$dest_path;

                //create folder?
                if(!is_dir($dest))
                    mkdir($dest, 0755, true);

                //copy file
                copy($src, $dest.$file);
                //unlink temp file
                unlink($src);

                //image resize
                if(!empty($conf["resize"]))
                    Images::resize($dest, $file, $conf["resize"]);
            }
        }

        return $moved_files;
    }

    /** ------------------------------------------- § ------------------------------------------------ **/

    /**
     * Validate uploaded file
     * @param $file object - The phalcon uploaded file
     * @param $file_key string - The file key
     * @return array
     */
    private function _validateUploadedFile($file, $file_key = "")
    {
        //get file properties
        $file_name       = $file->getName();
        $file_name_array = explode(".", $file_name);
        $file_ext        = end($file_name_array);
        $file_cname      = str_replace(".".$file_ext, "", $file_name);
        $file_namespace  = Slug::generate($file_cname);
        $file_mimetype   = $file->getRealType();       //real file MIME type
        $file_size       = (float)($file->getSize());  //set to KB unit

        //set array keys
        $new_file = [
            "name"      => $file_name,
            "namespace" => $file_namespace,
            "size"      => $file_size,
            "key"       => $file_key,
            "ext"       => $file_ext,
            "mime"      => $file_mimetype,
            "error"     => false
        ];
        //s($this->uploader_conf);exit;

        try {

            //get file config with file_key
            $file_conf = array_filter($this->uploader_conf["files"], function($o) use ($file_key) {

                return $o["key"] == $file_key;
            });

            if(empty($file_conf))
                throw new Exception("Uploader file configuration missing for $file_key.");

            //get first item
            $file_conf = current($file_conf);

            //set defaults
            if(!isset($file_conf["max_size"]))
                $file_conf["max_size"] = self::$DEFAULT_MAX_SIZE;

            if(!isset($file_conf["type"]))
                $file_conf["type"] = self::$DEFAULT_FILE_TYPE;

            //validation: max-size
            if ($file_size/1024 > $file_conf["max_size"])
                throw new Exception(str_replace(["{file}", "{size}"],[$file_name, $file_conf["max_size"]." KB"],
                                                                      $this->uploader_conf["trans"]["MAX_SIZE"]));
            //validation: file-type
            if (!in_array($file_ext, $file_conf["type"]))
                throw new Exception(str_replace("{file}", $file_name, $this->uploader_conf["trans"]["FILE_TYPE"]));

            //validation: image size
            if(isset($file_conf["isize"])) {

                $size  = $file_conf["isize"];
                $image = new GD($file->getTempName());

                //fixed width
                if(isset($size["w"]) && $size["w"] != $image->getWidth())
                    throw new Exception(str_replace(["{file}", "{w}"], [$file_name, $size["w"]], $this->uploader_conf["trans"]["IMG_WIDTH"]));

                //fixed height
                if(isset($size["h"]) && $size["h"] != $image->getHeight())
                    throw new Exception(str_replace(["{file}", "{h}"], [$file_name, $size["h"]], $this->uploader_conf["trans"]["IMG_HEIGHT"]));

                //minimun width
                if(isset($size["mw"]) && $image->getWidth() < $size["mw"])
                    throw new Exception(str_replace(["{file}", "{w}"], [$file_name, $size["mw"]], $this->uploader_conf["trans"]["IMG_MIN_WIDTH"]));

                //minimun width
                if(isset($size["mh"]) && $image->getHeight() < $size["mh"])
                    throw new Exception(str_replace(["{file}", "{h}"], [$file_name, $size["mh"]], $this->uploader_conf["trans"]["IMG_MIN_HEIGHT"]));

                //ratio
                if(isset($size["r"]) && round($image->getWidth()/$image->getHeight(), 2) != eval("return round(".$size["r"].", 2);"))
                    throw new Exception(str_replace(["{file}", "{r}"], [$file_name, $size["r"]], $this->uploader_conf["trans"]["IMG_RATIO"]));
            }
        }
        catch (Exception $e) {

            $new_file["error"] = $e->getMessage();
        }

        return $new_file;
    }
}
