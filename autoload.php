<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '39ebe08');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }