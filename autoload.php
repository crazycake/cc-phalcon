<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '2c554d1');

// load App
require "phalcon/App.php";

/**
 * Kint global shortcut function
 * @param Mixed $vars - The input vars
 **/
function ss(...$vars) { s(...$vars); exit; }
