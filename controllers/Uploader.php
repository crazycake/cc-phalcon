<?php
/**
 * Uploader Adapter. Handle uploads, validations & img-api caller.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Controllers;

use Phalcon\Exception;
use Phalcon\Image\Adapter\GD;

use CrazyCake\Helpers\Slug;

/**
 * Uploader Adapter Handler
 */
trait Uploader
{
	/**
	 * Default upload max size
	 * @var Integer
	 */
	protected static $DEFAULT_MAX_SIZE = 4096; //KB

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
	protected $UPLOADER_CONF;

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
		if (empty($conf["files"]))
			throw new Exception("Uploader requires files array config.");

		if (empty($conf["trans"]))
			$conf["trans"] = \TranslationController::getCoreTranslations("uploader");

		// set conf
		$this->UPLOADER_CONF = $conf;

		// set request headers
		$this->headers = $this->request->getHeaders();

		// set upload save path for session
		$this->UPLOADER_CONF["path"] = self::$ROOT_UPLOAD_PATH.$this->client->csrfKey."/";

		// create dir if not exists
		if (!is_dir($this->UPLOADER_CONF["path"]))
			 mkdir($this->UPLOADER_CONF["path"], 0755);
	}

	/**
	 * Action - Uploads a file
	 */
	public function uploadAction()
	{
		$this->jsonResponse(200, $this->upload());
	}

	/**
	 * Uploads a file
	 */
	protected function upload()
	{
		// check header
		if (empty($this->headers[self::$HEADER_NAME]) || !$this->request->hasFiles())
			$this->jsonResponse(400);

		// get uploaded file
		$file = current($this->request->getUploadedFiles());

		// validate file
		$upload = $this->validateUploadedFile($file, $this->headers[self::$HEADER_NAME]);

		// check for rejected uploads
		if (empty($upload["error"])) {

			// set file name
			$filename = $upload["key"]."_".$upload["tag"]."_".round(microtime(true) * 1000).".".$upload["ext"];

			$upload["id"] = $filename;

			// public url
			$upload["url"] = $this->baseUrl($this->router->getControllerName()."/file/".$this->cryptify->encryptData($filename));

			// move file into temp folder
			$file->moveTo($this->UPLOADER_CONF["path"].$filename);
		}
		else
			unlink($file->getTempName());

		return $upload;
	}

	/**
	 * Action - Get file by encrypted path
	 */
	public function fileAction($hash = "")
	{
		$filename = $this->UPLOADER_CONF["path"].$this->cryptify->decryptData($hash);

		if (!is_file($filename)) die();

		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime  = finfo_file($finfo, $filename);

		$this->response->setStatusCode(200, "OK");
		$this->response->setContentType($mime);

		// content must be set after content type
		$this->response->setContent(file_get_contents($filename));
		$this->response->send();
		die();
	}

	/**
	 * Ajax POST - Removes a file in uploader folder
	 */
	public function removeUploadedFileAction()
	{
		$this->onlyAjax();

		// validate and filter request params data, second params are the required fields
		$data = $this->handleRequest(["file" => "string"], "POST");

		// set file path
		$file_path = $this->UPLOADER_CONF["path"].$data["file"];

		if (is_file($file_path))
			unlink($file_path);

		$this->jsonResponse(200, ["file" => $file_path]);
	}

	/**
	 * Ajax GET - Removes all files in uploader folder
	 */
	public function removeAllUploadedFilesAction()
	{
		$this->onlyAjax();

		$this->cleanUploadFolder();

		$this->jsonResponse(200);
	}

	/**
	 * Cleans upload folder
	 * @param String $path - The target path to delete
	 */
	protected function cleanUploadFolder($path = null)
	{
		if (empty($path))
			$path = $this->UPLOADER_CONF["path"];

		if (!is_dir($path))
			return;

		array_map(function($f) { @unlink($f); }, glob($path."*"));
	}

	/**
	 * Gets uploaded files in temp directory
	 * @param Boolean $absolute_path - Append absolute path to each image (optional)
	 * @param String $filter_key - File Key Filter (optional)
	 * @return String
	 */
	protected function getUploadedFiles($absolute_path = true, $filter_key = false)
	{
		// filter function
		$filter = function($array) use ($filter_key) {

			return array_filter($array, function($f) use ($filter_key) { return strpos($f, $filter_key) !== false; });
		};

		// exclude hidden files
		$files = preg_grep('/^([^.])/', scandir($this->UPLOADER_CONF["path"]));

		if (!$absolute_path)
			return $filter_key ? $filter(array_values($files)) : array_values($files);

		$files = array_map(function($f) { return $this->UPLOADER_CONF["path"].$f; }, $files);

		return $filter_key ? $filter(array_values($files)) : array_values($files);
	}

	/**
	 * Push uploaded files to Image API
	 * @param String $uri - The file uri to append
	 * @return Array - The saved uploaded files
	 * @return Array - Optional files
	 */
	protected function pushToImageApi($uri = "", $files = false)
	{
		$files = $files ? $files : $this->getUploadedFiles(false);

		if (empty($files)) return [];

		// add missing slash to uri?
		if (!empty($uri) && substr($uri, -1) != "/")
			$uri .= "/";

		$pushed = [];

		foreach ($this->UPLOADER_CONF["files"] as $key => $conf) {

			if (empty($conf["resize"]) && empty($conf["s3push"]))
				continue;

			// set bucket base uri
			$conf["s3"] = (array)$this->config->aws->s3;
			$conf["s3"]["bucketBaseUri"] .= strtolower($uri);

			// set job (img-api)
			$job = !empty($conf["resize"]) ? "resize" : "s3push";

			// loop through files
			foreach ($files as $file) {

				// check key if belongs
				if (strpos($file, $key) === false)
					continue;

				// create array
				if (!isset($pushed[$key]))
					$pushed[$key] = [];

				// set filename to be saved
				$conf["filename"] = $file;
				// new resize job
				$pushed[$key][] = $this->newImageApiJob($job, $this->UPLOADER_CONF["path"].$file, $conf);
			}
		}

		return $pushed;
	}

	/**
	 * New Image Api Job, files are stored automatically in S3 (curl request)
	 * @param String $api_uri - The imgapi uri job
	 * @param String $src - The source file
	 * @param Array $config - The config array
	 */
	protected function newImageApiJob($api_uri = "", $src = "", $config = [])
	{
		if (!is_file($src))
			return null;

		$body = json_encode([
			"contents" => base64_encode(file_get_contents($src)),
			"config"   => $config
		]);

		$headers = [
			"Content-Type: application/json",
			"Content-Length: ".strlen($body),
		];

		$url       = getenv("IMGAPI_URL") ?: "http://imgapi/";
		$url_parts = parse_url($url);

		$options = [
			CURLOPT_URL            => $url_parts["scheme"]."://".$url_parts["host"]."/".$api_uri, // SERVICE URL
			CURLOPT_PORT           => $url_parts["port"] ?? ($url_parts["scheme"] == "http" ? 80 : 443),
			CURLOPT_POST           => 1,
			CURLOPT_POSTFIELDS     => $body,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_TIMEOUT        => 90,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERAGENT      => "Phalcon"
		];

		$ch = curl_init();
	 	curl_setopt_array($ch, $options);
		$result = curl_exec($ch);
		curl_close($ch);
		//~ss($result, $options);

		// parse result
		$response = json_decode($result, true);

		$this->logger->debug("Uploader::newImageApiJob -> [$url] payload: ".json_encode($response, JSON_UNESCAPED_SLASHES)."\n".print_r($result, true));

		return $response["payload"] ?? null;
	}

	/**
	 * Sort files by numeric tag
	 * @param Array $files - The upload file array
	 */
	protected static function sortFilesByTag(&$files)
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

	/**
	 * Validate uploaded file
	 * @param String $file object - The phalcon uploaded file
	 * @param String $key string - The file key
	 * @return Array
	 */
	protected function validateUploadedFile($file, $key = "")
	{
		// get file properties
		$filename  = $file->getName();
		$pieces    = explode(".", $filename);
		$extension = strtolower(end($pieces));
		$mimetype  = $file->getRealType();      // real file MIME type
		$fsize     = (float)($file->getSize()); // set to KB unit

		// change special extensions
		if ($extension == "jpeg") {

			$filename  = str_replace(".jpeg", ".jpg", $filename);
			$extension = "jpg";
		}
		else if ($extension == "blob" && $mimetype == "image/jpeg") {

			$filename  = $key."-".uniqid();
			$extension = "jpg";
		}

		// set tag
		$cname = str_ireplace(".$extension", "", $filename);
		$tag   = preg_replace("/[^0-9]/", "", Slug::generate($cname));

		// limit namespace length
		if (empty($tag)) $tag = "0";

		$upload = [
			"name" => $filename,
			"tag"  => $tag,
			"size" => $fsize,
			"key"  => $key,
			"ext"  => $extension,
			"mime" => $mimetype
		];
		//~ss($upload);

		try {

			$conf = $this->UPLOADER_CONF["files"][$key]; // file conf

			if (empty($conf))
				throw new Exception("Uploader file configuration missing for $key.");

			// set defaults
			$conf["max_size"] = $conf["max_size"] ?? self::$DEFAULT_MAX_SIZE;
			$conf["type"]     = $conf["type"] ?? "";

			// validation: max-size
			if ($fsize/1024 > $conf["max_size"])
				throw new Exception(str_replace(["{file}", "{size}"], [$filename, ceil($conf["max_size"]/1024)." MB"], $this->UPLOADER_CONF["trans"]["MAX_SIZE"]));

			// validation: file-type
			if (!in_array($extension, $conf["type"]))
				throw new Exception(str_replace("{file}", $filename, $this->UPLOADER_CONF["trans"]["FILE_TYPE"]));

			// validation: image size
			if (empty($conf["isize"]))
				return $upload;

			// get file props
			$size  = $conf["isize"];
			$image = new GD($file->getTempName());

			// fixed width
			if (isset($size["w"]) && $size["w"] != $image->getWidth())
				throw new Exception(str_replace(["{file}", "{w}"], [$filename, $size["w"]], $this->UPLOADER_CONF["trans"]["IMG_WIDTH"]));

			// fixed height
			if (isset($size["h"]) && $size["h"] != $image->getHeight())
				throw new Exception(str_replace(["{file}", "{h}"], [$filename, $size["h"]], $this->UPLOADER_CONF["trans"]["IMG_HEIGHT"]));

			// minimun width
			if (isset($size["mw"]) && $image->getWidth() < $size["mw"])
				throw new Exception(str_replace(["{file}", "{w}"], [$filename, $size["mw"]], $this->UPLOADER_CONF["trans"]["IMG_MIN_WIDTH"]));

			// minimun width
			if (isset($size["mh"]) && $image->getHeight() < $size["mh"])
				throw new Exception(str_replace(["{file}", "{h}"], [$filename, $size["mh"]], $this->UPLOADER_CONF["trans"]["IMG_MIN_HEIGHT"]));

			$ratio = explode("/", $size["r"] ?? "");

			if (isset($size["r"]) && round($image->getWidth()/$image->getHeight(), 2) != round($ratio[0] / $ratio[1], 2))
				throw new Exception(str_replace(["{file}", "{r}"], [$filename, $size["r"]], $this->UPLOADER_CONF["trans"]["IMG_RATIO"]));
		}
		catch (\Exception | Exception $e) { $upload["error"] = $e->getMessage(); }

		return $upload;
	}
}
