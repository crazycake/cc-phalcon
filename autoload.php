<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '898ccf5');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }