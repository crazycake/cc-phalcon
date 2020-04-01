<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '7d79be7');

// load App
require "phalcon/App.php";

/**
 * Kint global shortcut function
 * @param Mixed $vars - The input vars
 **/
function ss(...$vars) { s(...$vars); exit; }
