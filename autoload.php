<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'bd29d7e');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }