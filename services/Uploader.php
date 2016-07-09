<?php
/**
 * Uploader Adapter
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Services;

//imports
use Phalcon\Exception;
use CrazyCake\Helpers\Slug;

/**
 * Uploader Adapter Handler
 */
trait Uploader
{
    /**
     * Temporal upload path
     * @var string
     */
    protected static $UPLOAD_PATH = PUBLIC_PATH."uploads/temp/";

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
	 * Config var
	 * @var array
	 */
	public $uploader_conf;

    /**
     * This method must be call in constructor parent class
     * @param array $conf - The config array
     */
    public function initUploader($conf = [])
    {
        $this->uploader_conf = $conf;

        //create dir if not exists
        if(!is_dir(self::$UPLOAD_PATH))
            mkdir(self::$UPLOAD_PATH, 0755);

        if(empty($conf["files"]))
            throw new Exception("Uploader requires files array in config.");

        //set data for view
        $this->view->setVar("upload_files", $this->uploader_conf["files"]);
    }

    /**
     * Action - Uploads a file
     */
    public function uploadAction()
    {
        $uploaded = [];
        $errors   = [];

        // check if user has uploaded files
        if (!$this->request->hasFiles())
            $this->jsonResponse(901);

        // get headers to set the uploaded object
        $headers = $this->request->getHeaders();

        if(empty($headers[self::$HEADER_NAME]))
            $this->jsonResponse(406);

        // loop through uploaded files
        $files = $this->request->getUploadedFiles();

        foreach ($files as $index => $file) {

            //validate file
            $new_file = $this->_validateUploadedFile($file, $headers[self::$HEADER_NAME]);

            //check for error
            if ($new_file["error"]) {
                array_push($errors, $new_file["error"]);
                continue;
            }

            //set file saved name
            $save_name = $new_file["key"].($index + 1).".".$new_file["ext"];
            //append resource url
            $new_file["url"] = $this->baseUrl("uploads/temp/".$save_name."?v=".time());

            //move file into temp folder
            $file->moveTo(self::$UPLOAD_PATH.$save_name);
            //push to array
            array_push($uploaded, $new_file);
        }

        //set payload
    	$payload = [
            "uploaded" => $uploaded,
            "errors"   => $errors
        ];

		//response
		$this->jsonResponse(200, $payload);
    }

    /**
     * Cleans upload temporal folder
     */
    public function cleanTemporalFolder()
    {
        if(!is_dir(self::$UPLOAD_PATH))
            return;

        //cleans folder
        array_map('unlink', glob(self::$UPLOAD_PATH."*"));
    }

    /** ------------------------------------------- § ------------------------------------------------ **/

    /**
     * Validate uploaded file
     * @return array
     */
    private function _validateUploadedFile($file, $file_key = "")
    {
        //get file properties
        $file_name       = $file->getName();
        $file_name_array = explode(".", $file_name);

        $file_ext       = end($file_name_array);
        $file_cname     = str_replace(".".$file_ext, "", $file_name);
        $file_namespace = Slug::generate($file_cname);
        $file_mimetype  = $file->getRealType();              //real file MIME type
        $file_size      = (float)($file->getSize() / 1000);  //set to KB unit

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
        //var_dump($new_file);exit;

        try {

            //get file config with file_key
            $file_conf = array_filter($this->uploader_conf["files"], function($o) use ($file_key) {

                return $o["key"] == $file_key;
            });

            if(empty($file_conf))
                throw new Exception("Uploader file configuration missing for $file_name.");

            //get first
            $file_conf = $file_conf[0];

            //set defaults
            if(!isset($file_conf["max_size"]))
                $file_conf["max_size"] = self::$DEFAULT_MAX_SIZE;

            if(!isset($file_conf["type"]))
                $file_conf["type"] = self::$DEFAULT_FILE_TYPE;

            //validations
            if ($file_size > $file_conf["max_size"])
                throw new Exception(str_replace(["{file}", "{size}"],[$file_name, $file_conf["max_size"]." KB"],
                                                                      $this->uploader_conf["trans"]["MAX_SIZE"]));

            if (!in_array($file_ext, $file_conf["type"]))
                throw new Exception(str_replace("{file}", $file_name, $this->uploader_conf["trans"]["FILE_TYPE"]));
        }
        catch (Exception $e) {

            $new_file["error"] = $e->getMessage();
        }

        return $new_file;
    }
}
