<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '5d1c3a4e');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }