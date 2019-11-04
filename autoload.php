<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '65df56d');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }