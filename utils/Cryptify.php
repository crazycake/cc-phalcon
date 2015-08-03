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
     * @param string $text
     * @return string The encrypted string
     */
    public function encryptForGetRequest($text)
    {
        //key must be set in DI service
        $encrypted = $this->crypt->encrypt((string)$text);

        //encrypt string
        $encrypted_string = str_replace('%', '-', rawurlencode(base64_encode($encrypted)));

        return $encrypted_string;
    }

    /**
     * Decrypts data received in a GET request
     * @param string $encrypted_text The encrypted text
     * @param boolean explode Optional, explode the string to return an aray
     * @return mixed string|array The decrypted string
     */
    public function decryptForGetResponse($encrypted_text, $explode = false)
    {
        try {
            //decrypt string
            $decrypted_string = $this->crypt->decrypt(base64_decode(rawurldecode(str_replace('-', '%', $encrypted_text))));
            //remove null bytes in string
            $data = str_replace(chr(0), '', $decrypted_string);

            if($explode)
                $data = explode($explode, $data);

            return $data;
        }
        catch(Exception $e) {

            //get DI instance (static)
            $di = DI::getDefault();

            $logger = $di->getShared("logger");
            $logger->error("Criptify -> Failed decryptForGetResponse: ".$encrypted_text.". Err: ".$e->getMessage());
            return null;
        }
    }

    /**
     * Encrypts a numeric ID and returns the hash
     * @param int $input_id
     * @return string
     */
    public function encryptHashId($input_id)
    {
        return $this->hashids->encode($input_id);
    }

    /**
     * Decrypt a numeric ID and returns the hash
     * @param string $hash
     * @return mixed
     */
    public function decryptHashId($hash)
    {
        $data = $this->hashids->decode($hash);

        if (count($data) > 0)
            return $data[0];
        else
            return false;
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
        $placeholders = array("O", "I", "J", "B");
        $replacers    = array("0", "1", "X", "3");

        return str_replace($placeholders, $replacers, $code);
    }
}
