<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '14e4d42');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }