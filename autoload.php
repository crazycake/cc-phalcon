<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'ee6c5f3');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }