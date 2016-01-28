<?php
/**
 * Checkout Stripe
 * This class has common actions for stripe checkout controllers
 * Requires a Frontend or Backend Module with CoreController and SessionTrait
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Checkout;

//imports
use Phalcon\Exception;

/**
 * Checkout Manager
 */
trait CheckoutStripe
{
    /**
     * Set Trait configurations
     */
    abstract public function setConfigurations();

    /**
     * Config var
     * @var array
     */
    public $stripeConfig;

    /**
     * Initializer
     */
    protected function initialize()
    {
        parent::initialize();
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Ajax - Process Post Checkout Stripe Token.
     */
    public function stripeAction()
    {
        //make sure is ajax request
        $this->_onlyAjax();

        //get POST params data
        $data = $this->_handleRequestParams();

        try {

            //set buy order
            $payload = $data;

            //send JSON response
            return $this->_sendJsonResponse(200, $payload);
        }
        catch (Exception $e)  { $exception = $e->getMessage(); }
        catch (\Exception $e) { $exception = $e->getMessage(); }

        //sends an error message
        $this->_sendJsonResponse(200, $exception, 'alert');
    }
}
