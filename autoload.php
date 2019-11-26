<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'e7101d8');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }