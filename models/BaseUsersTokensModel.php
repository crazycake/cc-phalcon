<?php
/**
 * Base Users Tokens Model
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Models;

//imports
use Phalcon\Mvc\Model\Validator\InclusionIn;
//other imports
use CrazyCake\Utils\DateHelper;

abstract class BaseUsersTokensModel extends BaseModel
{
    //this static method can be 'overrided' as late binding
    public static $TOKEN_EXPIRES_THRESHOLD = 2; //days

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
    static $TOKEN_TYPES = array('activation', 'pass');

    /** -------------------------------------------- § ------------------------------------------------- 
        Init
    ------------------------------------------------------------------------------------------------- **/
    public function initialize()
    {
        //Skips fields/columns on both INSERT/UPDATE operations
        $this->skipAttributes(array('created_at'));
    }
    /** -------------------------------------------------------------------------------------------------
        Validations
    ------------------------------------------------------------------------------------------------- **/
    public function validation()
    {        
        //type
        $this->validate(new InclusionIn(array(
            "field"   => "type",
            "domain"  => self::$TOKEN_TYPES,
            "message" => 'Invalid token type. Types supported: '.implode(", ", self::$TOKEN_TYPES)
        )));

        //check validations
        if ($this->validationHasFailed() == true)
            return false;
    }
    /** ------------------------------------------- § ------------------------------------------------ **/

    /**
     * Find Token By User and Value (make sure record exists)
     * @static
     * @param int $user_id
     * @param string $token
     * @param string $type
     * @return UsersTokens
     */
    public static function getTokenByUserAndValue($user_id, $token, $type = 'activation')
    {
        $conditions = "user_id = ?1 AND token = ?2 AND type = ?3";
        $parameters = array(1 => $user_id, 2 => $token, 3 => $type);

        return self::findFirst( array($conditions, "bind" => $parameters) );
    }

    /**
     * Find Token By User and Token Type
     * @static
     * @param int $user_id
     * @param string $type
     * @return UsersTokens
     */
    public static function getTokenByUserAndType($user_id, $type = 'activation')
    {
        $conditions = "user_id = ?1 AND type = ?2";
        $parameters = array(1 => $user_id, 2 => $type);

        return self::findFirst( array($conditions, "bind" => $parameters) );
    }

    /**
     * Saves a new ORM object
     * @static
     * @param int $user_id
     * @param string $type
     * @return mixed
     */
    public static function saveNewToken($user_id, $type = 'activation')
    {
        //Save a new temporal token
        $class = static::who();
        $token = new $class();
        $token->user_id = $user_id;     
        $token->token   = uniqid();  //creates a 13 len token  
        $token->type    = $type;

        if( $token->save() )
            return $token;
        else
            return false;
    }

    /**
     * Check token date and generate a new user token if expired, returns a token object
     * @param int $user_id
     * @param string $type
     * @return string
     */
    public static function generateNewTokenIfExpired($user_id, $type)
    {
        //search if a token already exists and delete it.
        $token = self::getTokenByUserAndType($user_id, $type);
        if ($token) {
            //check token 'days passed'
            $days_passed = DateHelper::getTimePassedFromDate($token->created_at);

            if ($days_passed > static::$TOKEN_EXPIRES_THRESHOLD) {
                //if token has expired delete it and generate a new one
                $token->delete();
                $token = self::saveNewToken($user_id, $type);
            }
        }
        else {
            $token = self::saveNewToken($user_id, $type);
        }

        return $token;
    }

    /**
     * Validates user & temp-token data. Input data is encrypted with cryptify lib. Returns decrypted data.
     * DI dependency injector must have cryptify service
     * UserTokens must be set in models
     * @static
     * @param string $encrypted_data
     * @throws \Exception
     * @return array
     */
    public static function handleUserTokenValidation($encrypted_data = null)
    {
        if (is_null($encrypted_data))
            throw new \Exception("sent input null encrypted_data");

        $di   = \Phalcon\DI::getDefault();
        $data = explode("#", $di->getCryptify()->decryptForGetResponse($encrypted_data));

        //validate data (user_id, token_type and token)
        if (count($data) != 3)
            throw new \Exception("decrypted data is not a 2 dimension array.");

        //set vars values
        list($user_id, $token_type, $token) = $data;

        //search for user and token combination
        $token = self::getTokenByUserAndValue($user_id, $token, $token_type);

        if (!$token)
            throw new \Exception("temporal token dont exists.");

        //get days passed
        $days_passed = DateHelper::getTimePassedFromDate($token->created_at);

        if ($days_passed > static::$TOKEN_EXPIRES_THRESHOLD)
            throw new \Exception("temporal token (id: " . $token->id . ") has expired (" . $days_passed . " days passed)");

        return $data;
    }
}
