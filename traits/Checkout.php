<?php
/**
 * Checkout Trait
 * This class has common actions for checkout controllers
 * Requires a Frontend or Backend Module with CoreController and SessionTrait
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Traits;

//imports
use Phalcon\Exception;
use CrazyCake\Utils\Cacher;

trait Checkout
{
    /**
     * abstract required methods
     */
    abstract public function setConfigurations();
    abstract public function onBeforeBuyOrderCreation(&$checkout);
    abstract public function onSuccessCheckoutTaskComplete($user, &$checkout);
    abstract public function onSkippedPayment(&$checkout);

    /**
     * Config var
     * @var array
     */
    public $checkoutConfig;

    /**
     * The cacher library
     * @var object
     */
    protected $cacher;

    /** ---------------------------------------------------------------------------------------------------------------
     * Init Function, is executed before any action on a controller
     * ------------------------------------------------------------------------------------------------------------- **/
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

        //set catcher
        $this->cacher = new Cacher('redis');

        //handle response, dispatch to auth/logout
        $this->_checkUserIsLoggedIn(true);
    }
    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Ajax - Before user goes to payment gateway (or not), buy order must be generated.
     * @TODO: move this action to checkoutTrait
     */
    public function buyOrderAction()
    {
        //make sure is ajax request
        $this->_onlyAjax();
        //get form data
        $data = $this->_handleRequestParams([
            "checkoutUri"        => "string",  //checkout URI
            "checkoutGateway"    => "string",  //checkout payment gateway
            "checkoutCategoryId" => "string",  //a parent category refence
            "@invoiceEmail"      => "string"   //custom validation
        ]);

        try {

            //get checkout object and set category property name
            $checkout = $this->_parseCheckoutData($data);

            //call listeners
            $this->onBeforeBuyOrderCreation($checkout);

            //get class
            $users_checkout_class = $this->getModuleClassName('users_checkouts');
            //save checkout detail in DB
            $checkoutOrm = $users_checkout_class::newBuyOrder($this->user_session["id"], $checkout);
            //check if an error occurred
            if(!$checkoutOrm)
                throw new Exception($this->checkoutConfig["trans"]["error_unexpected"]);

            //set cache data (checkout struct)
            $cache = (array)$checkout;
            $cache["buyOrder"] = $checkoutOrm->buy_order;
            //print_r($cache);exit;

            if(!$this->cacher->set($checkoutOrm->session_key, $cache))
                throw new Exception($this->checkoutConfig["trans"]["error_unexpected"]);

            //set payload data
            $payload = array_merge($cache, $data);
            $payload["sessionKey"] = $checkoutOrm->session_key;
            unset($payload["objects"]);

            if(APP_ENVIRONMENT !== "production")
                $this->logger->debug("CheckoutTrait:buyOrder -> got new order, session key: ".$checkoutOrm->session_key.". Obj: ".json_encode($cache));

            // send JSON response
            $this->_sendJsonResponse(200, $payload);
        }
        catch (Exception $e) {
            //sends an error message
            $this->_sendJsonResponse(200, $e->getMessage(), 'alert');
        }
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
            "buyOrder" => "string"
        ]);

        try {
            //get class
            $users_checkout_class = $this->getModuleClassName('users_checkouts');

            //instance cache lib and get data
            $session_key = $users_checkout_class::getCheckoutSessionKey($buy_order);
            $checkout    = $this->cacher->get($session_key);

            if(!$checkout)
                throw new Exception($this->checkoutConfig["trans"]["error_expired"]);

            //check buy orders
            if($data["buyOrder"] != $checkout->buyOrder)
                throw new Exception($this->checkoutConfig["trans"]["error_unexpected"]);

            //set flash message
            $this->flash->success($this->checkoutConfig["trans"]["success_checkout"]);
            //call succes checkout
            $this->successCheckout($checkout);

            //send OK response (TODO: redirect to success page first)
            $this->_sendJsonResponse(200, ["redirectUri" => "account/"]);
        }
        catch (Exception $e) {
            //sends an error message
            $this->_sendJsonResponse(200, $e->getMessage(), 'alert');
        }
    }

    /**
     * Succesful checkout, Called when checkout was made succesfuly (eg: after payment)
     * Checkout object cames from cacher session
     * @param object $checkout The checkout sesssion object
     * @param boolean $async If false wait for response
     * @return object Checkout
     */
    public function successCheckout($checkout, $async = true)
    {
        //executes another async self request, this time as socket (connection is closed before waiting for response)
        $this->_asyncRequest(
            ["checkout" => "successCheckoutTask"],
            //encrypted data
            ["checkout" => $checkout],
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

            if(is_null($data) || !isset($data->checkout))
                throw new Exception("Invalid decrypted data: ".print_r($data, true));

            //set classes
            $users_class          = $this->getModuleClassName('users');
            $users_checkout_class = $this->getModuleClassName('users_checkouts');
            //set event & checkout objects
            $users_checkout_objects_class = $users_checkout_class."Objects";

            //get checkout, user and event
            $checkout    = $data->checkout;
            $checkoutOrm = $users_checkout_class::getCheckout($checkout->buyOrder);
            $user        = $users_class::getObjectById($checkoutOrm->user_id);

            //check if data is OK
            if(!$user)
                throw new Exception("Invalid decrypted data, userId: ".$checkoutOrm->user_id.", data: ".print_r($data, true));

            $checkout->objects = $users_checkout_objects_class::getCheckoutObjects($checkout->buyOrder);

            //1) update status of checkout
            $users_checkout_class::updateState($checkout->buyOrder, 'success');

            //2) CALL OBJECT CLASS LOGIC
            foreach ($checkout->objectsClasses as $className) {

                $objectClass = $className."Controller";

                if(method_exists($objectClass, "onCheckoutSuccess"))
                    (new $objectClass())->onCheckoutSuccess($user->id, $checkout);
            }

            //3) Call listener
            $this->onSuccessCheckoutTaskComplete($user, $checkout);
        }
        catch(Exception $e) {

            $mailer = $this->getModuleClassName('mailer');

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
     * @return boolean
     */
    public function failedCheckout($checkout = false)
    {
        //get module class name
        $users_checkout_class = $this->getModuleClassName('users_checkouts');

        if(!$checkout || !isset($checkout->buyOrder))
            return false;

        //get ORM object and update status of checkout
        $checkoutOrm = $users_checkout_class::getCheckout($checkout->buyOrder);

        if(!$checkoutOrm)
            return false;

        if($checkoutOrm->state == "pending")
            $users_checkout_class::updateState($checkout->buyOrder, 'failed');

        return true;
    }

    /**
     * Skips payment, simulates success checkout (Only for development)
     * Metodo parche para crear entradas a partir de un checkout generado (se salta el pago)
     * La transaccion es opcional
     * @param string $code The security code
     * @return SQL statements
     */
    public function skipPaymentAction($code = "")
    {
        //get module class name
        $users_checkout_class = $this->getModuleClassName('users_checkouts');

        //instance cache lib and get data
        $last_checkout = $users_checkout_class::getLastUserCheckout($this->user_session["id"]);

        if(!$last_checkout)
            die("No pending checkout found for user id:".$this->user_session["id"]);

        $session_key = $users_checkout_class::getCheckoutSessionKey($last_checkout->buy_order);
        //validates an active checkout session
        $checkout = $this->cacher->get($session_key);

        //basic security
        if(APP_ENVIRONMENT !== 'development' && $code !== sha1($checkout->buyOrder))
            $this->_redirectToNotFound();

        if(empty($checkout))
            die("Invalid cached checkout for user_id ".$session_key);

        $checkoutOrm = $users_checkout_class::getCheckout($checkout->buyOrder);

        if(!$checkoutOrm || $checkoutOrm->state != "pending")
            die("No pending checkout found for user_id ".$session_key);

        //append custom comment
        $this->onSkippedPayment($checkout);

        //log
        $this->logger->debug("CheckoutTrait::skipPaymentAction -> Skipped payment for userId: ".$checkoutOrm->user_id.", BO: ".$checkout->buyOrder);
        //call succes checkout
        $this->successCheckout($checkout);

        //set flash message
        $this->flash->success($this->checkoutConfig["trans"]["success_checkout"]);
        //redirect
        $this->_redirectTo("account");
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Loads common setup for checkout view.
     * This are used for HTML bindings
     * @TODO support multiple payment gateways with checkout_gateway (hardcoded)
     * @param object $user The user object
     * @param string $categoryType The category type, example: paid, free, etc.
     * @param array $objects The checkout objects array
     * @param string $objectsClass The checkout objects class name
     * @param object $parentObject The parent object
     */
    private function _setupCheckoutView($categoryId = 0, $categoryType = "", $user = null, $objects = array(), $objectsClass = "", $view = "default")
    {
        //default inputs for checkout
        $inputs = [
            "checkoutUri"        => "",            //last visited uri (set by JS)
            "checkoutGateway"    => $categoryType, //checkout gateway name
            "checkoutCategoryId" => $categoryId    //checkout category id or namespace
        ];

        //get module class name
        $users_checkout_class = $this->getModuleClassName('users_checkouts');

        //set default max checkout number
        $checkoutMax = 1;

        //NOTE: only one gateway supported for now
        if($categoryType == "paid") {

            $inputs["checkoutGateway"] = $this->checkoutConfig["gateway"];
            //increase max number
            $checkoutMax = $this->checkoutConfig["max_per_item_allowed"];
        }

        //check for last used invoice email
        $lastCheckout = $users_checkout_class::findFirst([
            "user_id = ?0",
            "order" => "local_time DESC",
            "bind"  => [$user->id]
        ]);

        //pass data to view
        $this->view->setVars([
            "invoiceEmail"         => $lastCheckout ? $lastCheckout->invoice_email : $user->email,
            "checkoutInputs"       => $inputs,
            "checkoutInputsPrefix" => "checkout_"
        ]);

        //prepare tickets JS
        $objectsJs = [];
        foreach ($objects as $obj)
            $objectsJs[] = $obj->toArray($this->checkoutConfig["object_properties"]);

        //load JS modules
        $module_name = $this->checkoutConfig["js_module_name"];

        $this->_loadJavascriptModules([
            "$module_name" => [
                "categoryType"  => $categoryType,
                "checkoutMax"   => $checkoutMax,
                "objects"       => $objectsJs,
                "objectsClass"  => $objectsClass
            ]
        ]);

        //pick view
        $this->view->pick("checkout/$view");
    }

    /**
     * Parses the checkout POST params
     * @param  array $data The POST form data [checkoutUri, checkoutGateway, invoiceEmail]
     * @return stdObject with amount & objects_array
     */
    private function _parseCheckoutData($data = array())
    {
        //get module class name
        $users_checkout_class = $this->getModuleClassName('users_checkouts');

        //check invoice email if set
        if(!isset($data["invoiceEmail"]) || !filter_var($data['invoiceEmail'], FILTER_VALIDATE_EMAIL))
            throw new Exception($this->MSGS["ERROR_INVOICE_EMAIL"]);

        //lower case email
        $data["invoiceEmail"] = strtolower($data["invoiceEmail"]);

        //set object properties
        $checkout = new \stdClass();

        $checkout->client        = isset($this->client) ? json_encode($this->client, JSON_UNESCAPED_SLASHES) : null;
        $checkout->successTrxUrl = [$this->_baseUrl(), $this->checkoutConfig["success_trx_uri"]];
        $checkout->invoiceEmail  = $data["invoiceEmail"];
        $checkout->checkoutUri   = $data["checkoutUri"];
        $checkout->gateway       = $data["checkoutGateway"];
        $checkout->categoryId    = $data["checkoutCategoryId"];
        $checkout->coin          = $this->checkoutConfig["default_coin"];
        $checkout->objects       = [];
        $checkout->amount        = 0;

        //temp vars
        $totalQ        = 0;
        $objectClasses = [];

        //loop throught items
        foreach ($data as $key => $q) {

            //parse properties
            $props = explode("_", $key);

            //validates checkout data has defined prefix
            if(strpos($key, "checkout_") === false || count($props) != 3 || empty($q))
                continue;

            //get object props
            $object_class = $props[1];
            $object_id    = $this->cryptify->decryptHashId($props[2]);

            $preffixed_object_class = "\\$object_class";
            $object = $preffixed_object_class::getObjectById($object_id);
            //var_dump($object_class, $object_id, $object->toArray());exit;

            //check that object is in stock (also validates object exists)
            if(!$users_checkout_class::validateObjectStock($object_class, $object_id, $q)) {
                $this->logger->error("CheckoutTrait::_parseCheckoutObjects -> No stock for object '$object_class' ID: $object_id, q: $q.");
                throw new Exception(str_replace("{name}", $object->name, $this->checkoutConfig["trans"]["error_no_stock"]));
            }

            //append object class
            if(!in_array($object_class, $objectClasses))
                array_push($objectClasses, $object_class);

            //update amount
            $checkout->amount += $q * $object->price;
            //set item in array
            $checkout->objects[$object_class."_".$object_id] = $q;
            //update total Q
            $totalQ += $q;
        }

        //weird error, no checkout objects
        if(empty($checkout->objects))
            throw new Exception($this->checkoutConfig["trans"]["error_expired"]);

        //check max objects allowed
        if($totalQ > $this->checkoutConfig["user_max_item_per_category"])
            throw new Exception($this->checkoutConfig["trans"]["error_max_total"]);

        //set objectsClassName
        $checkout->objectsClasses = $objectClasses;
        //update total Q
        $checkout->totalQ = $totalQ;

        return $checkout;
    }
}
