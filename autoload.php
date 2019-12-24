<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '8cd82d3');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }