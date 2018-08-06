<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'f2ef8a3');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }