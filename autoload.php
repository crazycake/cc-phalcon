<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'a45941b');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }