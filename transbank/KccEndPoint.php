<?php
/**
 * End Point for KCC CGI, handles CGI response.
 * The end point URL is set in KCC kit config files.
 * Nested Path: ./{module-name}/public/ directory
 * Paths are relative to ./{module-name}/public/ directory
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Transbank;

use CrazyCake\Services\Guzzle;

/**
 * KccEndPoint Class
 */
class KccEndPoint
{
    /* traits */
    use Guzzle;

    // MAC file prefix name
    const MAC_FILE_PREFIX_NAME = "MAC01Normal_";
    // set MAC file path
    const MAC_PATH = PROJECT_PATH."webpay/outputs/";
    // CLI Cache exec
    const CMD_EXEC_CLI = "php ".PROJECT_PATH."cli/cli.php main getCache";
    // CGI MAC exec
    const CMD_EXEC_CGI = PUBLIC_PATH."cgi-bin/tbk_check_mac.cgi";

    /**
     * Set custom logger
     */
    private $log;

    /**
     * constructor
     */
    public function __construct()
    {
        //set logger service
        $this->log = new \Phalcon\Logger\Adapter\File(APP_PATH."logs/webpayKcc_".date("d-m-Y").".log");
    }

    /**
     * Inits webpay TRX validation
     */
    public function handleResponse()
    {
        // make sure post params are const
        if(!isset($_POST["TBK_RESPUESTA"]) || !isset($_POST["TBK_ID_SESION"]) || !isset($_POST["TBK_ORDEN_COMPRA"]))
            $this->setOutput(false, "post data invalida: ".isset($_POST) ? json_encode($_POST) : "null");

        // get TBK post data
        $TBK_RESPUESTA    = $_POST["TBK_RESPUESTA"];
        $TBK_ID_SESION    = $_POST["TBK_ID_SESION"];
        $TBK_ORDEN_COMPRA = $_POST["TBK_ORDEN_COMPRA"];
        $TBK_MONTO        = $_POST["TBK_MONTO"];

        // set MAC file name
        $mac_file = self::MAC_PATH.self::MAC_FILE_PREFIX_NAME."$TBK_ID_SESION.log";
        // cli & cgi command args
        $cmd_cli = self::CMD_EXEC_CLI." ".$TBK_ID_SESION;
        $cmd_cgi = self::CMD_EXEC_CGI." $mac_file";

        //save mac file
        $this->saveMacFile($mac_file);

        /** Transbank required validations **/

        $this->logOutput("TBK POST params:".json_encode($_POST, JSON_UNESCAPED_SLASHES), true);

        //1) Validates Transbank response, 0 means accepted. -8 to -1 means bank-card error.
        //NOTE: TBK_RESPUESTA -8 a -1 se procesa como ACEPTADO pero termina en fracaso.
        //@link: https://bitbucket.org/ctala/woocommerce-webpay/
        if ($TBK_RESPUESTA >= -8 && $TBK_RESPUESTA <= -1)
            $this->setOutput(true);

        if($TBK_RESPUESTA != 0)
            $this->setOutput(false, "TBK_RESPUESTA es distinto de 0");

        // execs CLI module for cache task
        exec($cmd_cli, $output_cli, $code_cli);
        // execs MAC cgi
        exec($cmd_cgi, $output_cgi, $code_cgi);

        // logs exec CGI taks output
        $this->logOutput(
            "exec command: $cmd_cli \n".
            "CLI CACHE return_value (0 = Ok): ".$code_cli."\n".json_encode($output_cli, JSON_UNESCAPED_SLASHES)."\n".
            "exec command: $cmd_cgi \n".
            "CGI return_value (0 = Ok): ".$code_cgi."\n".json_encode($output_cgi, JSON_UNESCAPED_SLASHES)
        );

        //get checkout
        if(empty($output_cli))
            $this->setOutput(false, "Cache key no encontrada: ".print_r($output_cli, true));

        // parse CLI output data
        $checkout = json_decode($output_cli[0]);

        //2) buyOrder validation
        if(!isset($checkout->buyOrder) || $TBK_ORDEN_COMPRA != $checkout->buyOrder)
            $this->setOutput(false, "Orden de compra es nulo o distinto de TBK_ORDEN_COMPRA ($TBK_ORDEN_COMPRA).");

        //3) amount validation
        if($TBK_MONTO != $checkout->amountFormatted)
            $this->setOutput(false, "El monto es distinto de TBK_MONTO.");

        //4) checks MAC CGI response
        if(empty($output_cgi) || $output_cgi[0] != "CORRECTO")
            $this->setOutput(false, "CGI MAC entegó un output distinto a 'CORRECTO' -> ".$output_cgi[0]);

        //5) OK, all validations passed process succesful Checkout
        $this->onSuccessTrx($TBK_ID_SESION, $checkout);

        //OK, redirect...
        $this->setOutput(true, "EXITO, redirecting...");
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Log text to file
     * @var string $text The text to log
     * @var boolean $first Markup flag for first log line
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
     * @var boolean $success True means ACEPTADO
     * @var string $log Logs something
     */
    protected function setOutput($success = true, $log = "")
    {
        //logs rejected reason?
        if(!empty($log))
            $this->logOutput($log);

        //outputs response
        die("<html>".($success ? "ACEPTADO" : "RECHAZADO")."</html>");
    }

    /**
     * Save Mac file with POST params
     * @param  string $file The MAC file path
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
     * @param object $checkout The checkout stored object
     */
    protected function onSuccessTrx($session_key, $checkout)
    {
        //get DI reference (static)
        $di = \Phalcon\DI::getDefault();

        //pass hashed session id
        $url = $checkout->handlerUrl;

        //log call (debug)
        $this->logOutput("OnSuccessTrx async-request: ".$url[0]." -> ".$url[1]);

        //set sending data
        $sending_data = $di->getShared('cryptify')->encryptForGetRequest($session_key);
        //send async request
        $this->_sendAsyncRequest($url[0], $url[1], $sending_data);
    }
}
