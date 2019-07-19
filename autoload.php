<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'd7280c2');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }