CrazyCake Phalcon Libraries
===========================

- PhalconPHP Version: 2.0.0

Packages
__________

- Core
- Utils
- QR
- Transbank

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
Get phar box library
```
curl -LSs https://box-project.github.io/box2/installer.php | php
```

Update phar library
```
box update
```

Library help
```
box help
```

Building phar file
```
box build -v
```
