<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '92d969d');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }