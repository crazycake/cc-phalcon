<?php
/**
 * Cryptify : helper that encrypts & decrypts sensitive data for URL format.
 * Also encrypts integers IDs
 * Uses Crypt Phalcon adapter and Hashids library
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Helpers;

//imports
use Phalcon\DI;
use Phalcon\Exception;
//other imports
use Hashids\Hashids;
use Phalcon\Crypt;

/**
 * Cryptify - Crypt Helper
 */
class Cryptify
{
    /* consts */
    const DEFAULT_CIPHER = "blowfish";

    /**
     * Phalcon Crypt Library Instance
     * @var object
     * @access private
     */
    private $crypt;

    /**
     * HashIds Library Instance
     * @var object
     * @access private
     * @link http://hashids.org/php/
     */
    private $hashids;

    /**
     * constructor
     * @param string $key - The salt key
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

        //instance hashids library
        $this->hashids = new Hashids($key);
    }

    /**
     * Encrypts data, example: to be passed in a GET request
     * @param mixed [string|array] $data - The input data to encrypt
     * @return string - The encrypted string hash
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
     * @param string $hash - The encrypted text
     * @param mixed [boolean|string] $parse - Parses the string from a token (explode) or parses a json (optional)
     * @return mixed string|array - The decrypted string
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

            if ($parse) {
                $data = is_string($parse) ? explode($parse, $data) : json_decode($data);
            }

            return $data;
        }
        catch (Exception $e) {

            //get DI instance (static)
            $di = \Phalcon\DI::getDefault();

            if ($di->getShared("logger")) {
                $logger = $di->getShared("logger");
                $logger->error("Cryptify -> Failed decryptData: ".$hash.". Err: ".$e->getMessage());
            }

            return null;
        }
    }

    /**
     * Encrypts a numeric ID and returns the hash
     * @param int $id - A numeric ID
     * @return string
     */
    public function encryptHashId($id)
    {
        if (empty($id) && $id != 0)
            return false;

        return $this->hashids->encode($id);
    }

    /**
     * Decrypt a numeric ID and returns the hash
     * @param string $hash - An encrypted ID
     * @return mixed [string|boolean]
     */
    public function decryptHashId($hash)
    {
        if (empty($hash))
            return false;

        $data = $this->hashids->decode($hash);

        return count($data) > 0 ? $data[0] : false;
    }

    /**
     * Generates a random alphanumeric code
     * @param int $length - The code length
     * @return string
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
        $placeholders = ["O", "I", "J", "B"];
        $replacers    = ["0", "1", "X", "3"];

        return str_replace($placeholders, $replacers, $code);
    }
}
