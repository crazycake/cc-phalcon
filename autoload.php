<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '0d0bd54');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }