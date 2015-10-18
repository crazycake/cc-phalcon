<?php
/**
 * Cryptify : helper that encrypts & decrypts sensitive data for URL format.
 * Also encrypts integers IDs
 * Uses Crypt Phalcon adapter and Hashids library
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Utils;

//imports
use Phalcon\DI;
use Phalcon\Exception;
//other imports
use Hashids\Hashids;
use Phalcon\Crypt;


class Cryptify
{
    /* consts */
    const DEFAULT_CIPHER = 'blowfish';

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
     * @param string $key The salt key
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
     * Encrypts data to be passed in a GET request
     * @param mixed [string|array] $data
     * @return string The encrypted string
     */
    public function encryptForGetRequest($data = null)
    {
        if(empty($data) && $data != 0)
            return false;

        //encode arrays as json
        $data = is_array($data) ? json_encode($data, JSON_UNESCAPED_SLASHES) : (string)$data;

        //key must be set in DI service
        $encrypted = $this->crypt->encrypt($data);

        //encrypt string
        $encrypted_string = str_replace('%', '-', rawurlencode(base64_encode($encrypted)));

        return $encrypted_string;
    }

    /**
     * Decrypts data received in a GET request
     * @param string $encrypted_text The encrypted text
     * @param mixed [boolean|string] parse Optional, parse the string from a token (explode) or parses a json
     * @return mixed string|array The decrypted string
     */
    public function decryptForGetResponse($encrypted_text = "", $parse = false)
    {
        try {
            if(empty($encrypted_text) || !is_string($encrypted_text))
                return false;

            //decrypt string
            $decrypted_string = $this->crypt->decrypt(base64_decode(rawurldecode(str_replace('-', '%', $encrypted_text))));
            //remove null bytes in string
            $data = str_replace(chr(0), '', $decrypted_string);

            if($parse) {
                $data = is_string($parse) ? explode($parse, $data) : json_decode($data);
            }

            return $data;
        }
        catch (Exception $e) {

            //get DI instance (static)
            $di = DI::getDefault();

            if($di->getShared("logger")) {
                $logger = $di->getShared("logger");
                $logger->error("Cryptify -> Failed decryptForGetResponse: ".$encrypted_text.". Err: ".$e->getMessage());
            }

            return null;
        }
    }

    /**
     * Encrypts a numeric ID and returns the hash
     * @param int $id
     * @return string
     */
    public function encryptHashId($id)
    {
        if(empty($id) && $id != 0)
            return false;

        return $this->hashids->encode($id);
    }

    /**
     * Decrypt a numeric ID and returns the hash
     * @param string $hash
     * @return mixed
     */
    public function decryptHashId($hash)
    {
        if(empty($hash))
            return false;

        $data = $this->hashids->decode($hash);

        return count($data) > 0 ? $data[0] : false;
    }

    /**
     * Generates a random alphanumeric code
     * @param  int $length The code length
     * @return string
     */
    public function generateAlphanumericCode($length = 8)
    {
        $code = "";

        for($k = 0; $k < $length; $k++) {

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
