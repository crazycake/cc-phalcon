<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '998eb7a');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }