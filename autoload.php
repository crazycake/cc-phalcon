<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '3975c11');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }