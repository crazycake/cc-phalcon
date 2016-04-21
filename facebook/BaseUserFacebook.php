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
        $user_class = \CrazyCake\Core\AppCore::getModuleClass("user", false);
        //model relations
        $this->hasOne("user_id", $user_class, "id");
    }

    /**
     * Before Validation Event [onCreate, onUpdate]
     */
    public function beforeValidation()
    {
        $this->created_at = date('Y-m-d H:i:s');
    }

    /** ------------------------------------------- § ------------------------------------------------ **/

}
