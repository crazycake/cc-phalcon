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
     * @param int $user - The user ID
     * @param object $checkout - The checkout object
     */
    abstract public function onSuccessCheckoutTaskComplete($user_id, &$checkout);

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
            "debug"                => false,
            "encrypted_ids"        => false,
            "max_per_item_allowed" => 5,
            "max_user_acquisition" => 10,
            "default_currency"     => "USD"
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

            //get checkout object with parsed data
            $checkout = $this->_parseCheckoutData();

            //call listeners
            $this->onBeforeBuyOrderCreation($checkout);

            //get class
            $user_checkout_class = AppModule::getClass("user_checkout");
            //save checkout detail in DB
            $checkout_orm = $user_checkout_class::newBuyOrder($this->user_session["id"], $checkout);

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
     * Succesful checkout, Called when checkout was made succesfuly (eg: after payment)
     * @param object $checkout - The checkout sesssion object
     * @return object Checkout
     */
    public function successCheckout($checkout)
    {
        //executes another async self request, this time as socket (connection is closed before waiting for response)
        $this->asyncRequest([
            "controller" => "checkout",
            "action"     => "successCheckoutTask",
            "method"     => "post",
            "socket"     => !$this->checkout_manager_conf["debug"], //wait for response debugging
            "payload"    => ["buy_order" => $checkout->buy_order]
        ]);
    }

    /**
     * POST Async checkout action (executes slow tasks)
     * Logic tasks:
     * 1) Update status del checkout
     * 2) Calls purchased object generation logic
     * 3) generates PDF invoice
     * 4) sends the checkout invoice email
     */
    public function successCheckoutTaskAction()
    {
        $data = $this->handleRequest([
            "payload" => "string",
        ], "POST", false);

        try {
            //decrypt data
            $data = $this->cryptify->decryptData($data["payload"], true);

            if (is_null($data) || !isset($data->buy_order))
                throw new Exception("Invalid decrypted data: ".json_encode($data));

            //set classes
            $user_class                 = AppModule::getClass("user");
            $user_checkout_class        = AppModule::getClass("user_checkout");
            $user_checkout_object_class = AppModule::getClass("user_checkout_object");
            $checkout_trx_class         = AppModule::getClass("user_checkout_trx");

            //get checkout, user and event
            $checkout = $user_checkout_class::findFirstByBuyOrder($data->buy_order);
            $user     = $user_class::getById($checkout->user_id);

            //check if data is OK
            if (!$checkout || !$user)
                throw new Exception("Invalid decrypted data, user or checkout not found: ".json_encode($data));

            //reduce object
            $checkout = $checkout->reduce();

            //extended properties
            $checkout->type             = "payment";
            $checkout->amount_formatted = Forms::formatPrice($checkout->amount, $checkout->currency);
            $checkout->objects          = $user_checkout_object_class::getCollection($checkout->buy_order);
            $checkout->categories       = explode(",", $checkout->categories); //set categories as array

            //$this->logger->debug("successCheckoutTask:: before parsing checkout objects: ".print_r($checkout, true));

            //1) update status of checkout
            $user_checkout_class::updateState($checkout->buy_order, "success");

            //2) set checkout object classes
            $checkout->objects_classes = [];

            foreach ($checkout->objects as $obj) {

                //only once
                if (in_array($obj->object_class, $checkout->objects_classes))
                    continue;

                //save object class
                array_push($checkout->objects_classes, $obj->object_class);

                //call object class listener
                $this->logger->debug("CheckoutManager::calling ".$obj->object_class."Controller::onSuccessCheckout");

                if (method_exists($obj->object_class."Controller", "onSuccessCheckout")) {

                    $class_name = $obj->object_class."Controller";

                    (new $class_name())->onSuccessCheckout($user->id, $checkout);
                }
                else {
                    $this->logger->debug("CheckoutManager::onSuccessCheckout, missing onSuccessCheckout fn on class: ".$obj->object_class);
                }
            }

            //3) set checkout trx object
            $trx = $checkout_trx_class::findFirstByBuyOrder($checkout->buy_order);
            $checkout->trx = $trx ? $trx->reduce() : null;

            if ($this->checkout_manager_conf["debug"])
                $this->logger->debug("Checkout task complete: ".json_encode($checkout));

            //3) Call listener
            $this->onSuccessCheckoutTaskComplete($user->id, $checkout);
        }
        catch (Exception $e) {
            //get mailer controller
            $mailer = AppModule::getClass("mailer_controller");
            //send alert system mail message
            (new $mailer())->adminException($e, [
                "action"  => "successCheckoutTask",
                "user_id" => (isset($user) ? $user->id : "unknown")
            ]);
        }
        finally {
            //send OK response
            $this->jsonResponse(200);
        }
    }

    /**
     * Failed checkout, marks checkout as failed and deletes saved cache
     * @static
     * @param object $checkout - The checkout object
     * @return boolean
     */
    public function failedCheckout($checkout = false)
    {
        //get module class name
        $user_checkout_class = AppModule::getClass("user_checkout");

        if (!$checkout || !isset($checkout->buy_order))
            return false;

        //get ORM object and update status of checkout
        $checkout_orm = $user_checkout_class::findFirstByBuyOrder($checkout->buy_order);

        if (!$checkout_orm)
            return false;

        if ($checkout_orm->state == "pending")
            $user_checkout_class::updateState($checkout->buy_order, "failed");

        return true;
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

                $this->logger->error("CheckoutManager::_parseCheckoutObjects -> No stock for object $object_class, ID: $object_id, Q: $q.");
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
     * Parses the checkout POST params
     * @return object
     */
    private function _parseCheckoutData()
    {
        //get form data
        $data = $this->handleRequest([
            "gateway"        => "string",  //checkout payment gateway
            "@categories"    => "array",   //the categories references
            "@invoice_email" => "string"   //optional, custom validation
        ], "POST");

        //check invoice email if set
        if (!empty($data["invoice_email"]) && !filter_var($data["invoice_email"], FILTER_VALIDATE_EMAIL))
            throw new Exception($this->checkout_manager_conf["trans"]["ERROR_INVOICE_EMAIL"]);

        //lower case email
        $data["invoice_email"] = strtolower($data["invoice_email"]);

        //set object properties. TODO: create a object class
        $checkout = (object)[
            "client"        => json_encode($this->client, JSON_UNESCAPED_SLASHES),
            "categories"    => empty($data["categories"]) ? [] : explode(",", $data["categories"]),
            "gateway"       => $data["gateway"],
            "invoice_email" => $data["invoice_email"],
            "currency"      => $this->checkout_manager_conf["default_currency"]
        ];

        //parse checkout objects
        $this->parseCheckoutObjects($checkout, $data);
        //print_r($checkout);exit;

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
