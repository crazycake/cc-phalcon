<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'cd5985d');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }