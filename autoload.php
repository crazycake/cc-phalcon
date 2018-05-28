<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '2be200a');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }