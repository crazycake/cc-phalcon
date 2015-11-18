<?php
/**
 * Web Errors Trait
 * This class has the common public actions for error pages
 * Requires a Frontend or Backend Module with CoreController
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

trait WebErrors
{
    /** ---------------------------------------------------------------------------------------------------------------
     * Init Function, is executed before any action on a controller
     * ------------------------------------------------------------------------------------------------------------- **/
    protected function initialize()
    {
        parent::initialize();

        //disable robots
        $this->view->setVar("html_disallow_robots", true);
    }
    /* --------------------------------------------------- § -------------------------------------------------------- */

	/**
     * View - 404 Not found page
     */
    public function notFoundAction()
    {
        if($this->request->isAjax())
            $this->_sendJsonResponse(404);
    }

    /**
     * View - 500 Error page
     */
    public function internalAction()
    {
        if($this->request->isAjax())
            $this->_sendJsonResponse(500);
    }

    /**
     * View - Bad request page
     */
    public function badRequestAction()
    {
        if($this->request->isAjax())
            $this->_sendJsonResponse(400);
    }

    /**
     * View - Old Browser
     */
    public function oldBrowserAction()
    {

    }

    /**
     * View - Expired page (pages that needs temporal token to access)
     */
    public function expiredAction()
    {
        //...
    }
}
