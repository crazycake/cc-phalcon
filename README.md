CrazyCake Phalcon Core
======================

## Requeriments

+ PHP `v7.4.x`
+ Phalcon `v4.x`


## Projects setup

Projects must have the following file path

```bash
~/workspace/{company-name}/{project-code}/{project-code}-{project-name}

# example
~/workspace/crazycake/cc/cc-frontend
```


## Box Project

- Used for building PHP phar files.
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
