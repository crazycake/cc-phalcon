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
//other imports
use CrazyCake\Utils\FormHelper;

/**
 * Checkout Manager
 */
trait CheckoutManager
{
    /**
     * Set Trait configurations
     */
    abstract public function setConfigurations();

    /**
     * Listener - Before Inster a new Buy Order record
     * @param object $checkout - The checkout object
     */
    abstract public function onBeforeBuyOrderCreation(&$checkout);

    /**
     * Listener - Success checkout Task completed
     * @param object $user - The ORM user object
     * @param object $checkout - The checkout object
     */
    abstract public function onSuccessCheckoutTaskComplete($user, &$checkout);

    /**
     * Listener - On skipped payment [testing]
     * @param object $checkout - The checkout object
     */
    abstract public function onSkippedPayment(&$checkout);


    /**
     * Config var
     * @var array
     */
    public $checkoutConfig;

    /**
     * Initializer
     */
    protected function initialize()
    {
        parent::initialize();

        //handle loggedIn exception
        if($this->router->getActionName() == "successCheckoutTask")
            return;

        //if not logged in
        if(!$this->_checkUserIsLoggedIn()) {

            //set a flash message for non authenticated users
            $this->flash->notice($this->checkoutConfig["trans"]["notice_auth"]);
            //if not logged In, set this URI to redirected after logIn
            $this->_setSessionRedirectionOnLoggedIn();
        }

        //handle response, dispatch to auth/logout
        $this->_checkUserIsLoggedIn(true);
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Ajax - Before user goes to payment gateway (or not), buy order must be generated.
     */
    public function buyOrderAction()
    {
        //make sure is ajax request
        $this->_onlyAjax();

        try {

            //get checkout object with parsed data
            $checkout = $this->_parseCheckoutData();

            //call listeners
            $this->onBeforeBuyOrderCreation($checkout);

            //get class
            $users_checkouts_class = $this->_getModuleClass('users_checkouts');
            //save checkout detail in DB
            $checkoutOrm = $users_checkouts_class::newBuyOrder($this->user_session["id"], $checkout);

            //check if an error occurred
            if(!$checkoutOrm)
                throw new Exception($this->checkoutConfig["trans"]["error_unexpected"]);

            //set buy order
            $checkout->buy_order = $checkoutOrm->buy_order;

            //send JSON response
            return $this->_sendJsonResponse(200, $checkout);
        }
        catch (Exception $e)  { $exception = $e->getMessage(); }
        catch (\Exception $e) { $exception = $e->getMessage(); }
        //sends an error message
        $this->_sendJsonResponse(200, $exception, 'alert');
    }

    /**
     * Ajax - process a free or invitation type event
     */
    public function processAction()
    {
        //make sure is ajax request
        $this->_onlyAjax();
        //get form data
        $data = $this->_handleRequestParams([
            "buy_order" => "string"
        ]);

        try {
            //get class
            $users_checkouts_class = $this->_getModuleClass('users_checkouts');

            //instance cache lib and get data
            $checkout = $users_checkouts_class::getCheckout($buy_order);

            if(!$checkout)
                throw new Exception($this->checkoutConfig["trans"]["error_unexpected"]);

            //check buy orders
            if($data["buy_order"] != $checkout->buy_order)
                throw new Exception($this->checkoutConfig["trans"]["error_unexpected"]);

            //set flash message
            $this->flash->success($this->checkoutConfig["trans"]["success_checkout"]);
            //call succes checkout
            $this->successCheckout($checkout);
        }
        catch (Exception $e) {
            //sends an error message
            $this->_sendJsonResponse(200, $e->getMessage(), 'alert');
        }
    }

    /**
     * Succesful checkout, Called when checkout was made succesfuly (eg: after payment)
     * @param object $checkout - The checkout sesssion object
     * @param boolean $async - If false wait for response
     * @return object Checkout
     */
    public function successCheckout($checkout, $async = true)
    {
        //executes another async self request, this time as socket (connection is closed before waiting for response)
        $this->_asyncRequest(
            ["checkout" => "successCheckoutTask"],
            //encrypted data
            ["buy_order" => $checkout->buy_order],
            //method
            "POST",
            //socket async
            $async
        );
    }

    /**
     * POST Async checkout action (executes slow tasks)
     * Logic tasks:
     * 1) Update status del checkout
     * 2) Calls user event tickets generation logic
     * 3) generates PDF invoice
     * 4) sends the checkout invoice email
     */
    public function successCheckoutTaskAction()
    {
        $data = $this->_handleRequestParams([
            'payload' => 'string',
        ], 'POST', false);

        try {
            //decrypt data
            $data = $this->cryptify->decryptForGetResponse($data["payload"], true);

            if(is_null($data) || !isset($data->buy_order))
                throw new Exception("Invalid decrypted data: ".json_encode($data));

            //set classes
            $users_class                   = $this->_getModuleClass('users');
            $users_checkouts_class         = $this->_getModuleClass('users_checkouts');
            $users_checkouts_objects_class = $this->_getModuleClass('users_checkouts_objects');
            $checkout_trx_class            = $this->_getModuleClass('users_checkouts_trx');

            //get checkout, user and event
            $checkout = $users_checkouts_class::getCheckout($data->buy_order);
            $user     = $users_class::getObjectById($checkout->user_id);

            //check if data is OK
            if(!$checkout || !$user)
                throw new Exception("Invalid decrypted data, user or checkout not found: ".json_encode($data));

            //reduce object
            $checkout = $checkout->reduce();

            //extended properties
            $checkout->type            = "payment";
            $checkout->amountFormatted = FormHelper::formatPrice($checkout->amount, $checkout->coin);
            $checkout->objects         = $users_checkouts_objects_class::getCheckoutObjects($checkout->buy_order);
            $checkout->categories      = explode(",", $checkout->categories); //set categories as array

            //1) update status of checkout
            $users_checkouts_class::updateState($checkout->buy_order, 'success');

            //2) set checkout object classes
            $checkout->objectsClasses = [];
            foreach ($checkout->objects as $obj) {

                if(in_array($obj->className, $checkout->objectsClasses))
                    continue;

                //save object class
                array_push($checkout->objectsClasses, $obj->className);

                //call object class listener
                if(method_exists($obj->className."Controller", "onCheckoutSuccess")) {
                    $className = $obj->className."Controller";
                    (new $className())->onCheckoutSuccess($user->id, $checkout);
                }
            }

            //3) set checkout trx object
            $trx = $checkout_trx_class::findFirstByBuyOrder($checkout->buy_order);
            $checkout->trx = $trx ? $trx->reduce() : null;

            //$this->logger->debug("Checkout task complete: ".print_r($checkout, true));

            //3) Call listener
            $this->onSuccessCheckoutTaskComplete($user, $checkout);
        }
        catch(Exception $e) {
            //get mailer controller
            $mailer = $this->_getModuleClass('mailer_controller');
            //send alert system mail message
            (new $mailer())->sendSystemMailForException($e, [
                "action"  => "successCheckoutTask",
                "user_id" => (isset($user) ? $user->id : "unknown")
            ]);
        }
        finally {
            //send OK response
            $this->_sendJsonResponse(200);
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
        $users_checkouts_class = $this->_getModuleClass('users_checkouts');

        if(!$checkout || !isset($checkout->buy_order))
            return false;

        //get ORM object and update status of checkout
        $checkoutOrm = $users_checkouts_class::getCheckout($checkout->buy_order);

        if(!$checkoutOrm)
            return false;

        if($checkoutOrm->state == "pending")
            $users_checkouts_class::updateState($checkout->buy_order, 'failed');

        return true;
    }

    /**
     * Skips payment, simulates success checkout
     * Metodo parche para crear entradas a partir de un checkout generado (se salta el pago)
     * La transaccion es opcional
     * @param string $code - The security code
     * @return SQL statements
     */
    public function skipPaymentAction($code = "")
    {
        //get module class name
        $users_checkouts_class = $this->_getModuleClass('users_checkouts');

        //instance cache lib and get data
        $checkout = $users_checkouts_class::getLastUserCheckout($this->user_session["id"]);

        if(!$checkout || $checkout->state != "pending")
            die("No pending checkout found for user id:".$this->user_session["id"]);

        //basic security
        if(APP_ENVIRONMENT !== 'local' && $code !== sha1($checkout->buy_order))
            $this->_redirectToNotFound();

        //append custom comment
        $this->onSkippedPayment($checkout, $this->checkoutConfig["debug"]);

        //log
        $this->logger->debug("CheckoutManager::skipPaymentAction -> Skipped payment for userId: ".$checkout->user_id.", BO: ".$checkout->buy_order);
        //call succes checkout
        $this->successCheckout($checkout);

        //set flash message
        $this->flash->success($this->checkoutConfig["trans"]["success_checkout"]);

        //redirect
        if(!$this->checkoutConfig["debug"])
            $this->_redirectTo("account");
        else
            echo "CheckoutConfig Debug Mode On...";
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Loads common setup for checkout view.
     * This are used for HTML bindings
     * @param array $categories - The categories array
     * @param string $checkoutType - The checkout type, example: paid, free, etc.
     * @param object $user - The user object
     * @param array $objects - The checkout objects array
     * @param array $objectsClasses - The checkout objects classes name
     * @param object $view - The checkout view class
     */
    private function _setupCheckoutView($categories = array(), $checkoutType = "", $user = null, $objects = array(), $objectsClasses = array(), $view = "default")
    {
        //default inputs for checkout
        $inputs = [
            "gateway"    => "",                       //checkout gateway name
            "categories" => implode(",", $categories) //checkout categories as string
        ];

        //get module class name
        $users_checkouts_class = $this->_getModuleClass('users_checkouts');

        //set default max checkout number
        $checkoutMax = 1;

        //increase max item allowed
        if($checkoutType == "paid") {
            $checkoutMax = $this->checkoutConfig["max_per_item_allowed"];
        }

        //check for last used invoice email
        $lastCheckout = $users_checkouts_class::findFirst([
            "user_id = ?0",
            "order" => "local_time DESC",
            "bind"  => [$user->id]
        ]);
        $lastInvoiceEmail = $lastCheckout ? $lastCheckout->invoice_email : "";

        //pass data to view
        $this->view->setVars([
            "objectsClasses"       => $objectsClasses,
            "invoiceEmail"         => !empty($lastInvoiceEmail) ? $lastInvoiceEmail : $user->email,
            "checkoutInputs"       => $inputs,
            "checkoutInputsPrefix" => "checkout_"
        ]);

        //prepare tickets JS
        $objectsJs = is_array($objects) ? $objects : [];

        if(empty($objectsJs)) {
            foreach ($objects as $obj)
                $objectsJs[] = $obj->toArray($this->checkoutConfig["object_properties"]);
        }

        //load JS modules
        $js_modules = $this->checkoutConfig["js_modules"];

        $this->_loadJavascriptModules(array_merge([
            "$js_modules[0]" => [
                "checkoutType"   => $checkoutType,
                "checkoutMax"    => $checkoutMax,
                "objects"        => $objectsJs,
                "objectsClasses" => $objectsClasses
            ]
        ],
            count($js_modules) > 1 ? array_slice($js_modules, 1) : []
        ));

        //pick view
        $this->view->pick("checkout/$view");
    }

    /**
     * Parses the checkout POST params
     * @return object
     */
    private function _parseCheckoutData()
    {
        //get form data
        $data = $this->_handleRequestParams([
            "gateway"       => "string",  //checkout payment gateway
            "categories"    => "array",   //the categories references
            "@invoiceEmail" => "string"   //optional, custom validation
        ]);

        //get module class name
        $users_checkouts_class = $this->_getModuleClass('users_checkouts');

        //check invoice email if set
        if(!isset($data["invoiceEmail"]) || !filter_var($data['invoiceEmail'], FILTER_VALIDATE_EMAIL))
            throw new Exception($this->MSGS["ERROR_INVOICE_EMAIL"]);

        //lower case email
        $data["invoiceEmail"] = strtolower($data["invoiceEmail"]);
        //set client object extended properties
        $this->client->baseUrl = $this->_baseUrl();

        //set object properties
        $checkout = new \stdClass();

        $checkout->client        = json_encode($this->client, JSON_UNESCAPED_SLASHES);
        $checkout->categories    = explode(",", $data["categories"]);
        $checkout->gateway       = $data["gateway"];
        $checkout->invoice_email = $data["invoiceEmail"];
        $checkout->coin          = $this->checkoutConfig["default_coin"];
        $checkout->objects       = [];
        $checkout->amount        = 0;

        //temp vars
        $totalQ  = 0;
        $classes = [];

        //loop throught checkout items
        foreach ($data as $key => $q) {

            //parse properties
            $props = explode("_", $key);

            //validates checkout data has defined prefix
            if(strpos($key, "checkout_") === false || count($props) != 3 || empty($q))
                continue;

            //get object props
            $class_name = $props[1];
            $object_id  = $this->cryptify->decryptHashId($props[2]);

            $preffixed_object_class = "\\$class_name";
            $object = $preffixed_object_class::getObjectById($object_id);
            //var_dump($class_name, $object_id, $object->toArray());exit;

            //check that object is in stock (also validates object exists)
            if(!$users_checkouts_class::validateObjectStock($class_name, $object_id, $q)) {
                $this->logger->error("CheckoutManager::_parseCheckoutObjects -> No stock for object '$class_name' ID: $object_id, q: $q.");
                throw new Exception(str_replace("{name}", $object->name, $this->checkoutConfig["trans"]["error_no_stock"]));
            }

            //append object class
            if(!in_array($class_name, $classes))
                array_push($classes, $class_name);

            //update amount
            $checkout->amount += $q * $object->price;
            //set item in array
            $checkout->objects[$class_name."_".$object_id] = $q;
            //update total Q
            $totalQ += $q;
        }
        //print_r($checkout);exit;

        //weird error, no checkout objects
        if(empty($checkout->objects))
            throw new Exception($this->checkoutConfig["trans"]["error_unexpected"]);

        //check max objects allowed
        if($totalQ > $this->checkoutConfig["max_user_acquisition"])
            throw new Exception($this->checkoutConfig["trans"]["error_max_total"]);

        //set objectsClassName
        $checkout->objectsClasses = $classes;
        //update total Q
        $checkout->totalQ = $totalQ;

        return $checkout;
    }
}
