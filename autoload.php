<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'd8e1e7f');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }