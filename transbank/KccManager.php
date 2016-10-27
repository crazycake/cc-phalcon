<?php
/**
 * Webpay Plus Kcc Trait
 * Handler Trait for Webpay Kcc Controller
 * Requires a Frontend or Backend Module with CoreController and SessionTrait
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Transbank;

//imports
use Phalcon\Exception;
//core
use CrazyCake\Phalcon\AppModule;
use CrazyCake\Helpers\Forms;

/**
 * Webpay Kcc Manager
 */
trait KccManager
{
    /* required functions */

    /**
     * Listener - Before render the success page
     * @param object $checkout - The checkout object reference
     */
    abstract public function onBeforeRenderSuccessPage(&$checkout);

    /* static vars */

    public static $MAC_FILE_PREFIX_NAME = "MAC01Normal_";
    public static $OUTPUTS_PATH         = "webpay/outputs/";
    public static $HANDLER_DEBUG_URI    = "checkout/skipPayment";

    /* properties */

    /**
     * Config var
     * @var array
     */
    public $kcc_manager_conf;

    /**
     * Payment types
     * @var array
     */
    public $kcc_payment_types;

    /**
     * This method must be call in constructor parent class
     * @param array $conf - The config array
     */
    protected function initKccManager($conf = [])
    {
        //set conf
        $this->kcc_manager_conf = $conf;

        //set payment types
        $this->kcc_payment_types = [
            "VN" => ["credit", $this->trans->_("Crédito"),  $this->trans->_("Sin Cuotas"), "0"],
            "VC" => ["credit", $this->trans->_("Crédito"),  $this->trans->_("Cuotas normales"), "4-48"],
            "SI" => ["credit", $this->trans->_("Crédito"),  $this->trans->_("Sin interés"), "3"],
            "S2" => ["credit", $this->trans->_("Crédito"),  $this->trans->_("Sin interés"), "2"],
            "CI" => ["credit", $this->trans->_("Crédito"),  $this->trans->_("Cuotas Comercio"), $this->trans->_("Número no definido")],
            "VD" => ["debit", $this->trans->_("Redcompra"), $this->trans->_("Débito"), "0"]
        ];
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Handler - load webpay setup for rendering view
     */
    public function viewSetup()
    {
        //set post input hiddens
        $inputs = [
            //set kcc params data (empty params will be set later when generating an order_buy)
            "TBK_URL_PAGO"         => $this->baseUrl($this->kcc_manager_conf["paymentCgiUri"]), //Payment URI location
            "TBK_URL_EXITO"        => $this->baseUrl($this->kcc_manager_conf["successUri"]),    //Success page
            "TBK_URL_FRACASO"      => $this->baseUrl($this->kcc_manager_conf["failedUri"]),     //Failure page
            "TBK_TIPO_TRANSACCION" => "TR_NORMAL",
            //dynamic inputs
            "TBK_ID_SESION"        => md5(uniqid(rand(), true)),
            "TBK_ORDEN_COMPRA"     => "",
            "TBK_MONTO"            => ""
        ];

        //for debugging, redirect to skip payment
        if (APP_ENVIRONMENT == "local") {
            $inputs["TBK_URL_PAGO"] = $this->baseUrl(self::$HANDLER_DEBUG_URI);
        }

        //pass data to view
        $this->view->setVars([
            "webpay_inputs" => $inputs
        ]);
    }

    /**
     * This action is called before the success page and process the succeful checkout
     * Este metodo es invocado antes de la redirección de Transbank [ACEPTADO ó RECHAZADO]
     * @param string $encrypted_data - The encrypted data
     */
    public function successTrxAction($encrypted_data = null)
    {
        $this->logger->debug("KccManager::_successTrxAction -> Got  encrypted data: $encrypted_data");

        if (empty($encrypted_data))
            $this->redirectToNotFound();

        try {
            //get users checkouts class
            $user_checkout_class = AppModule::getClass("user_checkout");
            //trx model class
            $checkout_trx_class = AppModule::getClass("user_checkout_trx");

            //decrypt data
            $buy_order = $this->cryptify->decryptData($encrypted_data);
            //get checkout obejct
            $checkout = $user_checkout_class::findFirstByBuyOrder($buy_order);

            //checks session id
            if (!$checkout)
                throw new Exception("Checkout not found with Buy Order: $buy_order");

            //reduce checkout object
            $checkout = $checkout->reduce();
            //parse transbank MAC file
            $params = $this->_parseMacFile($buy_order);

            //check MAC file exists
            if (!$params || $checkout->buy_order != $params["TBK_ORDEN_COMPRA"])
                throw new Exception("Invalid MAC file or buyOrders inconsistency. Buy Order: $buy_order");

            //check if trx was already processed
            if ($checkout_trx_class::findFirstByBuyOrder($checkout->buy_order))
                throw new Exception("Transaction already processed for buy order: ".$checkout->buy_order);

            //create & save a new transaction
            $trx = new $checkout_trx_class();

            if (!$trx->save($this->_parseKccTrx($params, $checkout)))
                throw new Exception("Error saving transaction: ".$trx->messages(true));

            $this->logger->debug("KccManager::successTrxAction -> successCheckout: ".json_encode($checkout));

            //call succes checkout and get checkout objects
            $checkout_controller = AppModule::getClass("checkout_controller");
            (new $checkout_controller())->successCheckout($checkout->buy_order);

            //ok response
            $this->jsonResponse(200);
        }
        catch (Exception $e) {

            $this->logger->error("KccManager::successTrxAction -> something occurred on Webpay Success Trx: ".$e->getMessage());

            $buyOrder = isset($checkout) ? $checkout->buy_order : "unknown";

            //NOTE: sending a warning to admin users!
            $mailer = AppModule::getClass("mailer_controller");

            (new $mailer())->sendAdminMessage([
                "subject" => "Trx handler error",
                "to"      => $this->config->app->emails->support,
                "email"   => $this->config->app->emails->sender,
                "name"    => $this->config->app->name." Kcc Webpay Service",
                "message" => "An error occurred during successful Webpay Success Trx (".APP_ENVIRONMENT.").\n".
                             "\n BuyOrder:".$buyOrder." \n Trace: ".$e->getMessage()
            ]);

            //error server response
            $this->jsonResponse(500);
        }
    }

    /**
     * View - success page
     * TODO: esta accion solo debe ser invocada por el IP whiteList de Transbank
     */
    public function successAction()
    {
        //handle response, dispatch to auth/logout
        $this->requireLoggedIn();

        //get post params
        $data = $this->handleRequest([
            "@TBK_ID_SESION"    => "string",
            "@TBK_ORDEN_COMPRA" => "string"
        ], "POST", false);

        try {
            //model classes
            $user_checkout_class        = AppModule::getClass("user_checkout");
            $user_checkout_object_class = AppModule::getClass("user_checkout_object");
            $checkout_trx_class         = AppModule::getClass("user_checkout_trx");

            //get checkout
            $checkout = $user_checkout_class::findFirstByBuyOrder($data["TBK_ORDEN_COMPRA"]);

            if (!$checkout)
                throw new Exception("Checkout not found. TBK_ORDEN_COMPRA: ".$data["TBK_ORDEN_COMPRA"]);

            //reduce checkout object
            $checkout = $checkout->reduce();

            //parse transbank MAC file
            $params = $this->_parseMacFile($checkout->buy_order);

            if (!$params)
                throw new Exception("Invalid checkout params. Input data ".json_encode($data));

            //get extend props
            $checkout->trx = $checkout_trx_class::findFirstByBuyOrder($checkout->buy_order);

            if (!$checkout->trx)
                throw new Exception("No processed TRX found for Buy Order: ".$checkout->buy_order);

            //get checkout objects
            $checkout->objects = $user_checkout_object_class::getCollection($checkout->buy_order);

            //before render success page listener
            $this->onBeforeRenderSuccessPage($checkout);

            //set flash message
            $this->flash->success($this->kcc_manager_conf["trans"]["SUCCESS_TRX"]);

            //set view vars
            $this->view->setVar("webpay", $params);
            $this->view->setVar("checkout", $checkout);
        }
        catch (Exception $e) {

            $this->logger->error("KccManager::onSuccess -> something occurred on Webpay Success page: Data: \n ".print_r($data, true)." \n ".$e->getMessage());

            $this->redirectTo("webpay/failed", $data);
        }
    }

    /**
     * View - failed page (POST or GET)
     * param TBK_ID_SESION is not used.
     */
    public function failedAction()
    {
        //get post params (optional params)
        $data = $this->handleRequest([
            "@TBK_ID_SESION"    => "string",
            "@TBK_ORDEN_COMPRA" => "string"
        ], "MIXED", false);

        //model classes
        $user_checkout_class = AppModule::getClass("user_checkout");
        $checkout_controller = AppModule::getClass("checkout_controller");

        //get checkout
        $checkout = $user_checkout_class::findFirstByBuyOrder($data["TBK_ORDEN_COMPRA"]);

        //set checkout uri
        if ($checkout) {
            //call failed checkout
            (new $checkout_controller())->failedCheckout($checkout);
            //get last requested URI
            $client = json_decode($checkout->client);
            //set view vars
            $this->view->setVar("checkoutUri", $client->requestedUri);
        }

        //log error
        $this->logger->error("KccManager::failedAction -> Data received: ".json_encode($data));

        //pass data to view
        $this->view->setVar("webpay", $data);
    }

    /**
     * View - debug success page
     * NOTE: just for debugging
     */
    public function renderSuccessAction()
    {
        if (APP_ENVIRONMENT == "production")
            $this->redirectToNotFound();

        //handle response, dispatch to auth/logout
        $this->requireLoggedIn();

        //get classes name
        $user_checkout_class        = AppModule::getClass("user_checkout");
        $user_checkout_object_class = AppModule::getClass("user_checkout_object");

        //get last checkout
        $checkout = $user_checkout_class::getLast($this->user_session["id"], "success");

        if (!$checkout)
            die("Render: No buy order to process for userID:".$this->user_session["id"]);

        //reduce checkout object
        $checkout = $checkout->reduce();

        //parse transbank MAC file
        $params = $this->_parseMacFile($checkout->buy_order);

        //set checkout objects
        $checkout->objects = $user_checkout_object_class::getCollection($checkout->buy_order);

        //call listener
        $this->onBeforeRenderSuccessPage($checkout);

        $this->view->setVar("webpay", $params);
        $this->view->setVar("checkout", $checkout);

        $this->view->pick("webpay/success");
    }

    /**
     * Formats order amount for Kcc
     * @param  double $amount - The checkout amount
     * @param  boolean $parse - Parses the tail zeros
     * @return string
     */
    public static function formatAmountForKcc($amount = 0, $parse = false)
    {
        return $parse ? substr($amount, 0, strlen($amount) - 2) : $amount."00";
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Parses a new kcc transaction
     * @param array $params - The Webpay KCC POST data
     * @param object $checkout - The session checkout data
     * @return object The ORM object
     */
    public static function _parseKccTrx($params, $checkout)
    {
        //webpay date format mmdd-his
        $gateway_date = explode("-", $params["TBK_DATE"]);
        //split format
        $date        = str_split($gateway_date[0], 2);
        $time        = str_split($gateway_date[1], 2);
        $result_date = date("Y")."-".$date[0]."-".$date[1]." ".implode(":", $time);

        return [
            "buy_order"        => $params["TBK_ORDEN_COMPRA"],
            "trx_id"           => $params["TBK_ID_TRANSACCION"],
            "type"             => $params["TBK_TIPO_PAGO"][0],
            "card_last_digits" => $params["TBK_FINAL_NUMERO_TARJETA"],
            "amount"           => $checkout->amount,
            "currency"         => $checkout->currency,
            "date"             => $result_date
        ];
    }

    /**
     * Parses MAC file generated by GCI
     * @param  string $buy_order - The checkout buy order (required)
     * @param  string $outputs_path - The webpay outputs path (optional)
     * @param  string $file_prefix - The MAC file prefix (optional)
     * @return array with format TBK_ORDEN_COMPRA => array(TBK_ORDEN_COMPRA => VALUE), ...
     */
    private function _parseMacFile($buy_order = "", $outputs_path = null, $file_prefix = null)
    {
        if (is_null($file_prefix))
            $file_prefix = self::$MAC_FILE_PREFIX_NAME;

        if (is_null($outputs_path))
            $outputs_path = self::$OUTPUTS_PATH;

        $file = PROJECT_PATH.$outputs_path.$file_prefix.$buy_order.".log";

        try {
            //check if file exists
            if (!is_file($file))
                throw new Exception("No MAC file found");

            //get transbank data from file
            $file_opened  = fopen($file, "r");
            $file_content = fgets($file_opened);
            fclose($file_opened);

            $data   = explode("&", $file_content);
            $delim  = "=";
            $params = [];

            foreach ($data as $key => $value) {

                //parse data (key is the index, value an string with format prop=value)
                $array = explode($delim, $value);
                $prop  = $array[0];

                if ($prop == "TBK_MAC")
                    continue;

                $params[$prop] = isset($array[1]) ? $array[1] : null;
            }

            /* validate & format some params */

            if (!isset($params["TBK_MONTO"]))
                throw new Exception("Invalid parsed mount ".$params["TBK_MONTO"]);

            if (!isset($params["TBK_FECHA_TRANSACCION"]))
                throw new Exception("Invalid parsed date".$params["TBK_FECHA_TRANSACCION"]);

            //amount
            $params["TBK_MONTO"] = self::formatAmountForKcc($params["TBK_MONTO"], true);
            //amount with format
            $params["TBK_MONTO_FORMATO"] = Forms::formatPrice($params["TBK_MONTO"], "CLP")." (CLP)";

            //date & time
            $params["TBK_DATE"]         = $params["TBK_FECHA_TRANSACCION"]."-".$params["TBK_HORA_TRANSACCION"];
            $params["TBK_DATE_FORMATO"] = self::_formatDate($params["TBK_FECHA_TRANSACCION"], $params["TBK_HORA_TRANSACCION"]);

            //payment type
            if (isset($this->kcc_payment_types[$params["TBK_TIPO_PAGO"]])) {

                $type = $params["TBK_TIPO_PAGO"];
                $params["TBK_TIPO_PAGO"] = $this->kcc_payment_types[$type];
                //caso especial para cuotas VC
                if ($type == "VC")
                    $params["TBK_TIPO_PAGO"][3] = $params["TBK_NUMERO_CUOTAS"];
            }

            return $params;
        }
        catch (Exception $e) {
            $this->logger->error("WebpayHelper::_parseMacFile -> error parsing file, err: ".$e->getMessage());
            return false;
        }
    }

	/**
     * Format Date
     * @return string
     */
    private function _formatDate($tbk_date, $tbk_time)
    {
        $date  = str_split($tbk_date, 2);
        $time  = str_split($tbk_time, 2);
        $month = $date[0];
        $day   = $date[1];
		$hour  = implode(":", $time);

        //set month
        $months = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
        $month  = $months[(int)$month - 1];

        return "$day de $month a las $hour.";
    }
}
