<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '6f4cdfe');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }