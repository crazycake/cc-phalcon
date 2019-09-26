<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '6bf8e86');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }