<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'd4c1cca');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }