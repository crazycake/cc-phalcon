<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '7e9c78b');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }