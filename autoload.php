<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'a1d3d56');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }