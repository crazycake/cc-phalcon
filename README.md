CrazyCake Phalcon Libraries
===========================

## PhalconPHP

Current Version: `2.0.x`

## Documentation

[CrazyCake Docs](http://docs.crazycake.cl/)

## Box Project

- Used for building phar files.
- Files are autoloaded with `phalcon/AppLoader.php` class:
- Documentation [ref](http://box-project.org/)

### Commands
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
