<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'f12ed2f');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }