<?php
/**
 * StorageS3: handles storage file managment (independent)
 * Requires S3 composer library.
 * @link https://github.com/tpyo/amazon-s3-php-class
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Services;

//imports
use Phalcon\Exception;
use S3;

/**
 * Storage Handler for Amazon S3
 */
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
     * Contructor
     * @param string $access - AWS access Key
     * @param string $secret - AWS secret key
     * @param string $bucket - The AWS S3 bucket name
     * @param boolean $autoenv - Automatically sets a env suffix for dev & production
     */
    function __construct($access = "", $secret = "", $bucket = "", $autoenv = true)
    {
        if (empty($access)) {
            throw new Exception("StorageS3::__construct -> param access is required and must be an non-empty value.");
        }
        else if (empty($secret)) {
            throw new Exception("StorageS3::__construct -> param secret is required and must be an non-empty value.");
        }
        else if (empty($bucket)) {
            throw new Exception("StorageS3::__construct -> param bucket is required and must be an non-empty value.");
        }

        //set app AWS S3 bucket
        $suffix = "";

        if ($autoenv && defined("APP_ENVIRONMENT"))
            $suffix = (APP_ENVIRONMENT === "production") ? "-prod" : "-dev";

        $this->accessKey  = $access;
        $this->secretKey  = $secret;
        $this->bucketName = $bucket.$suffix;

        try {
            $this->s3 = new S3($this->accessKey, $this->secretKey);
        }
        catch (\S3Exception $e) {
            throw new Exception("StorageS3::__construct -> An error occurred authenticating S3: ".$e->getMessage());
        }
        catch (Exception $e) {
            throw new Exception("StorageS3::__construct -> An error occurred authenticating S3: ".$e->getMessage());
        }
    }

    /**
     * Push a object to AWS S3
     * @param string $file - The file path
     * @param string $uploadName - The upload name (route)
     * @param boolean $private - Flag for private file
     */
    public function putObject($file, $uploadName, $private = false)
    {
        $private = $private ? S3::ACL_PRIVATE : S3::ACL_PUBLIC_READ;

        try {
            S3::putObject(S3::inputFile($file, false), $this->bucketName, $uploadName, $private);
        }
        catch (\S3Exception $e) {
            throw new Exception("StorageS3::putObject -> An error occurred pushing resource (".$file.") to S3. Error: ".$e->getMessage());
        }
        catch (\Exception $e) {
            throw new Exception("StorageS3::putObject -> An error occurred pushing resource (".$file.") to S3. Error: ".$e->getMessage());
        }
        catch (Exception $e) {
            throw new Exception("StorageS3::putObject -> An error occurred pushing resource (".$file.") to S3. Error: ".$e->getMessage());
        }
    }

    /**
     * Get an object
     * @param string $uploadName - The upload name (route)
     * @param boolean $parseBody - Return only the binary content
     * @return object
     */
    public function getObject($uploadName, $parseBody = false)
    {
        try {
            $object = S3::getObject($this->bucketName, $uploadName);
        }
        catch (\S3Exception $e) {
            throw new Exception("StorageS3::putObject -> An error occurred getting resource (".$file.") at S3. Error: ".$e->getMessage());
        }
        catch (\Exception $e) {
            throw new Exception("StorageS3::putObject -> An error occurred pushing resource (".$file.") to S3. Error: ".$e->getMessage());
        }
        catch (Exception $e) {
            throw new Exception("StorageS3::putObject -> An error occurred getting resource (".$file.") at S3. Error: ".$e->getMessage());
        }

        if ($object && $parseBody)
            $object = $object->body;

        return $object;
    }

    /**
     * Deletes an object from storage
     * @param string $uploadName - The uploaded filename
     * @return boolean
     */
    public function deleteObject($uploadName)
    {
        return S3::deleteObject($this->bucketName, $uploadName);
    }

    /**
      * Copies an object from bucket
      * @param string $uploadName - The uploaded filename
      * @param string $bucketDest - The bucket name destination
      * @param string $saveName - The bucket file save name
      * @return boolean
      */
    public function copyObject($uploadName, $bucketDest = null, $saveName = null)
    {
        try {

            if (is_null($bucketDest))
                $bucketDest = $this->bucketName;

            $action = S3::copyObject($this->bucketName, $uploadName, $bucketDest, $saveName);

            return $action;
        }
        catch (\S3Exception $e) {
            throw new Exception("StorageS3::copyObject -> An error occurred copying resource (".$file.") at S3. Error: ".$e->getMessage());
        }
        catch (\Exception $e) {
            throw new Exception("StorageS3::putObject -> An error occurred pushing resource (".$file.") to S3. Error: ".$e->getMessage());
        }
        catch (Exception $e) {
            throw new Exception("StorageS3::copyObject -> An error occurred copying resource (".$file.") at S3. Error: ".$e->getMessage());
        }
    }
}
