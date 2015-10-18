<?php
/**
 * StorageController: handles storage file managment (independent)
 * Requires S3 composer library.
 * @link https://github.com/tpyo/amazon-s3-php-class
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Utils;

//imports
use Phalcon\Exception;
use S3;

class StorageS3
{
    /**
     * @var string
     */
    private $accessKey;

    /**
     * @var string
     */
    private $secretKey;

    /**
     * The name of the S3 bucket
     * @var string
     */
    private $bucketName;

    /**
     * Non-official S3 Library
     * @var object
     */
    private $s3;

    /**
     * contructor
     * @param string $access AWS access Key
     * @param string $secret AWS secret key
     * @param string $bucket The AWS S3 bucket name
     */
    function __construct($access, $secret, $bucket) {

        if(empty($access)) {
            throw new Exception("StorageS3::__construct -> param access is required and must be an non-empty value.");
        }
        else if(empty($secret)) {
            throw new Exception("StorageS3::__construct -> param secret is required and must be an non-empty value.");
        }
        else if(empty($bucket)) {
            throw new Exception("StorageS3::__construct -> param bucket is required and must be an non-empty value.");
        }

        $this->accessKey  = $access;
        $this->secretKey  = $secret;
        $this->bucketName = $bucket;

        try {
            $this->s3 = new S3($this->accessKey, $this->secretKey);
        }
        catch (Exception $e) {
            throw new Exception("StorageS3::__construct -> An error occurred authenticating S3");
        }
    }

    /**
     * Push a object to AWS S3
     * @param string $file The file path
     * @param string $uploadName The upload name (route)
     * @param boolean $private Flag for private file
     */
    public function putObject($file, $uploadName, $private = false)
    {
        $private = $private ? S3::ACL_PRIVATE : S3::ACL_PUBLIC_READ;

        try {
            S3::putObject(S3::inputFile($file, false), $this->bucketName, $uploadName, $private);
        }
        catch (Exception $e) {
            throw new Exception("StorageS3::putObject -> An error occurred pushing resource (".$file.") to S3. Error: ".$e->getMessage());
        }
    }

    /**
     * Get an object
     * @param string $uploadName The upload name (route)
     * @param boolean $parseBody Return only the binary content
     * @throws Exception $e
     */
    public function getObject($uploadName, $parseBody = false)
    {
        $object = S3::getObject($this->bucketName, $uploadName);

        if($object && $parseBody)
            $object = $object->body;

        return $object;
    }

    /**
     * Deletes an object from storage
     * @param string $uploadName The uploaded filename
     * @return boolean
     */
     public function deleteObject($uploadName)
     {
         return S3::deleteObject($this->bucketName, $uploadName);
     }

     /**
      * Copies an object from bucket
      * @param string $uploadName The uploaded filename
      * @param string $bucketDest The bucket name destination
      * @param string $saveName The bucket file save name
      * @return mixed
      */
     public function copyObject($uploadName, $bucketDest = null, $saveName = null)
     {
         if(is_null($bucketDest))
            $bucketDest = $this->bucketName;

         return S3::copyObject($this->bucketName, $uploadName, $bucketDest, $saveName);
     }
}
