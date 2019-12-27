<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'f6b0e95');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }