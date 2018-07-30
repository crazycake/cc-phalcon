<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '6e32eff');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }