<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '74a484c');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }