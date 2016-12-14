<?php
/**
 * Checkout Currency
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Checkout;

//imports
use Phalcon\Exception;
use Predis\Client as Redis;
//core
use CrazyCake\Phalcon\App;
use Carbon\Carbon;

/**
 * Checkout Currency
 */
trait CheckoutCurrency
{
    /**
     * API URL to get chilean currencies values
     * @var string
     */
    private static $API_URL_CLP_CURRENCY = "http://www.mindicador.cl/";

    /**
     * Redis key to store Dollar day value to CLP currency
     * @var string
     */
    private static $REDIS_KEY_USD_CLP_VALUE = "CHECKOUT_CURRENCY_USD_CLP";

    /** ------------------------------------------- § ------------------------------------------------ **/

	/**
	 * Set Redis client
	 */
	protected function newRedisClient()
	{
        return new Redis([
			"scheme" 	 => "tcp",
			"host"   	 => "redis",
			"port"   	 => 6379,
			"persistent" => false
		]);
	}

    /**
     * CLI - Saves in Redis currency CLP - USD value conversion
     */
    protected function storeDollarChileanPesoValue()
    {
        try {

            //get value from remote API
            $value = $this->apiChileanCurrencyRequest();

            if (empty($value))
                throw new Exception("Invalid value received from api chilean currency");

			$redis = $this->newRedisClient();
            $redis->set(self::$REDIS_KEY_USD_CLP_VALUE, $value);

            $output = "[".date("d-m-Y H:i:s")."] storeDollarChileanPesoValue -> Stored value '$value' in Redis. \n";
            //print output
            $this->output($output);
        }
        catch (Exception $e) {

            $this->logger->error("CheckoutJob::storeChileanPesoToDollarConversion -> failed retriving API data. Err: ".$e->getMessage());
        }
    }

    /**
     * Gets CLP to USD currency conversion value
     * @param int $amount - The CLP amount
     * @return float The USD value
     */
    protected function chileanPesoToDollar($amount = 0)
    {
        $value = $this->dollarToChileanPeso();

        //fallback
        if (empty($value))
            throw new Exception("Invalid chilean currency value stored in Redis. Run CLI to store value");

        //apply conversion
        return number_format((float)($amount / $value), 2, '.', '');
    }

    /**
     * Get currency conversion USD to CLP
     */
	protected function dollarToChileanPeso($amount = 1.00)
	{
		//redis service
		$redis = $this->newRedisClient();

		$value = $redis->get(self::$REDIS_KEY_USD_CLP_VALUE);

        //set value if is empty
		if(empty($value) && $new_value = $this->apiChileanCurrencyRequest())
            $redis->set(self::$REDIS_KEY_USD_CLP_VALUE, $new_value);

		return $redis->get(self::$REDIS_KEY_USD_CLP_VALUE) * $amount;
	}

    /**
     * Calls API Chilean Currency to get indicator values
     * @param  string $indicator - Values: [uf, ivp, dolar, dolar_intercambio, euro, ipc, utm, imacec, tpm, libra_cobre, tasa_desempleo]
     * @param  int $interval - horas iterativas
     * @return float - The value
     */
    protected function apiChileanCurrencyRequest($indicator = "dolar", $interval = 12)
    {
		try {
	        //subtract now 12 hours
	        $date = (new Carbon())->subHours($interval);

	        //get dollar value for today
	        $api_url = self::$API_URL_CLP_CURRENCY."api/$indicator/".$date->format("d-m-Y");

	        //print output for CLI
			if(method_exists($this, "colorize"))
		        $this->colorize("Requesting: ".$api_url." => Date: ".$date->format("d-m-Y H:i"));

	        //try both approaches
	        if (ini_get("allow_url_fopen")) {

	            $json = file_get_contents($api_url);
				//sd($json);
	        }
	        else {

	            $curl = curl_init($api_url);
	            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	            $json = curl_exec($curl);
	            curl_close($curl);
	        }

		    //get data
		    $data = json_decode($json);

		    //check struct
		    if (!$data || !is_array($data->serie) || empty($data->serie)) {

				if(method_exists($this, "colorize"))
					$this->colorize("Invalid response struct o empty payload: ".json_encode($data, JSON_UNESCAPED_SLASHES), "WARNING");

		        return $this->apiChileanCurrencyRequest($indicator, $interval + 12);
			}

		    $serie = current($data->serie);
		    $value = (float)($serie->valor);

			//print output for CLI
			if(method_exists($this, "colorize"))
		    	$this->colorize("Saving value in Redis: ".$value);

			return $value;
		}
		catch(Exception $e) {
			$msg = $e->getMessage();
		}
		catch(\Exception $e) {
			$msg = $e->getMessage();
		}

		if(method_exists($this, "colorize"))
			$this->colorize($msg, "ERROR");

		return null;
    }
}
