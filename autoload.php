<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'c23a1d2');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }