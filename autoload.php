<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'b19241f');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }