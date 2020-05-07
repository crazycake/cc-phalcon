<?php
/**
 * Uploader Adapter, handle uploads, validations & img-api caller.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Controllers;

use Phalcon\Image\Adapter\GD;
use CrazyCake\Controllers\Translations;
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
	protected static $DEFAULT_MAX_SIZE = 4096; // KB

	/**
	 * Default file expire time
	 * @var Integer
	 */
	protected static $FILE_EXPIRES = 20; // minutes

	/**
	 * Header Name for file checking
	 * @var String
	 */
	protected static $HEADER_NAME = "File-Key";

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
		if (empty($conf["trans"]))
			$conf["trans"] = Translations::defaultCoreTranslations("uploader");

		$conf["files"] = $conf["files"] ?? [];

		// set conf
		$this->UPLOADER_CONF = $conf;

		// set request headers
		$this->headers = $this->request->getHeaders();

		// set key for user uploads
		$this->UPLOADER_CONF["key"] = $this->client->csrfKey."_";
	}

	/**
	 * Returns a new redis client
	 * @return Object
	 */
	protected static function newRedisClient()
	{
		$redis = new \Redis();
		$redis->connect(getenv("REDIS_HOST") ?: "redis");
		$redis->select(2);

		return $redis;
	}

	/**
	 * Action - Uploads a file
	 */
	public function uploadAction()
	{
		$upload = $this->upload();

		// event
		if (method_exists($this, "onAfterUpload"))
			$this->onAfterUpload($upload);

		$this->jsonResponse(200, $upload);
	}

	/**
	 * Uploads a file
	 * @return Array - The upload data
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

			// set file name?
			if (method_exists($this, "newUploadId"))
				$upload["id"] = $this->newUploadId($upload);
			else
				$upload["id"] = $upload["key"]."_".$upload["tag"]."_".$upload["time"].".".$upload["ext"];

			// public url
			$upload["url"] = $this->baseUrl($this->router->getControllerName()."/file/".$this->cryptify->encryptData($upload["id"]));

			// store temp file
			$this->storeFile($file->getTempName(), $upload["id"]);
		}

		// remove local file
		unlink($file->getTempName());

		return $upload;
	}

	/**
	 * Saves temporary file with base64 encoding
	 * @param String $src - The source local file
	 * @param String $filename - The filename key
	 */
	protected function storeFile($src, $filename)
	{
		if (!is_file($src)) return;

		$redis = self::newRedisClient();

		$key  = $this->UPLOADER_CONF["key"].$filename;
		$file = file_get_contents($src);

		$redis->set($key, base64_encode($file));
		$redis->expire($key, ($this->UPLOADER_CONF["expires"] ?? self::$FILE_EXPIRES) * 60); // minutes
		$redis->close();
	}

	/**
	 * Gets stored temporary file
	 * @param String $filename - The filename key
	 * @param Boolean $prefix - Append prefix to key
	 * @param Boolean $decode - Base64 decoded value
	 * @return String
	 */
	protected function getStoredFile($filename, $prefix = true, $decode = true)
	{
		$redis = self::newRedisClient();

		$key  = ($prefix ? $this->UPLOADER_CONF["key"] : "").$filename;
		$file = $redis->get($key);

		$redis->close();

		return $file ? ($decode ? base64_decode($file) : $file) : null;
	}

	/**
	 * Gets stored temporary files keys
	 * @param String $filter- A key filter (optional)
	 * @return Array
	 */
	protected function getStoredFileKeys($filter = "")
	{
		$redis = self::newRedisClient();

		$keys = $redis->keys($this->UPLOADER_CONF["key"].$filter."*");

		$redis->close();

		return $keys;
	}

	/**
	 * Action - Get file by encrypted path
	 * @param String $hash - The hash to decode
	 */
	public function fileAction($hash = "")
	{
		$redis = self::newRedisClient();

		$filename = $this->cryptify->decryptData($hash);

		if (!$file = $this->getStoredFile($filename)) die();

		$finfo = new \finfo(FILEINFO_MIME);
		$mime  = $finfo->buffer($file);

		$this->response->setStatusCode(200, "OK");
		$this->response->setContentType($mime);

		// content must be set after content type
		$this->response->setContent($file);
		$this->response->send();
		die();
	}

	/**
	 * Removes a file in uploader folder
	 * @param String $filename - The filename to remove
	 */
	protected function removeStoredFile($filename)
	{
		$redis = self::newRedisClient();

		$key = $this->UPLOADER_CONF["key"].$filename;

		$redis->del($key);
		$redis->close();
	}

	/**
	 * Ajax POST - Removes a temporary file
	 */
	public function removeUploadedFileAction()
	{
		$this->onlyAjax();

		// validate and filter request params data, second params are the required fields
		$data = $this->handleRequest(["file" => "string"], "POST");

		$this->removeStoredFile($data["file"]);

		$this->jsonResponse(200);
	}

	/**
	 * Ajax GET - Removes all files in uploader folder
	 */
	public function removeAllUploadedFilesAction()
	{
		$this->onlyAjax();

		$files = $this->getStoredFileKeys();

		if (empty($files)) return $this->jsonResponse(200);

		$redis = self::newRedisClient();

		foreach ($files as $key)
			$redis->del($key);

		$redis->close();

		$this->jsonResponse(200);
	}

	/**
	 * Push uploaded files to Image API
	 * @param String $uri - The file uri to append
	 * @param Array files - Optional files
	 * @return Array
	 */
	protected function pushToImageApi($uri = "", $files = null)
	{
		$files = $files ?? $this->getStoredFileKeys();

		if (empty($files)) return [];

		// add missing slash to uri?
		if (!empty($uri) && substr($uri, -1) != "/") $uri .= "/";

		$pushed = [];

		foreach ($this->UPLOADER_CONF["files"] as $key => $conf) {


			if (empty($conf["resize"]) && empty($conf["s3push"]))
				continue;

			// set aws data
			$conf["s3"] = [
				"accessKey"     => $this->config->aws->accessKey,
				"secretKey"     => $this->config->aws->secretKey,
				"bucketName"    => $this->config->aws->bucketName,
				"bucketBaseUri" => ($this->config->aws->bucketBaseUri ?? "temp/").strtolower($uri)
			];

			// set job (img-api)
			$job = isset($conf["resize"]) ? "resize" : "s3push";


			// loop through files
			foreach ($files as $file) {

				// check key if belongs
				if (strpos($file, $key) === false)
					continue;

				// create array
				if (!isset($pushed[$key])) $pushed[$key] = [];

				// set filename to be saved (remove prefix)
				$conf["filename"] = str_replace($this->UPLOADER_CONF["key"], "", $file);

				$contents = $this->getStoredFile($file, false, false);

				// new resize job
				$pushed[$key][] = $this->newImageApiJob($job, $contents, $conf);
			}
		}

		return $pushed;
	}

	/**
	 * New Image Api Job, files are stored automatically in S3 (curl request)
	 * @param String $api_uri - The imgapi uri job
	 * @param String $contents - The base64 encoded file
	 * @param Array $config - The config array
	 */
	protected function newImageApiJob($api_uri = "", $contents = "", $config = [])
	{
		$body = json_encode([
			"contents" => $contents,
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
			"mime" => $mimetype,
			"time" => round(microtime(true) * 1000)
		];
		//~ss($upload);

		try {

			$conf = $this->UPLOADER_CONF["files"][$key]; // file conf

			if (empty($conf))
				throw new \Exception("Uploader file configuration missing for $key.");

			// set defaults
			$conf["max_size"] = $conf["max_size"] ?? self::$DEFAULT_MAX_SIZE;
			$conf["type"]     = $conf["type"] ?? "";

			// validation: max-size
			if ($fsize/1024 > $conf["max_size"])
				throw new \Exception(str_replace(["{file}", "{size}"], [$filename, ceil($conf["max_size"]/1024)." MB"], $this->UPLOADER_CONF["trans"]["MAX_SIZE"]));

			// validation: file-type
			if (!in_array($extension, $conf["type"]))
				throw new \Exception(str_replace("{file}", $filename, $this->UPLOADER_CONF["trans"]["FILE_TYPE"]));

			// validation: image size
			if (empty($conf["isize"]))
				return $upload;

			// get file props
			$size  = $conf["isize"];
			$image = new GD($file->getTempName());

			// fixed width
			if (isset($size["w"]) && $size["w"] != $image->getWidth())
				throw new \Exception(str_replace(["{file}", "{w}"], [$filename, $size["w"]], $this->UPLOADER_CONF["trans"]["IMG_WIDTH"]));

			// fixed height
			if (isset($size["h"]) && $size["h"] != $image->getHeight())
				throw new \Exception(str_replace(["{file}", "{h}"], [$filename, $size["h"]], $this->UPLOADER_CONF["trans"]["IMG_HEIGHT"]));

			// minimun width
			if (isset($size["mw"]) && $image->getWidth() < $size["mw"])
				throw new \Exception(str_replace(["{file}", "{w}"], [$filename, $size["mw"]], $this->UPLOADER_CONF["trans"]["IMG_MIN_WIDTH"]));

			// minimun width
			if (isset($size["mh"]) && $image->getHeight() < $size["mh"])
				throw new \Exception(str_replace(["{file}", "{h}"], [$filename, $size["mh"]], $this->UPLOADER_CONF["trans"]["IMG_MIN_HEIGHT"]));

			$ratio = explode("/", $size["r"] ?? "");

			if (isset($size["r"]) && round($image->getWidth()/$image->getHeight(), 2) != round($ratio[0] / $ratio[1], 2))
				throw new \Exception(str_replace(["{file}", "{r}"], [$filename, $size["r"]], $this->UPLOADER_CONF["trans"]["IMG_RATIO"]));
		}
		catch (\Exception | \Phalcon\Exception $e) { $upload["error"] = $e->getMessage(); }

		return $upload;
	}
}
