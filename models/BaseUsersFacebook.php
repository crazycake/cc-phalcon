<?php
/**
 * Base Model Users Facebook
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Models;

//imports
//...

class BaseUsersFacebook extends Base
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

    /** -------------------------------------------- § -------------------------------------------------
        Init
    ------------------------------------------------------------------------------------------------- **/
    public function initialize()
    {
        //model relations
        $this->hasOne("user_id", "Users", "id");

        //Skips fields/columns on both INSERT/UPDATE operations
        $this->skipAttributes( array('created_at') );
    }
    /** ------------------------------------------- § ------------------------------------------------ **/

    /**
     * Get facebook data by user_id
     * @static
     * @param int $user_fb_id
     * @return UsersFacebook
     */
    public static function getFacebookDataByUserId($user_id)
    {
        return self::findFirst("user_id = ".$user_id);
    }
}
