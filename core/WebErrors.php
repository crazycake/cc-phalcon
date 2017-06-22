<?php
/**
 * Web Errors Trait
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

/**
 * Handles public actions for error pages
 * Requires a Frontend or Backend Module
 */
trait WebErrors
{
	/**
	 * After Execute Route
	 */
	protected function afterExecuteRoute()
	{
		parent::afterExecuteRoute();

		//disable robots
		$this->view->setVar("html_disallow_robots", true);
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * View - 404 Not found page
	 */
	public function notFoundAction()
	{
		if ($this->request->isAjax())
			$this->jsonResponse(404);
	}

	/**
	 * View - 500 Error page
	 */
	public function internalAction()
	{
		if ($this->request->isAjax())
			$this->jsonResponse(500);
	}

	/**
	 * View - Bad request page
	 */
	public function badRequestAction()
	{
		if ($this->request->isAjax())
			$this->jsonResponse(400);
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
