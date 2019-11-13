<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'c8c63b5');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }