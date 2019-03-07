<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'ed11d9e');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }