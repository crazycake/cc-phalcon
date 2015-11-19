<?php
/**
 * Base Model Users Checkouts Objects
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Checkout;

//imports
use CrazyCake\Utils\FormHelper;

/**
 * Base User Checkouts objects
 */
class BaseUsersCheckoutsObjects extends \CrazyCake\Models\Base
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

    /** ------------------------------------------- § ------------------------------------------------ **/

    /**
     * Get checkout objects
     * @param  string $buy_order Checkout buyOrder
     * @param  boolean $ids Flag optional to get an array of object IDs
     * @return array
     */
    public static function getCheckoutObjects($buy_order, $ids = false)
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
           ['buy_order' => $buy_order]
       );

       $result = array();

       //loop through objects
       foreach ($objects as $obj) {

            $object_class = $obj->object_class;

            //filter only to ids?
            if($ids) {
               array_push($result, $obj->object_id);
               continue;
            }

            //create a new object and clone common props
            $new_object = self::newCheckoutObject($obj->object_id, $object_class, $obj->quantity);

            //get object local props
            $props = $object_class::findFirst(["id ='".$obj->object_id."'"]);

            if(!$props) continue;

            //extend custom flexible properties
            $new_object->name  = isset($props->name) ? $props->name : $props->_ext["name"];

            //extedend common object props
            $new_object->price = $props->price;
            $new_object->coin  = $props->coin;

            //UI props
            $new_object->formattedPrice = FormHelper::formatPrice($props->price, $props->coin);
            $new_object->formattedTotal = FormHelper::formatPrice($props->price * $obj->quantity, $props->coin);

            array_push($result, $new_object);
       }

       return $result;
    }

    /**
     * Returns a new instance of a simple checkout object
     * @return stdClass object
     */
    public static function newCheckoutObject($id = null, $className = "CheckoutObject", $quantity = 1)
    {
        $new_object = new \stdClass();

        $new_object->id        = $id;
        $new_object->className = $className;
        $new_object->quantity  = $quantity;

        return $new_object;
    }
}
