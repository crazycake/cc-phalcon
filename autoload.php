<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '3b3b00b');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }