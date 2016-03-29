<?php
/**
 * Base Model Users Checkouts Objects
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Checkout;

//imports
use CrazyCake\Helpers\FormHelper;

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
     * @param  string $buy_order - Checkout buyOrder
     * @param  boolean $ids - Flag optional to get an array of object IDs
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
            $props = $object_class::findFirst(["id = ?1", "bind" => [1 => $obj->object_id]]);

            if(!$props) continue;

            //extend custom flexible properties
            $new_object->name = isset($props->name) ? $props->name : $props->_ext["name"];

            //extedend common object props
            $new_object->price = $props->price;
            $new_object->currency = $props->currency;

            //UI props
            $new_object->formattedPrice = FormHelper::formatPrice($props->price, $props->currency);
            $new_object->formattedTotal = FormHelper::formatPrice($props->price * $obj->quantity, $props->currency);

            array_push($result, $new_object);
       }

       return $result;
    }

    /**
     * Returns a new instance of a simple checkout object
     * @param int $id - The object ID
     * @param string $className - The object class name
     * @param int $quantity - The object quantity
     * @return object
     */
    public static function newCheckoutObject($id = 0, $className = "CheckoutObject", $quantity = 1)
    {
        return (object)[
            "id"        => $id,
            "className" => $className,
            "quantity"  => $quantity
        ];
    }

    /**
     * Validates that checkout object is already in stock.
     * Sums to q the number of checkout object presents in a pending checkout state.
     * @param string $object_class - The object class
     * @param int $object_id - The object id
     * @param int $q - The quantity to validate
     * @return boolean
     */
    public static function validateObjectStock($object_class = "", $object_id = 0, $q = 0)
    {
        if(!class_exists($object_class))
            throw new Exception("BaseUsersCheckoutsObjects -> Object class not found ($object_class)");

        $object = $object_class::getObjectById($object_id);

        if(!$object)
            return false;

        //get classes
        $checkoutModel = \CrazyCake\Core\AppCore::getModuleClass("users_checkouts");
        //get checkouts objects class
        $objectsModel = static::who();

        //get pending checkouts items quantity
        $objects = $checkoutModel::getObjectsByPhql(
           //phql
           "SELECT SUM(quantity) AS q
            FROM $objectsModel AS objects
            INNER JOIN $checkoutModel AS checkout ON checkout.buy_order = objects.buy_order
            WHERE objects.object_id = :object_id:
                AND objects.object_class = :object_class:
                AND checkout.state = 'pending'
            ",
           //bindings
           ['object_id' => $object_id, "object_class" => $object_class]
       );
       //get sum quantity
       $checkout_q = $objects->getFirst()->q;

        if(is_null($checkout_q))
            $checkout_q = 0;

        //substract total
        $total = $object->quantity - $checkout_q;
        //var_dump($total, $object->quantity, $checkout_q, $total);exit;

        if($total <= 0)
            return false;

       return ($total >= $q) ? true : false;
    }

    /**
     * Substract Checkout objects quantity for processed checkouts
     * @param array $objects - The checkout objects array (getCheckoutObjects returned array)
     */
    public static function substractObjectsStock($objects)
    {
        //loop throught items and substract Q
        foreach ($objects as $obj) {

            if(empty($obj->quantity))
                continue;

            $object_class = $obj->className;

            $orm_object       = $object_class::findFirst(["id = ?1", "bind" => [1 => $obj->id]]);
            $current_quantity = $orm_object->quantity;
            $updated_quantity = (int)($current_quantity - $obj->quantity);

            $state = $orm_object->state;

            if($updated_quantity <= 0) {
                $updated_quantity = 0;
                //check state and update if stocked output
                if($orm_object->state == "open")
                    $state = "soldout";
            }

            //update record throught query (safer than ORM)
            self::executePhql(
                "UPDATE $object_class
                    SET quantity = ?1, state = ?2
                    WHERE id = ?0
                ",
                [$orm_object->id, $updated_quantity, $state]
            );
        }
    }

    /**
     * Get checkout buyOrders by given objects
     * @param int $user_id - The user ID
     * @param string $state - The checkout state, default is 'success'
     * @param array $object_ids - An array with object IDs (required)
     * @param string $object_class - The object class name (required)
     * @return array
     */
    public static function getBuyOrdersByObjectsIds($user_id, $state = "success", $object_ids = array(), $object_class = "")
    {
        if(!class_exists($object_class))
            throw new Exception("BaseUsersCheckouts -> Object class not found ($object_class)");

        if(empty($object_ids))
            return array();

        //get classes
        $checkoutModel = \CrazyCake\Core\AppCore::getModuleClass("users_checkouts");
        //get checkouts objects class
        $objectsModel = static::who();

        $conditions = "";

        foreach ($object_ids as $key => $id)
            $object_ids[$key] = "objects.object_id = '".(int)$id."'"; //prevent string injections

        $ids_filter = implode(" OR ", $object_ids);
        $conditions .= " AND (".$ids_filter.") ";

        //result
        $result = $checkoutModel::getObjectsByPhql(
           //phql
           "SELECT objects.buy_order
            FROM $objectsModel AS objects
            INNER JOIN $checkoutModel AS checkout ON checkout.buy_order = objects.buy_order
            WHERE checkout.user_id IS NOT NULL
                AND checkout.user_id = :user_id:
                AND checkout.state = :state:
                AND objects.object_class = :object_class:
                $conditions
            ",
           //bindings
           ["user_id" => $user_id, "state" => $state, "object_class" => $object_class]
       );

        if(!$result)
            return false;

        return \CrazyCake\Models\BaseResultset::getIdsArray($result, "buy_order");
    }
}
