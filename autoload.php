<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '59d245a');

// load App
require "phalcon/App.php";

/**
 * Kint global shortcut function
 * @param Mixed $vars - The input vars
 **/
function ss(...$vars) { s(...$vars); exit; }
