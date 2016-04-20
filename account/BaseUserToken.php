<?php
/**
 * Base Model Users Tokens
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
    //this static method can be 'overrided' as late binding
    public static $TOKEN_EXPIRES_THRESHOLD = 3; //days

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
     * @var array
     */
    static $TOKEN_TYPES = ['activation', 'pass'];

    /**
     * Validation Event
     */
    public function validation()
    {
        //type
        $this->validate(new InclusionIn([
            "field"   => "type",
            "domain"  => self::$TOKEN_TYPES,
            "message" => 'Invalid token type. Types supported: '.implode(", ", self::$TOKEN_TYPES)
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
    public static function getTokenByUserAndValue($user_id, $type = 'activation', $token)
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
    public static function getTokenByUserAndType($user_id, $type = 'activation')
    {
        $conditions = "user_id = ?1 AND type = ?2";
        $binding    = [1 => $user_id, 2 => $type];

        return self::findFirst([$conditions, "bind" => $binding]);
    }

    /**
     * Saves a new ORM object
     * @static
     * @param int $user_id - The user ID
     * @param string $type - The token type, default is 'activation'
     * @return mixed [string|boolean]
     */
    public static function saveNewToken($user_id, $type = 'activation')
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
    public static function generateNewTokenIfExpired($user_id, $type)
    {
        //search if a token already exists and delete it.
        $token = self::getTokenByUserAndType($user_id, $type);

        if ($token) {
            //check token 'days passed' (checks if token is expired)
            $days_passed = Dates::getTimePassedFromDate($token->created_at);

            if ($days_passed > static::$TOKEN_EXPIRES_THRESHOLD) {
                //if token has expired delete it and generate a new one
                $token->delete();
                $token = self::saveNewToken($user_id, $type);
            }
        }
        else {
            $token = self::saveNewToken($user_id, $type);
        }

        //append encrypted data
        $di = \Phalcon\DI::getDefault();
        $token->encrypted = $di->getShared('cryptify')->encryptForGetRequest($token->user_id."#".$token->type."#".$token->token);

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
        $data = $di->getShared('cryptify')->decryptForGetResponse($encrypted_data, "#");

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

        //special case for activation (don't expires)
        if($token_type == "activation") {
            //success
            return $data;
        }

        //for other token type, get days passed
        $days_passed = Dates::getTimePassedFromDate($token->created_at);

        if ($days_passed > static::$TOKEN_EXPIRES_THRESHOLD)
            throw new Exception("temporal token (id: ".$token->id.") has expired (".$days_passed." days passed since ".$token->created_at.")");

        return $data;
    }

    /**
     * Deletes expired tokens
     * Requires Carbon library
     * @return int
     */
    public static function deleteExpiredTokens()
    {
        //use carbon to manipulate days
        try {

            //use server datetime
            $now = new \Carbon\Carbon();
            //consider one hour early from date
            $now->subDays(static::$TOKEN_EXPIRES_THRESHOLD);

            //get expired objects
            $conditions = "created_at < ?1";
            $binding    = [1 => $now->toDateTimeString()];
            //query
            $objects = self::find([$conditions, "bind" => $binding]);

            $count = 0;

            if($objects) {
                //set count
                $count = $objects->count();
                //delete action
                $objects->delete();
            }

            //delete expired objects
            return $count;
        }
        catch(Exception $e) {
            //throw new Exception("BaseUsersTokens::deleteExpiredTokens -> error: ".$e->getMessage());
            return 0;
        }
    }
}
