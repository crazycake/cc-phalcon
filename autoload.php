<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '00d1d9b');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }