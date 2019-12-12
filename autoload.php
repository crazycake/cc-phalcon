<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '56a8001');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }