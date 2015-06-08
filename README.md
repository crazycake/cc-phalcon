CrazyCake Phalcon Libraries
===========================

- PhalconPHP Version: `2.x`

Packages
--------
- phalcon
- core
- utils
- models
- qr
- transbank

Box Project
-----------
- Used for building phar files
- Files are autoloaded with `Loader.php` class:
- Documentation [ref](http://box-project.org/)


###Loading phar file
```
<?php
	//Load phar file,
	//Classes are autoloaded with 'Phalcon\Loader->registerNamespaces()' function.
	require 'cc-phalcon.phar';
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

Listing phar file contents
```
php box.phar info -l <filepath>
```

