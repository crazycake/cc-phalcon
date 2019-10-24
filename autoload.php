<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '1c77333');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }