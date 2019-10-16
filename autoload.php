<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'dc55e82');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }