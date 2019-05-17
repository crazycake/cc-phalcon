<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'b9ca8b1');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }