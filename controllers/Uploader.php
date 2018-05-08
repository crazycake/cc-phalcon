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
	 * @var Integer
	 */
	protected static $DEFAULT_MAX_SIZE = 3072; //KB

	/**
	 * Default upload file type
	 * @var Array
	 */
	protected static $DEFAULT_FILE_TYPE = ["csv"];

	/**
	 * Header Name for file checking
	 * @var String
	 */
	protected static $HEADER_NAME = "File-Key";

	/**
	 * Root upload path. Files are saved in a temporal public user folder.
	 * @var String
	 */
	public static $ROOT_UPLOAD_PATH = STORAGE_PATH."uploads/";

	/**
	 * trait config var
	 * @var Array
	 */
	protected $uploader_conf;

	/**
	 * Request headers
	 * @var Array
	 */
	private $headers;

	/**
	 * Initialize Trait
	 * @param Array $conf - The config array
	 */
	protected function initUploader($conf = [])
	{
		if (empty($conf["trans"]))
			$conf["trans"] = \TranslationController::getCoreTranslations("uploader");

		//set conf
		$this->uploader_conf = $conf;

		if (empty($conf["files"]))
			throw new Exception("Uploader requires files array config.");

		//set request headers
		$this->headers = $this->request->getHeaders();

		//get session user id or temp dir
		$subdir = ($user_session = $this->session->get("user")) ? $user_session["id"] : time();

		//set upload root path
		if (empty($this->uploader_conf["root_path"]))
			$this->uploader_conf["root_path"] = self::$ROOT_UPLOAD_PATH;

		//set upload save path
		$this->uploader_conf["path"]     = $this->uploader_conf["root_path"]."temp/".$subdir."/";
		$this->uploader_conf["path_url"] = $this->baseUrl("uploads/temp/".$subdir."/");

		//create dir if not exists
		if (!is_dir($this->uploader_conf["path"]))
			@mkdir($this->uploader_conf["path"], 0755, true);

		//set data for view
		$this->view->setVar("upload_files", $this->uploader_conf["files"]);
	}

	/**
	 * Dispatch Event, cleans upload folder for non ajax requests
	 */
	public function afterExecuteRoute()
	{
		parent::afterExecuteRoute();

		if (!$this->request->isAjax())
			$this->cleanUploadFolder();
	}

	/**
	 * Uploads a file
	 */
	public function upload()
	{
		$uploaded = [];
		$messages = [];

		//check header
		if (empty($this->headers[self::$HEADER_NAME]))
			$this->jsonResponse(404);

		//check if user has uploaded files
		if (!$this->request->hasFiles())
			$this->jsonResponse(900);

		// loop through uploaded files
		$files = $this->request->getUploadedFiles();

		foreach ($files as $file) {

			//validate file
			$new_file = $this->_validateUploadedFile($file, $this->headers[self::$HEADER_NAME]);

			//check for rejected uploads
			if ($new_file["message"]) {

				array_push($messages, $new_file["message"]);
				//remove temp file
				unlink($file->getTempName());
				continue;
			}

			//set file saved name
			$namespace = $new_file["key"]."_".$new_file["tag"]."_".round(microtime(true) * 1000);
			$save_name = $namespace.".".$new_file["ext"];
			//append resource url
			$new_file["url"]            = $this->uploader_conf["path_url"].$save_name;
			$new_file["save_name"]      = $save_name;
			$new_file["save_namespace"] = $namespace;

			//move file into temp folder
			$file->moveTo($this->uploader_conf["path"].$save_name);
			//push to array
			array_push($uploaded, $new_file);
		}

		return [$uploaded, $messages];
	}

	/**
	 * Action - Uploads a file
	 */
	public function uploadAction()
	{
		list($uploaded, $messages) = $this->upload();
		
		//response
		$this->jsonResponse(200, [
			"uploaded" => $uploaded,
			"messages" => $messages
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

		//get file path
		$file_path = $this->uploader_conf["path"].$data["uploaded_file"]["save_name"];

		//check if exists
		if (is_file($file_path))
			unlink($file_path);

		$this->jsonResponse(200, ["file" => $file_path]);
	}

	/**
	 * Ajax Action - Removes all files in uploader folder
	 */
	public function removeAllUploadedFilesAction()
	{
		$this->onlyAjax();

		$this->cleanUploadFolder();

		$this->jsonResponse(200);
	}

	/**
	 * Ajax Action - Replace a current upload
	 */
	public function replaceUploadAction()
	{
		list($uploaded, $messages) = $this->upload();

		//response
		$this->jsonResponse(200, [
			"uploaded" => $uploaded,
			"messages" => $messages
		]);
	}

	/**
	 * Cleans upload folder
	 * @param String $path - The target path to delete
	 */
	protected function cleanUploadFolder($path = "")
	{
		if (empty($path))
			$path = $this->uploader_conf["path"];

		if (!is_dir($path))
			return;

		//cleans folder
		array_map(function($f) { @unlink($f); }, glob($path."*"));
	}

	/**
	 * Gets uploaded files in temp directory
	 * @param Boolean $absolute_path - Append absolute path to each image
	 * @return String
	 */
	protected function getUploadedFiles($absolute_path = true)
	{
		//exclude hidden files
		$files = preg_grep('/^([^.])/', scandir($this->uploader_conf["path"]));

		if (!$absolute_path)
			return array_values($files);

		$files = array_map(function($f) { return $this->uploader_conf["path"].$f; }, $files);

		return array_values($files);
	}

	/**
	 * Saves & stores uploaded files
	 * @param String $uri - The file uri
	 * @return Array - The saved uploaded files
	 */
	protected function saveUploadedFiles($uri = "")
	{
		$uploaded_files = $this->getUploadedFiles(false);

		if (empty($uploaded_files))
			return [];

		$saved_files = [];

		foreach ($this->uploader_conf["files"] as $key => $conf) {

			//set amazon properties
			if (!empty($this->config->aws->s3)) {

				$conf["s3"] = (array)$this->config->aws->s3;
				// set bucket base uri
				$conf["s3"]["bucketBaseUri"] .= strtolower($uri);
			}

			//set job (img-api)
			$job = !empty($conf["resize"]) ? "resize" : "s3push";

			// loop through files
			foreach ($uploaded_files as $file) {

				//check key if belongs
				if (strpos($file, $key) === false)
					continue;

				//add missing slash to uri?
				if (!empty($uri) && substr($uri, -1) != "/")
					$uri .= "/";

				//append fullpath
				$dest_folder = self::$ROOT_UPLOAD_PATH.$uri;

				//create folder?
				if (!is_dir($dest_folder))
					mkdir($dest_folder, 0755, true);

				//set source/destination path
				$src = $this->uploader_conf["path"].$file;
				$dst = $dest_folder.$file;

				//copy file & unlink temp file
				copy($src, $dst);
				unlink($src);

				if (!isset($saved_files[$key]))
					$saved_files[$key] = [];

				//skip buckets actions?
				if (!isset($conf["s3"])) {

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
	 * @param String $api_uri - The imgapi uri job
	 * @param String $src - The source file
	 * @param Array $config - The config array
	 */
	public function newImageApiJob($api_uri = "", $src = "", $config = [])
	{
		if (!is_file($src))
			throw new Exception("Uploader::newImageApiJob -> File not found!");

		$body = json_encode([
			"contents" => base64_encode(file_get_contents($src)),
			"config"   => $config
		]);

		$headers = [
			"Content-Type: application/json",
			"Content-Length: ".strlen($body),
		];

		$url       = APP_ENV == "local" || !getenv("IMGAPI_URL") ? "http://imgapi/" : getenv("IMGAPI_URL");
		$url_parts = parse_url($url);

		$options = [
			CURLOPT_URL            => $url_parts["scheme"]."://".$url_parts["host"]."/".$api_uri, // SERVICE URL
			CURLOPT_PORT           => $url_parts["port"] ?? 80,
			CURLOPT_POST           => 1,
			CURLOPT_POSTFIELDS     => $body,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_RETURNTRANSFER => true
		];

		//curl call
		$ch = curl_init();
	 	curl_setopt_array($ch, $options);
		$result = curl_exec($ch);
		curl_close($ch);

		//process result
		$response = json_decode($result, true);

		$this->logger->debug("Uploader::newImageApiJob -> [$url] payload: ".json_encode($response, JSON_UNESCAPED_SLASHES)."\n".print_r($result, true));

		return $response["payload"] ?? null;
	}

	/**
	 * Get upload local path
	 * @param String $url - The remote url upload
	 */
	public function getUploadLocalFilepath($url = "")
	{
		$uri = substr($url, strpos($url, $this->config->aws->s3->bucketBaseUri) +
							strlen($this->config->aws->s3->bucketBaseUri));

		$filepath = self::$ROOT_UPLOAD_PATH.$uri;

		return $filepath;
	}

	/**
	 * Sort files by numeric tag
	 * @param Array $files - The upload file array
	 */
	public static function sortFilesByTag(&$files)
	{
		if (empty($files))
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
	 * @param String $file object - The phalcon uploaded file
	 * @param String $file_key string - The file key
	 * @return Array
	 */
	private function _validateUploadedFile($file, $file_key = "")
	{
		//get file properties
		$file_name       = $file->getName();
		$file_name_array = explode(".", $file_name);
		$file_ext        = strtolower(end($file_name_array));
		$file_mimetype   = $file->getRealType(); //real file MIME type
		$file_size       = (float)($file->getSize()); //set to KB unit

		// change special extensions
		if($file_ext == "jpeg") {

			$file_name = str_replace(".jpeg", ".jpg", $file_name);
			$file_ext = "jpg";
		}

		else if($file_ext == "blob" && $file_mimetype == "image/jpeg") {

			$file_name = str_replace(".blob", ".jpg", $file_name);
			$file_ext = "jpg";
		}

		$file_cname = str_ireplace(".$file_ext", "", $file_name); //ignore case
		$file_tag   = preg_replace("/[^0-9]/", "", Slug::generate($file_cname));

		// limit namespace length
		if (empty($file_tag))
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
		//~s($new_file);exit;

		try {

			//get file config with file_key
			$file_conf = $this->uploader_conf["files"][$file_key];

			if (empty($file_conf))
				throw new Exception("Uploader file configuration missing for $file_key.");

			//set defaults
			if (!isset($file_conf["max_size"]))
				$file_conf["max_size"] = self::$DEFAULT_MAX_SIZE;

			if (!isset($file_conf["type"]))
				$file_conf["type"] = self::$DEFAULT_FILE_TYPE;

			//validation: max-size
			if ($file_size/1024 > $file_conf["max_size"])
				throw new Exception(str_replace(["{file}", "{size}"],[$file_name, $file_conf["max_size"]." KB"],
																	  $this->uploader_conf["trans"]["MAX_SIZE"]));
			//validation: file-type
			if (!in_array($file_ext, $file_conf["type"]))
				throw new Exception(str_replace("{file}", $file_name, $this->uploader_conf["trans"]["FILE_TYPE"]));

			//validation: image size
			if (!empty($file_conf["isize"])) {

				$size  = $file_conf["isize"];
				$image = new GD($file->getTempName());

				//fixed width
				if (isset($size["w"]) && $size["w"] != $image->getWidth())
					throw new Exception(str_replace(["{file}", "{w}"], [$file_name, $size["w"]], $this->uploader_conf["trans"]["IMG_WIDTH"]));

				//fixed height
				if (isset($size["h"]) && $size["h"] != $image->getHeight())
					throw new Exception(str_replace(["{file}", "{h}"], [$file_name, $size["h"]], $this->uploader_conf["trans"]["IMG_HEIGHT"]));

				//minimun width
				if (isset($size["mw"]) && $image->getWidth() < $size["mw"])
					throw new Exception(str_replace(["{file}", "{w}"], [$file_name, $size["mw"]], $this->uploader_conf["trans"]["IMG_MIN_WIDTH"]));

				//minimun width
				if (isset($size["mh"]) && $image->getHeight() < $size["mh"])
					throw new Exception(str_replace(["{file}", "{h}"], [$file_name, $size["mh"]], $this->uploader_conf["trans"]["IMG_MIN_HEIGHT"]));

				//ratio
				$ratio = explode("/", $size["r"] ?? "");

				if (isset($size["r"]) && round($image->getWidth()/$image->getHeight(), 2) != round($ratio[0] / $ratio[1], 2))
					throw new Exception(str_replace(["{file}", "{r}"], [$file_name, $size["r"]], $this->uploader_conf["trans"]["IMG_RATIO"]));
			}
		}
		catch (\Exception | Exception $e) { $new_file["message"] = $e->getMessage(); }

		return $new_file;
	}
}
