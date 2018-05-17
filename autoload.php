<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '9c248a4');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }