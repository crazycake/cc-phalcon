<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'bc29f31');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }