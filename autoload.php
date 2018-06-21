<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'db9738f');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }