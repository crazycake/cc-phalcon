#! /bin/bash
## Script para configurar entorno

# se interrumpe el script si ocurre un error
set -e
#Current path
CURRENT_PATH="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
MACHINE_USER_NAME="$(whoami)"
# WebApp properties
APP_NAME="cc-phalcon"
APP_NAMESPACE="$(echo "$APP_NAME" | tr '[:upper:]' '[:lower:]')"

#script help function
scriptHelp() {
	echo -e "\033[93m"$APP_NAME" WebApp Environment Script\nValid commands:\033[0m"
	echo -e "\033[95m -env: App environment set up, set correct permissions on directories and files.\033[0m"
	echo -e "\033[95m -build: Builds phar file with default box.json file.\033[0m"
	echo -e "\033[95m -tree: Returns the file tree of phar file.\033[0m"
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

if [ $1 = "-env" ]; then
	# print project dir
	echo -e "\033[95mCurrent Dir: "$CURRENT_PATH" \033[0m"

	# set default perms for folders & files (use 'xargs -I {} -0 sudo chmod xxxx {}' if args is to long)
	echo -e "\033[95mApplying user-perms for all folders and files (sudo is required)... \033[0m"
	# current path
	find $CURRENT_PATH -type d -print0 | sudo xargs -0 chmod 0755
	find $CURRENT_PATH -type f -print0 | sudo xargs -0 chmod 0644

	#task done!
	echo -e "\033[92mScript successfully executed! \033[0m"

elif [ $1 = "-build" ]; then

	cd $CURRENT_PATH
	php box.phar build -v
	# task done!
	echo -e "\033[92mScript successfully executed! \033[0m"

elif [ $1 = "-tree" ]; then

	cd $CURRENT_PATH
	php box.phar info -l $APP_NAMESPACE".phar"
	# task done!
	echo -e "\033[92mScript successfully executed! \033[0m"

else
	echo -e "\033[31mInvalid command\033[0m"
fi
