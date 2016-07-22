<?php
/**
 * Base Model User Tokens
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Account;

//imports
use Phalcon\Exception;
use Phalcon\Mvc\Model\Validator\InclusionIn;
//other imports
use CrazyCake\Helpers\Dates;

/**
 * Base User Tokens Model
 */
class BaseUserToken extends \CrazyCake\Models\Base
{
    /* properties */

    /**
     * @var int
     */
    public $user_id;

    /**
     * @var string
     */
    public $token;

    /**
     * @var string
     */
    public $type;

    /**
     * @var string
     */
    public $created_at;

    /* inclusion vars */

    /**
     * Token expiration in days.
     * Can be overrided.
     * @var integer
     */
    public static $TOKEN_EXPIRES_THRESHOLD = [
                        "activation" => 365,
                        "access"     => 1095,
                        "pass"       => 3
                   ];
    /**
     * @var array
     */
    public static $TOKEN_TYPES = ["activation", "pass", "access"];

    /**
     * Validation Event
     */
    public function validation()
    {
        //type
        $this->validate(new InclusionIn([
            "field"   => "type",
            "domain"  => self::$TOKEN_TYPES,
            "message" => "Invalid token type. Types supported: ".implode(", ", self::$TOKEN_TYPES)
        ]));

        //check validations
        if ($this->validationHasFailed() == true)
            return false;
    }

    /** ------------------------------------------- § ------------------------------------------------ **/

    /**
     * Find Token By User and Value (make sure record exists)
     * @static
     * @param int $user_id - The user ID
     * @param string $type - The token type
     * @param string $token - The token value
     * @return object
     */
    public static function getTokenByUserAndValue($user_id, $type = "activation", $token)
    {
        $conditions = "user_id = ?1 AND type = ?2 AND token = ?3";
        $binding    = [1 => $user_id, 2 => $type, 3 => $token];

        return self::findFirst([$conditions, "bind" => $binding]);
    }

    /**
     * Find Token By User and Token Type
     * @static
     * @param int $user_id - The user ID
     * @param string $type - The token type
     * @return object
     */
    public static function getTokenByUserAndType($user_id, $type = "activation")
    {
        $conditions = "user_id = ?1 AND type = ?2";
        $binding    = [1 => $user_id, 2 => $type];

        return self::findFirst([$conditions, "bind" => $binding]);
    }

    /**
     * Saves a new ORM object
     * @static
     * @param int $user_id - The user ID
     * @param string $type - The token type, default is "activation"
     * @return mixed [string|boolean]
     */
    public static function newToken($user_id, $type = "activation")
    {
        //Save a new temporal token
        $class = static::who();
        $token = new $class();

        $token->user_id    = $user_id;
        $token->token      = uniqid();  //creates a 13 len token
        $token->type       = $type;
        $token->created_at = date("Y-m-d H:i:s");

        //save token
        return $token->save() ? $token : false;

    }

    /**
     * Check token date and generate a new user token if expired, returns a token object
     * @param int $user_id - The user ID
     * @param string $type - The token type
     * @return string
     */
    public static function newTokenIfExpired($user_id, $type)
    {
        //search if a token already exists and delete it.
        $token = self::getTokenByUserAndType($user_id, $type);

        if ($token) {
            //check token "days passed" (checks if token is expired)
            $days_passed = Dates::getTimePassedFromDate($token->created_at);

            if ($days_passed > static::$TOKEN_EXPIRES_THRESHOLD[$type]) {
                //if token has expired delete it and generate a new one
                $token->delete();
                $token = self::newToken($user_id, $type);
            }
        }
        else {
            $token = self::newToken($user_id, $type);
        }

        //append encrypted data
        $di = \Phalcon\DI::getDefault();
        $token->encrypted = $di->getShared("cryptify")->encryptData($token->user_id."#".$token->type."#".$token->token);

        return $token;
    }

    /**
     * Validates user & temp-token data. Input data is encrypted with cryptify lib. Returns decrypted data.
     * DI dependency injector must have cryptify service
     * UserTokens must be set in models
     * @static
     * @param string $encrypted_data - The encrypted data
     * @return array
     */
    public static function handleUserTokenValidation($encrypted_data = null)
    {
        if (is_null($encrypted_data))
            throw new Exception("sent input null encrypted_data");

        $di   = \Phalcon\DI::getDefault();
        $data = $di->getShared("cryptify")->decryptData($encrypted_data, "#");

        //validate data (user_id, token_type and token)
        if (count($data) < 3)
            throw new Exception("decrypted data is not at least 3 dimension array (user_id, token_type, token).");

        //set vars values
        $user_id    = $data[0];
        $token_type = $data[1];
        $token      = $data[2];

        //search for user and token combination
        $token = self::getTokenByUserAndValue($user_id, $token_type, $token);

        if (!$token)
            throw new Exception("temporal token dont exists.");

        $expiration = static::$TOKEN_EXPIRES_THRESHOLD[$token_type];

        //for other token type, get days passed
        $days_passed = Dates::getTimePassedFromDate($token->created_at);

        if ($days_passed > $expiration)
            throw new Exception("temporal token (id: ".$token->id.") has expired (".$days_passed." days passed since ".$token->created_at.")");

        return $data;
    }

    /**
     * Deletes expired tokens
     * Requires Carbon library
     * @return int
     */
    public static function deleteExpired()
    {
        //use carbon to manipulate days
        try {

            $token_types = static::$TOKEN_EXPIRES_THRESHOLD;
            $count       = 0;

            foreach ($token_types as $type => $expiration) {

                //use server datetime
                $now = new \Carbon\Carbon();
                //consider one hour early from date
                $now->subDays($expiration);
                //print_r($now->toDateTimeString());exit;
                
                //get expired objects
                $conditions = "created_at < ?1 AND type = ?2";
                $binding    = [1 => $now->toDateTimeString(), 2 => $type];
                //query
                $objects = self::find([$conditions, "bind" => $binding]);

                //delete objects
                if (!$objects)
                    continue;

                //set count
                $count += $objects->count();
                //delete action
                $objects->delete();
            }

            //return count
            return $count;
        }
        catch (Exception $e) {
            //throw new Exception("BaseUserToken::deleteExpiredTokens -> error: ".$e->getMessage());
            return 0;
        }
    }
}
