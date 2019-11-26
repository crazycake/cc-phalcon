<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'a67b1fa');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }