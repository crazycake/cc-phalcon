<?php
/**
 * Base Model Users Checkouts Objects
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Models;

class BaseUsersCheckoutsObjects extends Base
{
    /* properties */

    /*
     * @var string
     */
    public $buy_order;

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

    /** -------------------------------------------- § -------------------------------------------------
        Init
    ------------------------------------------------------------------------------------------------- **/
    public function initialize()
    {

    }
    /** ------------------------------------------- § ------------------------------------------------ **/

    /**
     * Get checkout objects
     * @param  string $buy_order Checkout buyOrder
     * @return array
     */
    public static function getCheckoutObjects($buy_order)
    {
        $objectsModel = static::who();

        //get checkout objects
        $objects = self::getObjectsByPhql(
           //phql
           "SELECT object_class, object_id, quantity
            FROM $objectsModel
            WHERE buy_order = :buy_order:
            ",
           //bindings
           array('buy_order' => $buy_order)
       );

       foreach ($objects as $obj) {

           $object_class = $obj->object_class;

           //select object props
           $props = $object_class::findFirst(array("id ='".$obj->object_id."'"));

           print_r($props->toArray());exit;
           //set object properties as array
           $obj->props = $props;
       }

       return $objects;
    }
}
