<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'c656a57');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }