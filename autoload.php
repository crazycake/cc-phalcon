<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'eb1b0b0');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }