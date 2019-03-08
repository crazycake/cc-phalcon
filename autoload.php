<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'eb59f52');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }