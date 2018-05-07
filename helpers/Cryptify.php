<?php
/**
 * Cryptify : helper that encrypts & decrypts sensitive data for URL format.
 * Uses Crypt Phalcon adapter
 * Optional library: Hashids
 * @link https://github.com/ivanakimov/hashids.php
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Helpers;

//imports
use Phalcon\DI;
use Phalcon\Exception;
use Phalcon\Crypt;

/**
 * Cryptify - Crypt Helper
 */
class Cryptify
{
	/**
	 * Default cipher algorithm
	 * @var String
	 */
	const DEFAULT_CIPHER = "aes-256-cfb";

	/**
	 * Phalcon Crypt Library Instance
	 * @var Object
	 */
	private $crypt;

	/**
	 * constructor
	 * @param String $key - The salt key
	 */
	public function __construct($key = null)
	{
		//validate key is not empty or null
		if (empty($key))
			throw new Exception("Cryptify helper -> Key parameter in constructor is required.");

		//set crypt adapter
		$this->crypt = new Crypt(); //must be included in Phalcon loader configs
		$this->crypt->setKey($key);
		//set algorithm cipher
		$this->crypt->setCipher(self::DEFAULT_CIPHER);
	}

	/**
	 * Encrypts data, example: to be passed in a GET request
	 * @param Mixed [string|array] $data - The input data to encrypt
	 * @return String - The encrypted string hash
	 */
	public function encryptData($data = null)
	{
		if (empty($data) && $data != 0)
			return false;

		//encode arrays as json
		$data = (is_array($data) || is_object($data)) ? json_encode($data, JSON_UNESCAPED_SLASHES) : (string)$data;

		//key must be set in DI service
		$encrypted = $this->crypt->encrypt($data);

		//encrypt string
		$hash = str_replace("%", "-", rawurlencode(base64_encode($encrypted)));

		return $hash;
	}

	/**
	 * Decrypts hashed data
	 * @param String $hash - The encrypted text
	 * @param Mixed [boolean|string] $parse - Parses the string from a token (explode) or parses a json (optional)
	 * @return Mixed string|array - The decrypted string
	 */
	public function decryptData($hash = "", $parse = false)
	{
		try {
			if (empty($hash) || !is_string($hash))
				return false;

			//decrypt string
			$decrypted_string = $this->crypt->decrypt(base64_decode(rawurldecode(str_replace("-", "%", $hash))));
			//remove null bytes in string
			$data = str_replace(chr(0), "", $decrypted_string);

			if ($parse)
				$data = is_string($parse) ? explode($parse, $data) : json_decode($data);

			return $data;
		}
		catch (Exception $e) {

			//get DI instance (static)
			$di = \Phalcon\DI::getDefault();

			if ($di->getShared("logger"))
				$di->getShared("logger")->error("Cryptify -> decryptData failed [$hash]: ".$e->getMessage());

			return null;
		}
	}

	/**
	 * Encrypts a numeric ID and returns the hash
	 * @param Int $id - A numeric ID
	 * @return String
	 */
	public function encryptHashId($id)
	{
		if (empty($id) && $id != 0)
			return false;

		//HashIds Library Instance
		$hashids = new \Hashids\Hashids($this->crypt->getKey());

		return $hashids->encode($id);
	}

	/**
	 * Decrypt a numeric ID and returns the hash
	 * @param String $hash - An encrypted ID
	 * @return Mixed [string|boolean]
	 */
	public function decryptHashId($hash)
	{
		if (empty($hash))
			return false;

		//HashIds Library Instance
		$hashids = new \Hashids\Hashids($this->crypt->getKey());
		$data    = $hashids->decode($hash);

		return count($data) > 0 ? $data[0] : false;
	}

	/**
	 * Generates a random Hash
	 * @param Int $length - The hash length, max length 20.
	 * @param String $seed - The string seed
	 * @return String
	 */
	public function newHash($length = 20, $seed = "")
	{
		if (empty($seed))
			$seed = uniqid();

		$code = "";

		for ($k = 1; $k <= $length; $k++) {

			$num  = chr(rand(48, 57));
			$char = chr(rand(97, 122));
			//append string
			$code .= (rand(1, 2) == 1) ? $num : $char;
		}

		//make sure hash is always different
		$hash = sha1($code.microtime().$seed);
		$hash = substr(str_shuffle($hash), 0, $length);

		return $hash;
	}

	/**
	 * Generates a random alphanumeric code
	 * @param Int $length - The code length
	 * @return String
	 */
	public function newAlphanumeric($length = 8)
	{
		$code = "";

		for ($k = 0; $k < $length; $k++) {

			$num  = chr(rand(48,57));
			$char = strtoupper(chr(rand(97,122)));
			$p    = rand(1,2);
			//append
			$code .= ($p == 1) ? $num : $char;
		}
		
		//replace ambiguos chars
		$placeholders = ["0", "O", "I", "J", "B"];
		$replacers    = ["G", "Y", "1", "X", "3"];

		return str_replace($placeholders, $replacers, $code);
	}
}
