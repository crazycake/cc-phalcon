<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'e87ff4b');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }