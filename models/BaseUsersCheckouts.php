<?php
/**
 * Base Model Users Checkouts
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Models;

class BaseUsersCheckouts extends Base
{
    /* properties */

    /**
     * @var int
     */
    public $user_id;

    /**
     * @var string
     */
    public $object_class;

    /**
     * @var string
     */
    public $object_id;

    /**
     * @var int
     */
    public $quantity;

    /**
     * @var string
     */
    public $buy_order;

    /**
     * @var string
     */
    public $created_at;

    /** -------------------------------------------- § -------------------------------------------------
        Init
    ------------------------------------------------------------------------------------------------- **/
    public function initialize()
    {
        //Skips fields/columns on both INSERT/UPDATE operations
        $this->skipAttributes(array('created_at'));
    }
    /** ------------------------------------------- § ------------------------------------------------ **/

    /**
     * Finds an object
     * @static
     * @param int $user_id
     * @param string $object_class
     * @param int $object_id
     * @return object
     */
    public static function getObject($user_id, $object_class, $object_id)
    {
        $conditions = "user_id = ?1 AND $object_class = ?2 AND $object_id = ?3";
        $parameters = array(1 => $user_id, 2 => $object_class, 3 => $object_id);

        return self::findFirst( array($conditions, "bind" => $parameters) );
    }

    /**
     * Gets object quantity
     * @static
     * @param int $user_id
     * @param string $object_class
     * @param int $object_id
     * @return int
     */
    public static function getObjectQuantity($user_id, $object_class, $object_id)
    {
        $object = self::getObject($user_id, $object_class, $object_id);

        return $object ? $object->quantity : 0;
    }
}
