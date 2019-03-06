<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'bcc8b4c');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }