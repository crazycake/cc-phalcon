<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '3d9ab6e');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }