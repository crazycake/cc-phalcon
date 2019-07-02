<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '1e803c3');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }