CrazyCake Phalcon Libraries
===========================

PhalconPHP Version: 2.0.0

Packages
__________

- Core
- Utils
- QR

Box Project
-----------
- Used for building phar files
- Files are autoloaded with `Loader.php` class:
- Documentation [ref](http://box-project.org/)


###Loading phar file
```
<?php
	//Load phar file
	require 'cc-phalcon.phar';
	new \CrazyCake\Loader($loader, $packages);

	/* Classes are autoloaded with 'Phalcon\Loader::registerNamespaces()' function */
?>
```

###Commands
Library help
```
box help
```
Building phar file
```
box build -v
```