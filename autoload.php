<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '2a6d8d9');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }