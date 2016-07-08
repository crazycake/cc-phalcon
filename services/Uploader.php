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

        //set defaults
        if(!isset($this->uploader_conf["max_size"]))
            $this->uploader_conf["max_size"] = self::$DEFAULT_MAX_SIZE;

        if(!isset($this->uploader_conf["file_type"]))
            $this->uploader_conf["file_type"] = self::$DEFAULT_FILE_TYPE;
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

        // loop through uploaded files
        $files = $this->request->getUploadedFiles();

        foreach ($files as $file) {

            $validated_file = $this->_validateUploadedFile($file);

            //check for error
            if ($validated_file["error"]) {
                array_push($errors, $validated_file["error"]);
                continue;
            }

            array_push($uploaded, $validated_file);
            //Move the file into the application
            $file->moveTo(self::$UPLOAD_PATH.$validated_file["namespace"]);
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
    private function _validateUploadedFile($file)
    {
        //get file properties
        $file_key        = $file->getKey();
        $file_name       = $file->getName();
        $file_name_array = explode(".", $file_name);

        $file_ext       = end($file_name_array);                     //get file extension
        $file_cname     = str_replace(".$file_ext", "", $file_name); //get clean name
        $file_mimetype  = $file->getRealType();                      //real file MIME type
        $file_size      = (float)($file->getSize() / 1000);          //set to KB unit
        $file_namespace = Slug::generate($file_cname);               //namespacer slug

        //set array keys
        $validated_file = [
            "name"      => $file_name,
            "namespace" => $file_namespace.".$file_ext",
            "size"      => $file_size,
            "key"       => $file_key,
            "ext"       => $file_ext,
            "mime"      => $file_mimetype,
            "error"     => false
        ];
        //var_dump($validated_file);exit;

        //check size
        if ($file_size > $this->uploader_conf["max_size"]) {

            $validated_file["error_code"] = 910;
            //msg
            $validated_file["error"] = str_replace(["{file}", "{size}"],[$file_name, $this->uploader_conf["max_size"]." KB"],
                                                    $this->uploader_conf["trans"]["MAX_SIZE"]);
        }
        //check extension
        else if (!in_array($file_ext, $this->uploader_conf["file_type"])) {

            $validated_file["error_code"] = 911;
            //msg
            $validated_file["error"] = str_replace("{file}", $file_name, $this->uploader_conf["trans"]["FILE_TYPE"]);
        }

        //append resource url
        $validated_file["url"] = $this->baseUrl("uploads/temp/".$validated_file["namespace"]);

        return $validated_file;
    }
}
