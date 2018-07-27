<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'f68ad4b');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }