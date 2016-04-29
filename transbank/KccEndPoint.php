<?php
/**
 * End Point for KCC CGI, handles CGI response.
 * The end point URL is set in KCC kit config files.
 * Nested Path: ./{module-name}/public/ directory
 * Paths are relative to ./{module-name}/public/ directory
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Transbank;

use CrazyCake\Phalcon\AppModule;
use CrazyCake\Core\MvcCore;

/**
 * KccEndPoint Class
 */
class KccEndPoint extends MvcCore
{
    // URI SUCCES TRX Handler
    const SUCCESS_URI_HANDLER = "webpay/successTrx/";
    // MAC file prefix name
    const MAC_FILE_PREFIX_NAME = "MAC01Normal_";

    // MAC file path
    private static $MAC_PATH = PROJECT_PATH."webpay/outputs/";
    // CGI MAC exec
    private static $CMD_EXEC_CGI = PUBLIC_PATH."cgi-bin/tbk_check_mac.cgi";

    /**
     * Set custom logger
     */
    private $log;

    /**
     * constructor
     */
    protected function onConstruct()
    {
        //set logger service
        $this->log = new \Phalcon\Logger\Adapter\File(APP_PATH."logs/webpaykcc_".date("d-m-Y").".log");
    }

    /**
     * Inits webpay TRX validation
     */
    public function handleResponse()
    {
        //make sure post params are const
        if(!isset($_POST["TBK_RESPUESTA"]) || !isset($_POST["TBK_ID_SESION"]) || !isset($_POST["TBK_ORDEN_COMPRA"]))
            $this->setOutput(false, "post data invalida: ".isset($_POST) ? json_encode($_POST) : "null");

        //get TBK post data
        $TBK_RESPUESTA    = $_POST["TBK_RESPUESTA"];
        $TBK_ORDEN_COMPRA = $_POST["TBK_ORDEN_COMPRA"];
        $TBK_MONTO        = $_POST["TBK_MONTO"];

        //set MAC file name
        $mac_file = self::$MAC_PATH.self::MAC_FILE_PREFIX_NAME."$TBK_ORDEN_COMPRA.log";
        //cgi command args
        $cmd_cgi = self::$CMD_EXEC_CGI." $mac_file";

        //save mac file
        $this->saveMacFile($mac_file);

        /** Transbank required validations **/

        $this->logOutput("TBK POST params:".json_encode($_POST, JSON_UNESCAPED_SLASHES), true);

        /**
         * 1) Validates Transbank response, 0 means accepted. -8 to -1 means bank-card error.
         * NOTE: TBK_RESPUESTA -8 a -1 se procesa como ACEPTADO pero termina en fracaso.
         * @link: https://bitbucket.org/ctala/woocommerce-webpay/
         */
        if ($TBK_RESPUESTA >= -8 && $TBK_RESPUESTA <= -1)
            $this->setOutput(true);

        if($TBK_RESPUESTA != 0)
            $this->setOutput(false, "TBK_RESPUESTA es distinto de 0");

        //execs MAC cgi
        exec($cmd_cgi, $output_cgi, $code_cgi);

        //logs exec CGI taks output
        $this->logOutput(
            "exec command: $cmd_cgi \n".
            "CGI return_value (0 = Ok): ".$code_cgi."\n".json_encode($output_cgi, JSON_UNESCAPED_SLASHES)
        );

        //get users checkouts class
        $user_checkout_class = AppModule::getClass("user_checkout");
        //get checkout obejct
        $checkout = $user_checkout_class::findFirstByBuyOrder($TBK_ORDEN_COMPRA);

        //2) buyOrder validation
        if(!$checkout || $TBK_ORDEN_COMPRA != $checkout->buy_order)
            $this->setOutput(false, "Orden de compra es nulo o distinto de TBK_ORDEN_COMPRA ($TBK_ORDEN_COMPRA).");

        $amount_formatted = ((int)$checkout->amount)."00";
        //3) amount validation (kcc format is appended)
        if($TBK_MONTO != $amount_formatted)
            $this->setOutput(false, "El monto es distinto de TBK_MONTO ($TBK_MONTO != $amount_formatted).");

        //4) checks MAC CGI response
        if(empty($output_cgi) || $output_cgi[0] != "CORRECTO")
            $this->setOutput(false, "CGI MAC entegó un output distinto a CORRECTO -> ".$output_cgi[0]);

        //5) OK, all validations passed process succesful Checkout
        $this->onSuccessTrx($checkout);

        //OK, redirect...
        $this->setOutput(true, "EXITO, redirecting...");
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Log text to file
     * @param string $text - The text to log
     * @param boolean $first - Markup flag for first log line
     */
    protected function logOutput($text, $first = false)
    {
        //set text
        $text = $first ? "\nKccEndPoint [".date("d-m-Y H:i:s")."]\n$text" : "$text";
        //log message
        $this->log->debug($text);
    }

    /**
     * Sets output response
     * @param boolean $success - True value means ACEPTADO
     * @param string $log - Logs something
     */
    protected function setOutput($success = true, $log = "")
    {
        //logs rejected reason?
        if(!empty($log))
            $this->logOutput($log);

        //outputs response
        $response = $success ? "ACEPTADO" : "RECHAZADO";
        //log response
        $this->logOutput("response: ".$response);
        //send response
        $this->_sendTextResponse($response);
    }

    /**
     * Save Mac file with POST params
     * @param string $file - The MAC file path
     */
    protected function saveMacFile($file)
    {
        // saves each POST params for MAC cgi execution (MAC file format)
        $file_opened = fopen($file, "wt");
        while (list($key, $val) = each($_POST)) {
            fwrite($file_opened, "$key=$val&");
        }
        fclose($file_opened);
    }

    /**
     * on success TRX event, sends Async Call
     * @param object $checkout - The Checkout object
     */
    protected function onSuccessTrx($checkout)
    {
        //get DI reference (static)
        $di = \Phalcon\DI::getDefault();

        //Base URL is kept in client data for CGI call.
        $client = json_decode($checkout->client);

        //async request
        $this->_asyncRequest([
            "base_url" => $client->baseUrl,
            "uri"      => self::SUCCESS_URI_HANDLER,
            "payload"  => $checkout->buy_order,
            "socket"   => true
        ]);
    }
}
