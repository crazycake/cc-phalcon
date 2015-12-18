<?php
/**
 * End Point for KCC CGI, handles CGI response.
 * The end point URL is set in KCC kit config files.
 * Nested Path: ./{module-name}/public/ directory
 * Paths are relative to ./{module-name}/public/ directory
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Transbank;

/**
 * KccEndPoint Class
 */
class KccEndPoint
{
    // MAC file prefix name
    const MAC_FILE_PREFIX_NAME = "MAC01Normal_";
    // set log path
    const LOG_PATH = PROJECT_PATH."/webpay/outputs/";
    // Cache CLI task (gets saved session and buy order status)
    const CMD_EXEC_CLI_CACHE = "php ".PROJECT_PATH."/cli/cli.php main getCheckoutCache";
    // Process TRX CLI task (process a succesful transaction)
    const CMD_EXEC_CLI_SUCCESS = "php ".PROJECT_PATH."/cli/cli.php main successCheckout";
    // CGI MAX exec
    const CMD_EXEC_CGI = "cgi-bin/tbk_check_mac.cgi";

    /**
     * Inits webpay TRX validation
     */
    function handleResponse()
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
        $mac_file = self::LOG_PATH.self::MAC_FILE_PREFIX_NAME."$TBK_ID_SESION.log";
        // cgi & cli args
        $cmd_cli_cache   = self::CMD_EXEC_CLI_CACHE." ".$TBK_ID_SESION;
        $cmd_cli_success = self::CMD_EXEC_CLI_SUCCESS." ".$TBK_ID_SESION;
        $cmd_cgi         = self::CMD_EXEC_CGI." $mac_file";

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
        exec($cmd_cli_cache, $output_cli, $return_value);
        // execs MAC cgi
        exec($cmd_cgi, $output_cgi, $return_value);

        // logs exec CLI taks output
        $this->logOutput(
            "exec command: $cmd_cli_cache \n".
            "CLI CACHE return_value (0 = Ok): ".$return_value."\n".json_encode($output_cli, JSON_UNESCAPED_SLASHES)."\n".
            "exec command: $cmd_cgi \n".
            "CGI return_value (0 = Ok): ".$return_value."\n".json_encode($output_cgi, JSON_UNESCAPED_SLASHES)
        );

        // checks CLI output data
        if(empty($output_cli))
            $this->setOutput(false, "Output invalido de CLI task: ".print_r($output_cli, true));

        // process CLI output data
        $data = json_decode($output_cli[0]);

        //2) buyOrder validation
        if(!isset($data->buyOrder) || $TBK_ORDEN_COMPRA != $data->buyOrder)
            $this->setOutput(false, "Orden de compra es nulo o distinto de TBK_ORDEN_COMPRA.");

        //3) amount validation
        if($TBK_MONTO != $data->amountFormatted)
            $this->setOutput(false, "El monto es distinto de TBK_MONTO.");

        //4) checks for buy orders duplicity, validates checkout state.
        if($data->state != "pending")
            $this->setOutput(false, "El estado de la orden es distinto de 'pending'.");

        //5) checks MAC CGI response
        if(empty($output_cgi) || $output_cgi[0] != "CORRECTO")
            $this->setOutput(false, "CGI MAC entegó un output distinto a 'CORRECTO' -> ".$output_cgi[0]);

        //6) OK, all validations passed process succesful Checkout
        exec($cmd_cli_success, $output_cli, $return_value);

        $this->logOutput(
            "exec command: $cmd_cli_success \n".
            "CLI SUCCESS return_value (0 = Ok): ".$return_value."\n"
        );

        //OK, redirect...
        $this->setOutput(true, "EXITO, redirecting...");
    }

    /**
     * Log text to file
     * @var string $text The text to log
     * @var boolean $first Markup flag for first log line
     */
    function logOutput($text, $first = false)
    {
        //set text
        $text = $first ? "\nWP LOG [".date("d-m-Y H:i:s")."]\n$text\n" : "$text\n";
        //saves text in log
        file_put_contents(self::LOG_PATH."xt_compra_".date("d-m-Y").".log", $text, FILE_APPEND);
    }

    /**
     * Sets output response
     * @var boolean $success True means ACEPTADO
     * @var string $log Logs something
     */
    function setOutput($success = true, $log = "")
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
    function saveMacFile($file)
    {
        // saves each POST params for MAC cgi execution (MAC file format)
        $file_opened = fopen($file, "wt");
        while (list($key, $val) = each($_POST)) {
            fwrite($file_opened, "$key=$val&");
        }
        fclose($file_opened);
    }
}
