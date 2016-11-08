<?php
/**
 * Checkout Trait
 * This class has common actions for checkout controllers
 * Requires a Frontend or Backend Module with CoreController and SessionTrait
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Checkout;

//imports
use Phalcon\Exception;
//core
use CrazyCake\Phalcon\AppModule;
use CrazyCake\Helpers\Forms;

/**
 * Checkout Manager
 */
trait CheckoutManager
{
    /**
     * Listener - Before Inster a new Buy Order record
     * @param object $checkout - The checkout object
     */
    abstract public function onBeforeBuyOrderCreation(&$checkout);

    /**
     * Listener - Success checkout Task completed
     * @param object $checkout - The checkout object
     */
    abstract public function onSuccessCheckout(&$checkout);

    /**
     * Config var
     * @var array
     */
    public $checkout_manager_conf;

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * This method must be call in constructor parent class.
     * TODO: implement defaults conf values.
     * @param array $conf - The config array
     */
    public function initCheckoutManager($conf = [])
    {
        $defaults = [
            "encrypted_ids"        => false,
            "max_per_item_allowed" => 5,
            "default_currency"     => "CLP"
        ];

        $this->checkout_manager_conf = array_merge($defaults, $conf);
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Ajax - Before user goes to payment gateway (or not), buy order must be generated.
     */
    public function buyOrderAction()
    {
        //make sure is ajax request
        $this->onlyAjax();

        try {
            //get class
            $user_checkout_class = AppModule::getClass("user_checkout");

            //get checkout object with parsed data
            $checkout = $this->setCheckoutObject();

            //call listeners
            $this->onBeforeBuyOrderCreation($checkout);

            $checkout_orm = $user_checkout_class::newBuyOrder($checkout);

            //check if an error occurred
            if (!$checkout_orm)
                throw new Exception($this->checkout_manager_conf["trans"]["ERROR_UNEXPECTED"]);

            //set buy order
            $checkout->buy_order = $checkout_orm->buy_order;

            //send JSON response
            return $this->jsonResponse(200, $checkout);
        }
        catch (Exception $e)  { $exception = $e->getMessage(); }
        catch (\Exception $e) { $exception = $e->getMessage(); }
        //sends an error message
        $this->jsonResponse(200, $exception, "alert");
    }

    /**
     * Succesful checkout, Called when checkout was made succesfuly
     * @param string $buy_order - The buy order
     * @return object Checkout
     */
    public function successCheckout($buy_order = "")
    {
        //triggers async request
        $this->asyncRequest([
            "controller" => "checkout",
            "action"     => "successCheckoutTask",
            "method"     => "post",
            "socket"     => true,
            "payload"    => ["buy_order" => $buy_order]
        ]);
    }

    /**
     * POST Async checkout action
     * Logic tasks:
     * 1) Update status del checkout
     * 2) Calls listener
     */
    public function successCheckoutTaskAction()
    {
        try {

			//get post params
			$data = $this->handleRequest([
	            "payload" => "string",
	        ], "POST", false);

            //decrypt data
            $data = $this->cryptify->decryptData($data["payload"], true);

            if (is_null($data) || !isset($data->buy_order))
                throw new Exception("Invalid decrypted data: ".json_encode($data));

            //set classes
            $user_class                 = AppModule::getClass("user");
            $user_checkout_class        = AppModule::getClass("user_checkout");
            $user_checkout_object_class = AppModule::getClass("user_checkout_object");

            //get checkout & user
            $checkout = $user_checkout_class::findFirstByBuyOrder($data->buy_order);
            $user     = $user_class::getById($checkout->user_id);

            //check if data is OK
            if (!$checkout || !$user)
                throw new Exception("Invalid decrypted data, user or checkout not found: ".json_encode($data));

            //reduce ORM to simple object
            $checkout = $checkout->reduce();

            //extended properties
            $checkout->amount_formatted = Forms::formatPrice($checkout->amount, $checkout->currency);
			//set objects
            $checkout->objects = $user_checkout_object_class::getCollection($checkout->buy_order);

            //1) update status of checkout
            $user_checkout_class::updateState($checkout->buy_order, "success");

            //2) Call listener
            $this->onSuccessCheckout($checkout);
        }
        catch (Exception $e) {
            //get mailer controller
            $mailer = AppModule::getClass("mailer_controller");
            //send alert system mail message
            (new $mailer())->adminException($e, [
                "action" => "successCheckoutTask",
                "data"   => json_encode($data, JSON_UNESCAPED_SLASHES)
            ]);
        }
        finally {
            //send OK response
            $this->jsonResponse(200);
        }
    }

    /**
     * Parses objects checkout & set new props by reference
     * @param object $checkout - The checkout object
     * @param array $data - The received form data
     */
    public function parseCheckoutObjects(&$checkout = null, $data = [])
    {
        if (empty($checkout) || empty($data))
            return;

        if (empty($checkout->objects));
            $checkout->objects = [];

        if (empty($checkout->amount))
            $checkout->amount = 0;

        //get module class name
        $user_checkout_object_class = AppModule::getClass("user_checkout_object");

        //computed vars
        $classes = empty($checkout->objects_classes) ? [] : $checkout->objects_classes;
        $total_q = empty($checkout->total_q) ? 0 : $checkout->total_q;

        //loop throught checkout items
        foreach ($data as $key => $q) {

            //parse properties
            $props = explode("_", $key);

            //validates checkout data has defined prefix
            if (strpos($key, "Checkout_") === false || count($props) != 3 || empty($q))
                continue;

            //get object props
            $object_class = $props[1];
            $object_id    = $props[2];

            if($this->checkout_manager_conf["encrypted_ids"]) {
                $object_id = $this->cryptify->decryptHashId($object_id);
            }

            $preffixed_object_class = "\\$object_class";
            $object = $preffixed_object_class::getById($object_id);
            //var_dump($object_class, $object_id, $object->toArray());exit;

            //check that object is in stock (also validates object exists)
            if (!$user_checkout_object_class::validateStock($object_class, $object_id, $q)) {

                $this->logger->error("CheckoutManager::parseCheckoutObjects -> No stock for object $object_class, ID: $object_id, Q: $q.");
                throw new Exception(str_replace("{name}", $object->name, $this->checkout_manager_conf["trans"]["ERROR_NO_STOCK"]));
            }

            //append object class
            if (!in_array($object_class, $classes))
                array_push($classes, $object_class);

            //update total Q
            $total_q += $q;

            //update amount
            $checkout->amount += $q * $object->price;

            //create new checkout object without ORM props
            $checkout_object = (new $user_checkout_object_class())->reduce();
            //props
            $checkout_object->object_class = $object_class;
            $checkout_object->object_id    = $object_id;
            $checkout_object->quantity     = $q;

            //set item in array as string or plain object
            $checkout->objects[] = $checkout_object;
        }

        //set objectsClassName
        $checkout->objects_classes = $classes;
        //update total Q
        $checkout->total_q = $total_q;
    }
    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Set checkout object
     * @return object
     */
    private function setCheckoutObject()
    {
        //get form data
        $data = $this->handleRequest([
            "gateway"   => "string",
            "@currency" => "string",
        ], "POST");

        if(empty($data["currency"]))
            $data["currency"] = $this->checkout_manager_conf["default_currency"];

        //check user_id
        $user_id = empty($this->user_session["id"]) ? null : $this->user_session["id"];

        //create checkout object
        $checkout = (object)[
            "user_id"  => $user_id,
            "client"   => json_encode($this->client, JSON_UNESCAPED_SLASHES),
            "gateway"  => $data["gateway"],
            "currency" => $data["currency"]
        ];

        //parse checkout objects
        $this->parseCheckoutObjects($checkout, $data);
        //sd($checkout);

        //weird error, no checkout objects
        if (empty($checkout->objects))
            throw new Exception($this->checkout_manager_conf["trans"]["ERROR_UNEXPECTED"]);

        //check max objects allowed
        if ($checkout->total_q > $this->checkout_manager_conf["max_user_acquisition"]) {

            throw new Exception(str_replace("{num}", $this->checkout_manager_conf["max_user_acquisition"],
                                                     $this->checkout_manager_conf["trans"]["ERROR_MAX_TOTAL"]));
        }

        return $checkout;
    }
}
