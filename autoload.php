<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'ee2a252');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }