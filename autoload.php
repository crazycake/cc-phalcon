<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'a3d1814');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }