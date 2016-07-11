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
     * Root upload path
     * Files are saved in a temporal public user folder.
     * @var string
     */
    protected static $ROOT_UPLOAD_PATH = PUBLIC_PATH."uploads/temp/";

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
        $this->uploader_conf = $conf;

        if(empty($conf["files"]))
            throw new Exception("Uploader requires files array in config.");

        //set request headers
        $this->headers = $this->request->getHeaders();

        //get session user id
        $user_session = $this->session->get("user");
        //set upload path
        $this->uploader_conf["path"] = self::$ROOT_UPLOAD_PATH.$user_session["id_hashed"]."/";

        //create dir if not exists
        if(!is_dir($this->uploader_conf["path"])) {
            mkdir($this->uploader_conf["path"], 0755);
        }
        else {

            //clean folder if uploader header is not present
            if(empty($this->headers[self::$HEADER_NAME]))
                $this->cleanUploadFolder();
        }

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
            $save_name = $new_file["key"]."-".time().".".$new_file["ext"];
            //append resource url
            $new_file["url"]       = $this->baseUrl("uploads/temp/".$save_name);
            $new_file["save_name"] = $save_name;

            //move file into temp folder
            $file->moveTo($this->uploader_conf["path"].$save_name);
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
     * Ajax Action - Removes a file in uploader folder
     */
    public function removeFileAction()
    {
        $this->onlyAjax();

        //cleans folder
        array_map('unlink', glob($this->uploader_conf["path"]."*"));

        $this->jsonResponse(200);
    }

    /**
     * Cleans upload temporal folder
     */
    protected function cleanUploadFolder()
    {
        if(!is_dir($this->uploader_conf["path"]))
            return;

        //cleans folder
        array_map('unlink', glob($this->uploader_conf["path"]."*"));
    }

    /**
     * Copy files in upload folder to given path
     */
    protected function copyFilesToPath($path = "")
    {

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
