<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '98da9ab');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }