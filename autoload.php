<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'f1d3aa7');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }