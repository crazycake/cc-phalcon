<?php
/**
 * Base Model Users Checkouts Objects
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Models;

//imports
use CrazyCake\Utils\FormHelper;

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
           array('buy_order' => $buy_order)
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
           $new_object = new \stdClass();
           //merge common props
           $new_object->id        = $obj->object_id;
           $new_object->className = $object_class;
           $new_object->quantity  = $obj->quantity;

           //select object props
           $props = $object_class::findFirst(array("id ='".$obj->object_id."'"));

            if(!$props) {
                continue;
            }

           //object props
           $new_object->name  = $props->name;
           $new_object->price = $props->price;
           $new_object->coin  = $props->coin;
           //aditional props
           $new_object->formattedPrice = FormHelper::formatPrice($props->price, $props->coin);
           $new_object->formattedTotal = FormHelper::formatPrice($props->price * $obj->quantity, $props->coin);

           array_push($result, $new_object);
       }

       return $result;
    }
}
