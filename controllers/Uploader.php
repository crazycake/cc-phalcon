<?php
/**
 * Uploader Adapter.
 * Handle uploads, validations & img-api link.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Controllers;

//imports
use Phalcon\Exception;
use Phalcon\Image\Adapter\GD;
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
	 * Image API service URL
	 * @var string
	 */
	protected static $IMG_API_URL = "http://imgapi/";

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
	 * Initialize Trait
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
		$subdir = ($user_session = $this->session->get("user")) ? $user_session["id"] : time();

		//set upload root path
		if(empty($this->uploader_conf["root_path"]))
			$this->uploader_conf["root_path"] = self::$ROOT_UPLOAD_PATH;

		//set upload save path
		$this->uploader_conf["path"] = $this->uploader_conf["root_path"]."temp/".$subdir."/";

		//create dir if not exists
		if(!is_dir($this->uploader_conf["path"]))
			@mkdir($this->uploader_conf["path"], 0755, true);

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
		$messages = [];

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

			//check for rejected uploads
			if ($new_file["message"]) {
				array_push($messages, $new_file["message"]);
				continue;
			}

			//set file saved name
			$namespace = $new_file["key"]."_".$new_file["tag"]."_".round(microtime(true) * 1000);
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
			"messages" => $messages,
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
		@rmdir($path);
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
	 * Saves & stores uploaded files
	 * @param string $uri - The file uri
	 */
	protected function saveUploadedFiles($uri = "")
	{
		$uploaded_files = $this->getUploadedFiles(false);

		if(empty($uploaded_files))
			return [];

		$saved_files = [];

		foreach ($this->uploader_conf["files"] as $key => $conf) {

			//set amazon properties
			if(!empty($this->config->aws->s3)) {

				$conf["s3"] = (array)$this->config->aws->s3;

				// set bucket base uri
				$conf["s3"]["bucketBaseUri"] = $this->config->aws->s3->bucketBaseUri.strtolower($uri);
			}

			//jobs
			$job = !empty($conf["resize"]) ? "resize" : "s3push";

			// loop through files
			foreach ($uploaded_files as $file) {

				//check key if belongs
				if(strpos($file, $key) === false)
					continue;

				//add missing slash to uri?
				if (!empty($uri) && substr($uri, -1) != "/")
					$uri .= "/";

				//append fullpath
				$dest_folder = self::$ROOT_UPLOAD_PATH.$uri;

				//create folder?
				if(!is_dir($dest_folder))
					mkdir($dest_folder, 0755, true);

				//set source/destination path
				$src = $this->uploader_conf["path"].$file;
				$dst = $dest_folder.$file;

				//copy file & unlink temp file
				copy($src, $dst);
				unlink($src);

				if(!isset($saved_files[$key]))
					$saved_files[$key] = [];

				//skip buckets actions?
				if(!isset($conf["s3"])) {

					//append destination to array
					$saved_files[$key][] = $dst;
					continue;
				}

				//set filename
				$conf["filename"] = $file;
				// new resize job

				$saved_files[$key][] = $this->newImageApiJob($job, $dst, $conf);
			}
		}

		return $saved_files;
	}

	/**
	 * New Image Api Job, files are stored automatically in S3 (curl request)
	 * @param  string $api_uri - The img-api uri job
	 * @param  string $src - The source file
	 * @param  array $config - The config array
	 */
	public function newImageApiJob($api_uri = "", $src = "", $config = [])
	{
		if(!is_file($src))
			throw new Exception("Uploader::newImageApiJob -> File not found!");

		$body = json_encode([
			"contents" => base64_encode(file_get_contents($src)),
			"config"   => $config
		]);
		$headers = [
			"Content-Type: application/json",
			"Content-Length: ".strlen($body),
		];
		//~sd($api_uri, $src, $config);

		//curl call
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, self::$IMG_API_URL.$api_uri); // SERVICE URL
		curl_setopt($ch, CURLOPT_PORT, 80);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$result = curl_exec($ch);
		curl_close($ch);
		//~sd($result);

		//process result
		$result = json_decode($result, true);

		if($result["status"] != "ok" || empty($result["payload"])) {

			$this->logger->error("Uploader::newImageApiJob -> invalid/empty payload: ".json_encode($result, JSON_UNESCAPED_SLASHES));
			return [];
		}

		return $result["payload"];
	}

	/**
	 * Sort files by numeric tag
	 * @param  array $files - The upload file array
	 */
	public static function sortFilesByTag(&$files)
	{
		if(empty($files))
			return;

		usort($files, function($a, $b) {

			$tags1 = explode("_", $a);
			$tags2 = explode("_", $b);

			$t1 = isset($tags1[1]) ? intval($tags1[1]) : 0;
			$t2 = isset($tags2[1]) ? intval($tags2[1]) : 0;

			return $t1 > $t2;
		});
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
		$file_ext        = strtolower(end($file_name_array));
		$file_cname      = str_ireplace(".$file_ext", "", $file_name); //ignore case
		$file_mimetype   = $file->getRealType(); //real file MIME type
		$file_size       = (float)($file->getSize()); //set to KB unit
		$file_tag        = preg_replace("/[^0-9]/", "", Slug::generate($file_cname));

		// limit namespace length
		if(empty($file_tag))
			$file_tag = "0";

		//set array keys
		$new_file = [
			"name"    => $file_name,
			"tag"     => $file_tag,
			"size"    => $file_size,
			"key"     => $file_key,
			"ext"     => $file_ext,
			"mime"    => $file_mimetype,
			"message" => false
		];
		//s($this->uploader_conf);exit;

		try {

			//get file config with file_key
			$file_conf = $this->uploader_conf["files"][$file_key];
			//sd($file_conf);

			if(empty($file_conf))
				throw new Exception("Uploader file configuration missing for $file_key.");

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

			$new_file["message"] = $e->getMessage();
		}

		return $new_file;
	}
}
