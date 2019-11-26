<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'd2a8322');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }