<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'df87f5d');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }