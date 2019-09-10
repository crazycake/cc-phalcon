<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '98a0dae');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }