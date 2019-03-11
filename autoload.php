<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '8e8917c');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }