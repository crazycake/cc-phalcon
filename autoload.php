<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'b3f9997');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }