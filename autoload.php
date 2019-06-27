<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '9d5ba1c');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }