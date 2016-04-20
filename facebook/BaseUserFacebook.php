<?php
/**
 * Base Model Users Facebook
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Facebook;

/**
 * Base Model Users Facebook
 */
class BaseUserFacebook extends \CrazyCake\Models\Base
{
    /* properties */

    /**
     * @var int
     */
    public $user_id;

    /**
     * facebook access token
     * @var string
     */
    public $fac;

    /**
     * access token expiration
     * @var string
     */
    public $expires_at;

    /**
     * @var string (timestamp)
     */
    public $created_at;

    /**
     * Initializer
     */
    public function initialize()
    {
        //get class
        $users_class = \CrazyCake\Core\AppCore::getModuleClass("users", false);
        //model relations
        $this->hasOne("user_id", $users_class, "id");
    }

    /**
     * Before Validation Event [onCreate, onUpdate]
     */
    public function beforeValidation()
    {
        $this->created_at = date('Y-m-d H:i:s');
    }

    /** ------------------------------------------- § ------------------------------------------------ **/

    /**
     * Gets facebook data by user_id
     * @static
     * @param int $user_id - The user ID
     * @return UsersFacebook
     */
    public static function getFacebookDataByUserId($user_id)
    {
        return self::findFirst(["user_id = ?1", "bind" => [1 => $user_id]]);
    }
}
