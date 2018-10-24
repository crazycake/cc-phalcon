<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'a7c9001');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }