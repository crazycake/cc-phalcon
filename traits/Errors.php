<?php
/**
 * Errors Trait
 * This class has the common public actions for error pages
 * Requires a Frontend or Backend Module with CoreController
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Traits;

trait Errors
{
	//....

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
     * View - Old Browser
     */
    public function oldBrowserAction()
    {
        //...
    }

    /**
     * View - Expired page (pages that needs temporal token to access)
     */
    public function expiredAction()
    {
        //...
    }
}
