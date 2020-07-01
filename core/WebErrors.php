<?php
/**
 * Web Errors Trait
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Core;

/**
 * Handles public actions for error pages
 * Requires a Frontend Module
 */
trait WebErrors
{
	/**
	 * After Execute Route
	 */
	public function onBeforeInitialize()
	{
		// disable robots
		$this->view->setVar("metas", ["disallow_robots" => true]);
	}

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
			$this->jsonResponse(404);
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

	}

	/**
	 * View - Prints current log
	 */
	public function logAction($code = "")
	{
		if (APP_ENV != "local" && $code != "cc-".date("YmH"))
			$this->redirectToNotFound();

		$file = STORAGE_PATH."logs/".date("d-m-Y").".log";

		if (!is_file($file)) die("No log file found.");

		ss(file_get_contents($file));
	}
}
