<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'c5cd49c');

// load App
require "phalcon/App.php";

/**
 * Kint global shortcut function
 * @param Mixed $vars - The input vars
 **/
function ss(...$vars) { s(...$vars); exit; }
