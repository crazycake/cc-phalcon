<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'f8bf883');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }