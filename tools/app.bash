#! /bin/bash
# author: Nicolas Pulido <nicolas.pulido@crazycake.cl>

# stop script if an error occurs
set -e
# set project path
PROJECT_PATH="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# app namespace
APP_NAME=${PWD##*/}
APP_NAME="${APP_NAME/-webapp/}"

# module components paths
MOD_NAME=""

# app paths
TOOLS_PATH=$PROJECT_PATH"/.tools/"
STORAGE_PATH=$PROJECT_PATH"/storage/"
COMPOSER_PATH=$PROJECT_PATH"/vendor/"

# help output
scriptHelp() {
	echo -e "\033[93m"$APP_NAME" webapp CLI\033[0m"
	echo -e "\033[94mDev commands:\033[0m"
	echo -e "\033[95m cli: Executes PHP App CLI.\033[0m"
	echo -e "\033[95m db: Executes DB migrations. Run command for help (phinx engine).\033[0m"
	echo -e "\033[95m clean: Cleans storage folder (cache, logs).\033[0m"
	echo -e "\033[95m build: build JS & CSS bundles and compile translations. \033[0m"
	echo -e "\033[95m watch <module>: Runs watcher daemon for backend or frontend. Modules: -b or -f.\033[0m"
	echo -e "\033[95m watch-mailing <module>: Runs mailing watcher daemon for backend or frontend. Modules: -b or -f.\033[0m"
	echo -e "\033[95m core: Installs/Updates core package (Requires cc-phalcon project). \033[0m"
	echo -e "\033[95m trans: Update translations po file. \033[0m"
	echo -e "\033[95m aws-cli <option>: Install AWS CLI (Pip is required). Use -s for self-update, -c for configuration.\033[0m"
	echo -e "\033[95m aws-cdn <module>: Syncs S3 CDN bucket with app assets. Modules: -b or -f.\033[0m"
	exit
}

# build process
appBuild() {

	#import phalcon builder bash file
	source $TOOLS_PATH"builder.bash"
	#call build task for deploy
	buildTask
}

# handle module archument (frontend & backend)
handleModuleArgument() {

	#check arg
	if [ "$1" = "-b" ]; then
		MOD_NAME="backend"
	elif [ "$1" = "-f" ]; then
		MOD_NAME="frontend"
	else
		echo -e "\033[31mInvalid module option. Use -b or -f. \033[0m"
		exit
	fi
}

# commands
case "$1" in

db)

	if [ ! -f $COMPOSER_PATH"bin/phinx" ]; then
		echo -e "\033[31mphinx library not found in composer project folder.\033[0m"
		exit
	fi

	echo -e "\033[95mRunning phinx command... \033[0m"

	php $COMPOSER_PATH"bin/phinx" "${@:2}"
	;;

cli)

	echo -e "\033[95mRunning PHP App CLI... \033[0m"

	php $PROJECT_PATH"/cli/cli.php" "main" "${@:2}"
	;;

clean)

	# clean storage
	if [ -d $STORAGE_PATH ]; then
		find $STORAGE_PATH -type f \( ! -iname ".*" \) -print0 | xargs -0 sudo rm
	fi

	# task done!
	echo -e "\033[92mDone! \033[0m"
	;;

build)

	appBuild
	;;

watch)

	handleModuleArgument "$2"

	echo -e "\033[95mRunning gulp watch...\033[0m"

	gulp watch -m $MOD_NAME
	;;

watch-mailing)

	handleModuleArgument "$2"

	echo -e "\033[95mRunning gulp watch-mailing...\033[0m"

	gulp watch-mailing -m $MOD_NAME
	;;

core)

	bash $TOOLS_PATH"core.bash"
	;;

trans)

	bash $TOOLS_PATH"translations.bash" find -b
	bash $TOOLS_PATH"translations.bash" find -f
	;;

aws-cli)

	echo -e "\033[95mChecking python & pip versions... \033[0m"

	python --version
	pip --version

	# instalation
	if [ "$2" = "-c" ]; then

		echo -e "\033[95mDefault region: us-east-1, Default Format Output: json [suggestion]\033[0m"

		aws configure

	elif [ "$2" = "-s" ]; then
		echo -e "\033[95mUpdating AWS CLI... \033[0m"
		sudo pip install --upgrade awscli
	else
		echo -e "\033[95mInstalling AWS CLI... \033[0m"
		sudo pip install awscli
	fi

	#check aws version
	echo -e "\033[95mChecking AWS CLI version... \033[0m"
	#get AWS CLI version
	aws --version
	;;

aws-cdn)

	handleModuleArgument "$2"

	#sync assets
	SYNC_LOCAL_PATH="$PROJECT_PATH/$MOD_NAME/public/assets/"
	SYNC_REMOTE_PATH="s3://$APP_NAME-cdn/$MOD_NAME/assets/"

	echo -e "\033[95mBucket Syncing $SYNC_LOCAL_PATH -> $SYNC_REMOTE_PATH \033[0m"
	#sync
	aws s3 sync $SYNC_LOCAL_PATH $SYNC_REMOTE_PATH --delete --cache-control max-age=864000 --exclude '*' --include '*.rev.css' --include '*.rev.js'

	#sync images
	SYNC_LOCAL_PATH="$PROJECT_PATH/$MOD_NAME/public/images/"
	SYNC_REMOTE_PATH="s3://$APP_NAME-cdn/$MOD_NAME/images/"

	echo -e "\033[95mBucket Syncing $SYNC_LOCAL_PATH -> $SYNC_REMOTE_PATH \033[0m"
	#sync
	aws s3 sync $SYNC_LOCAL_PATH $SYNC_REMOTE_PATH --delete --cache-control max-age=864000 --exclude '*.htaccess' --exclude '*.DS_Store' --exclude '*.html'

	#sync fonts
	SYNC_LOCAL_PATH="$PROJECT_PATH/$MOD_NAME/public/fonts/"
	SYNC_REMOTE_PATH="s3://$APP_NAME-cdn/$MOD_NAME/fonts/"

	echo -e "\033[95mBucket Syncing $SYNC_LOCAL_PATH -> $SYNC_REMOTE_PATH \033[0m"
	#sync
	aws s3 sync $SYNC_LOCAL_PATH $SYNC_REMOTE_PATH --delete --cache-control max-age=864000 --exclude '*.htaccess' --exclude '*.DS_Store' --exclude '*.html'
	;;

#default
*)
	scriptHelp
    ;;
esac
