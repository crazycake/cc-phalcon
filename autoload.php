<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '100b889');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }