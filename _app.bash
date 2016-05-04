#! /bin/bash
# cc-phalcon CLI tools

# stop execution for exceptions
set -e

#Current path
CURRENT_PATH="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
MACHINE_USER_NAME="$(whoami)"

# Package properties
APP_NAME="cc-phalcon"
APP_NAMESPACE="$(echo "$APP_NAME" | tr '[:upper:]' '[:lower:]')"

# Webpacks path
WEBPACKS_PATH=$CURRENT_PATH"/webpacks/"

# Local documentation path.
# PHP Core
DOC_PHP_INPUT_PATH="./"
DOC_PHP_OUTPUT_PATH=$CURRENT_PATH"/../cc-docs/cc-phalcon/php/"

#script help function
scriptHelp() {
	echo -e "\033[93m"$APP_NAME" WebApp Environment Script\nValid actions:\033[0m"
	echo -e "\033[95m env: App environment set up, set correct permissions on directories and files.\033[0m"
	echo -e "\033[95m build: [PHP] Builds phar file with default box.json file.\033[0m"
	echo -e "\033[95m tree: [PHP] Returns the file tree of phar file.\033[0m"
	echo -e "\033[95m npm: [JS] Install or update npm dev dependencies.\033[0m"
	echo -e "\033[95m watch: [JS] Watch and builds webpack core.\033[0m"
	echo -e "\033[95m docs: Generates PHP & JS API Docs (PHP apigen & JS apidoc required).\033[0m"
	echo -e "\033[95m release: Creates a new tag release. Required version and message.\033[0m"
	echo -e "\033[95m delete-tags: Removes local and remote repository tags.\033[0m"
	exit
}

#check platform
APACHE_USER_GROUP="www-data:www-data"
#OSX
if [ "$(uname)" == "Darwin" ]; then
	APACHE_USER_GROUP="$(whoami):_www"
fi

# check args
if [ "$*" = "" ]; then
	scriptHelp
fi

if [ $1 = "env" ]; then
	# print project dir
	echo -e "\033[95mCurrent Dir: "$CURRENT_PATH" \033[0m"

	# set default perms for folders & files (use 'xargs -I {} -0 sudo chmod xxxx {}' if args is to long)
	echo -e "\033[95mApplying user-perms for all folders and files (sudo is required)... \033[0m"
	# current path
	find $CURRENT_PATH -type d -print0 | sudo xargs -0 chmod 0755
	find $CURRENT_PATH -type f -print0 | sudo xargs -0 chmod 0644

	#task done!
	echo -e "\033[92mDone! \033[0m"

elif [ $1 = "build" ]; then

	cd $CURRENT_PATH
	php box.phar build -v
	# task done!
	echo -e "\033[92mDone! \033[0m"

elif [ $1 = "tree" ]; then

	cd $CURRENT_PATH
	php box.phar info -l $APP_NAMESPACE".phar"
	# task done!
	echo -e "\033[92mDone! \033[0m"

elif [ $1 = "npm" ]; then

	cd $WEBPACKS_PATH

	echo -e "\033[35mInstalling npm dependencies in $WEBPACKS_PATH...\033[0m"

	# package instalation
	if [ "$(uname)" == "Darwin" ]; then
		npm install
	else
		sudo npm install
	fi

elif [ $1 = "watch" ]; then

	cd $WEBPACKS_PATH

	#executes gulp task
	gulp build

elif [ $1 = "docs" ]; then

	#PHP
	echo -e "\033[35mGenerating PHP Docs...\033[0m"
	phpdoc -d $DOC_PHP_INPUT_PATH -t $DOC_PHP_OUTPUT_PATH

	#JS
	cd $WEBPACKS_PATH
	echo -e "\033[35mGenerating JS Docs...\033[0m"
	yuidoc "src/modules/"

elif [ $1 = "release" ]; then

	if [ "$2" = "" ] || [ "$3" = "" ] ; then
		echo -e "\033[95mRelease and message params are required.\033[0m"
		exit
	fi

	echo -e "\033[95mReleasing version $2 \033[0m"

	git tag -a "$2" -m "$3"
	git push origin master --tags

elif [ $1 = "delete-tags" ]; then

	#loop through tags
	for t in `git tag`
	do
	    git push origin :$t
	    git tag -d $t
	done

else
	echo -e "\033[31mInvalid command\033[0m"
fi
