<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'f4b079b');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }