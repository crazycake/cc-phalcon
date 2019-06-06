<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '54539bb');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }