<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'ec594d7');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }