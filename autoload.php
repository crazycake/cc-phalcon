<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'dc96b66');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }