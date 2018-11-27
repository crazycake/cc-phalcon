<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '29b3f75');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }