<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'f67a56f');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }