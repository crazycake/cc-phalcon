<?php
/**
 * PayPal Express Trait
 * This class has common actions for PayPal Express flux
 * Requires a Frontend or Backend Module with CoreController and SessionTrait
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\PayPal;

//imports
use Phalcon\Exception;

/**
 * PayPalExpress
 */
trait ExpressManager
{
    /* required functions */

    /**
     * Set Trait configurations
     */
    abstract public function setConfigurations();

    /* static vars */


    /* properties */

    /**
     * Config var
     * @var array
     */
    public $expressConfig;

    /**
     * Initializer
     */
    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * Handler - load webpay setup for rendering view
     */
    public function loadSetupForView()
    {
        //set post input hiddens
        $inputs = [

        ];

        //pass data to view
        $this->view->setVars([
            "paypalInputs" => $inputs
        ]);
    }
}
