<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'aeb8bdf');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }