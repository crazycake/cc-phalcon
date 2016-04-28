#! /bin/bash
# WebApp main script
# @author Nicolas Pulido <nicolas.pulido@crazycake.cl>

# stop script if an error occurs
set -e
# current path
PROJECT_PATH="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# App Name
APP_NAME=${PWD##*/}
APP_NAME="${APP_NAME/-webapp/}"

# module components paths
MODULES_NAME=("frontend" "backend" "api" "cli")
FRONTEND_PATH=$PROJECT_PATH"/frontend/"
BACKEND_PATH=$PROJECT_PATH"/backend/"
API_PATH=$PROJECT_PATH"/api/"
CLI_PATH=$PROJECT_PATH"/cli/"
MOD_NAME=""

# tools & packages path
TOOLS_PATH=$PROJECT_PATH"/.tools/"
PACKAGES_PATH=$PROJECT_PATH"/packages/"
COMPOSER_PATH=$PACKAGES_PATH"composer/"

# npm global dependencies
NPM_GLOBAL_DEPENDENCIES="gulp uglify-js npm-check-updates"

# Apache set up
APACHE_USER_GROUP="$(whoami):www-data"
# OSX special cases
if [ "$(uname)" == "Darwin" ]; then
	APACHE_USER_GROUP="$(whoami):_www"
fi

# folders that apache must own
APACHE_OWNER_FOLDERS=("app/cache/" "app/logs/" "app/langs/" "public/uploads/" "public/assets/" "outputs/" "storage/" "bootstrap/cache/")

# load environment file if exists
if [ -f "$PROJECT_PATH/.env" ]; then
	source "$PROJECT_PATH/.env"
fi

# help output
scriptHelp() {
	echo -e "\033[93m"$APP_NAME" webapp CLI [$APP_ENV]\033[0m"
	echo -e "\033[94mApp actions:\033[0m"
	echo -e "\033[95m env: App environment set up, sets owner group & perms for apache folders.\033[0m"
	echo -e "\033[95m composer <option>: Installs/Updates composer libraries with autoload-class dump. Use -s to composer self-update. Use -o for optimized dump.\033[0m"
	echo -e "\033[95m phinx: Executes phinx db migrations. Run phinx to display commands.\033[0m"
	echo -e "\033[95m cli: Executes PHP App CLI.\033[0m"
	echo -e "\033[95m wkhtmltopdf: Installs wkhtmltopdf library, required for webapps that uses PDF-maker engine.\033[0m"
	echo -e "\033[95m clean: Cleans cached view files and logs.\033[0m"
	echo -e "\033[96mDev actions:\033[0m"
	echo -e "\033[95m build: Executes build process for entire webapp. \033[0m"
	echo -e "\033[95m deploy <env> <option>: Deploy a phalcon app. env: -t, -s or -p. option: -m [migration], -c [composer], -mc [both]. \033[0m"
	echo -e "\033[95m watch <module>: Runs watcher daemon for backend or frontend. Modules: -b or -f.\033[0m"
	echo -e "\033[95m core: Installs/Updates core package (Requires cc-phalcon project). \033[0m"
	echo -e "\033[95m npm-global: Installs/Updates global required NPM dependencies.\033[0m"
	echo -e "\033[95m npm: Update NPM project dependencies. Use -u for package updates. \033[0m"
	echo -e "\033[95m trans: Update translations po file. \033[0m"
	echo -e "\033[95m aws-cli <option>: Install AWS CLI (Pip is required). Use -u for self-update, -c for configuration.\033[0m"
	echo -e "\033[95m aws-cdn <module>: Syncs S3 CDN bucket with app assets. Modules: -b or -f.\033[0m"
	exit
}

# build process
appBuild() {

	#import phalcon builder bash file
	source $TOOLS_PATH"_builder.bash"
	#call build task for deploy
	buildTask
}

# deploy task
appDeploy() {

	# making deploy
	if [ ! "$1" = "-t" ] && [ ! "$1" = "-s" ] && [ ! "$1" = "-p" ]; then
		echo -e "\033[95mNo environment option given for deploy.\033[0m"
	fi

	# build app first
	appBuild

	echo -e "\033[95mChecking CDN_SYNC env var... \033[0m"
	cd $PROJECT_PATH

	if [ "$CDN_SYNC_BACKEND" = "1" ]; then
		bash _app.bash aws-cdn -b
	fi

	if [ "$CDN_SYNC_FRONTEND" = "1" ]; then
		bash _app.bash aws-cdn -f
	fi

	#call deploy bash file
	cd $TOOLS_PATH
	bash _deploy.bash "$1" "$2"
}

# prevents machine from executing some task
excludeDeployMachine() {
	# check deploy machine
	if [ ! $APP_ENV = "local" ]; then
		echo -e "\033[31mThis script is for local environment only.\033[0m"
		exit
	fi
}

# handle module archument (frontend & backend)
handleModuleArgument() {

	if [ "$1" = "-b" ]; then
		MOD_NAME="backend"
	elif [ "$1" = "-f" ]; then
		MOD_NAME="frontend"
	else
		echo -e "\033[31mInvalid module option. Use -b or -f. \033[0m"
		exit
	fi
}

# check args
if [ "$*" = "" ]; then
	scriptHelp
fi

if [ $1 = "env" ]; then
	# print project dir
	echo -e "\033[96mProject Dir: "$PROJECT_PATH" \033[0m"

	# set default perms for folders & files (use 'xargs -I {} -0 sudo chmod xxxx {}' if args is to long)
	echo -e "\033[95mApplying user-perms for all folders and files (sudo is required)... \033[0m"

	# frontend
	if [ -d $FRONTEND_PATH ]; then
		find $FRONTEND_PATH -type d -not -path "*dev*" -print0 | sudo xargs -0 chmod 0755
		find $FRONTEND_PATH -type f -not -path "*dev*" -print0 | sudo xargs -0 chmod 0644
	fi

	# backend
	if [ -d $BACKEND_PATH ]; then
		find $BACKEND_PATH -type d -not -path "*dev*" -print0 | sudo xargs -0 chmod 0755
		find $BACKEND_PATH -type f -not -path "*dev*" -print0 | sudo xargs -0 chmod 0644
	fi

	# api
	if [ -d $API_PATH ]; then
		find $API_PATH -type d -print0 | sudo xargs -0 chmod 0755
		find $API_PATH -type f -print0 | sudo xargs -0 chmod 0644
	fi

	# cli
	if [ -d $CLI_PATH ]; then
		find $CLI_PATH -type d -print0 | sudo xargs -0 chmod 0755
		find $CLI_PATH -type f -print0 | sudo xargs -0 chmod 0644
	fi

	# set apache owner for specifics folders
	echo -e "\033[94mApplying app folders owner-group for apache... \033[0m"
	# loop through apache owner folders
	for FOLDER_PATH in "${APACHE_OWNER_FOLDERS[@]}"
	do
		# backend
		if [ -d $BACKEND_PATH$FOLDER_PATH ]; then
			sudo chown -R $APACHE_USER_GROUP $BACKEND_PATH$FOLDER_PATH
			sudo chmod -R 775 $BACKEND_PATH$FOLDER_PATH
		fi

		# frontend
		if [ -d $FRONTEND_PATH$FOLDER_PATH ]; then
			sudo chown -R $APACHE_USER_GROUP $FRONTEND_PATH$FOLDER_PATH
			sudo chmod -R 775 $FRONTEND_PATH$FOLDER_PATH
		fi

		# api
		if [ -d $API_PATH$FOLDER_PATH ]; then
			sudo chown -R $APACHE_USER_GROUP $API_PATH$FOLDER_PATH
			sudo chmod -R 775 $API_PATH$FOLDER_PATH
		fi

		# cli (CLI could runs as ubuntu or www-data user)
		if [ -d $CLI_PATH$FOLDER_PATH ]; then
			sudo chmod -R 775 $CLI_PATH$FOLDER_PATH
		fi

	done

	#update phalcon assets files
	if [ -d $BACKEND_PATH"public/assets/" ]; then
		find $BACKEND_PATH"public/assets/" -type f -print0 | sudo xargs -0 chmod 0775
	fi

	if [ -d $FRONTEND_PATH"public/assets/" ]; then
		find $FRONTEND_PATH"public/assets/" -type f -print0 | sudo xargs -0 chmod 0775
	fi

	#task done!
	echo -e "\033[92mDone! \033[0m"

elif [ $1 = "composer" ]; then

	if [ ! -d $COMPOSER_PATH ]; then
		echo -e "\033[95mComposer folder not found in packages directory.\033[0m"
		exit
	fi

	# go to composer dir
	cd $COMPOSER_PATH

	# check for self-update option
	if [ "$2" = "-s" ]; then
		# directory not exists, install
		php composer.phar self-update
		exit

	elif [ "$2" = "-o" ]; then

		echo -e "\033[95mComposer path: $COMPOSER_PATH \033[0m"

	# check if folder is created
	elif [ ! -d $COMPOSER_PATH"vendor" ]; then
			# directory not exists, install
			echo -e "\033[95mInstalling composer libraries \033[0m"
			php composer.phar install --no-dev
		else
			# directory exists, update
			echo -e "\033[95mUpdating composer libraries \033[0m"
			php composer.phar update --no-dev
	fi

	php composer.phar dump-autoload --optimize --no-dev
	cd $PROJECT_PATH
	# task done!
	echo -e "\033[95mComposer optimized autoload dump created! \033[0m"
	echo -e "\033[92mDone! \033[0m"

elif [ $1 = "phinx" ]; then

	echo -e "\033[95mRunning phinx command... \033[0m"

	php $COMPOSER_PATH"vendor/bin/phinx" "${@:2}"

elif [ $1 = "cli" ]; then

	echo -e "\033[95mRunning PHP App CLI... \033[0m"

	php $CLI_PATH"cli.php" "main" "${@:2}"

elif [ $1 = "wkhtmltopdf" ]; then

	echo -e "\033[95mInstalling wkhtmltopdf... \033[0m"
	#call script
	bash $TOOLS_PATH"/_wkhtmltopdf.bash"

elif [ $1 = "clean" ]; then

	# clean cache & log files for present modules
	for MOD_NAME in "${MODULES_NAME[@]}"
	do
		echo -e "\033[95mCleaning module cache and log files for $MOD_NAME. \033[0m"

		if [ ! -d $PROJECT_PATH"/"$MOD_NAME"/" ]; then
			continue
		fi

		sudo rm -rf $PROJECT_PATH"/"$MOD_NAME"/app/logs/"
		sudo rm -rf $PROJECT_PATH"/"$MOD_NAME"/app/cache/"
	done

	# checkout removed .html files
	echo -e "\033[95mBranch checkout... \033[0m"
	git checkout "*/app/*/index.html"

	# update environment file and folders
	bash _app.bash env

	# task done!
	echo -e "\033[92mDone! \033[0m"

elif [ $1 = "build" ]; then

	excludeDeployMachine

	appBuild

elif [ $1 = "deploy" ]; then

	excludeDeployMachine

	appDeploy "$2" "$3"

elif [ $1 = "watch" ]; then

	excludeDeployMachine

	handleModuleArgument "$2"

	echo -e "\033[95mRunning gulp watch task... \033[0m"
	gulp watch -m $MOD_NAME

elif [ $1 = "core" ]; then

	excludeDeployMachine

	bash $TOOLS_PATH"_core.bash"

elif [ $1 = "npm-global" ]; then

	excludeDeployMachine

	#modules instalation
	sudo npm install -g $NPM_GLOBAL_DEPENDENCIES

elif [ $1 = "npm" ]; then

	excludeDeployMachine

	echo -e "\033[95mUpdating project npm dependencies... \033[0m"

	if [ "$3" = "-u" ]; then
		echo -e "\033[95mChecking for updates... \033[0m"
		ncu -u
	fi

	#package instalation
	if [ "$(uname)" == "Darwin" ]; then
		npm install
		npm prune
	else
		sudo npm install
		sudo npm prune
	fi

elif [ $1 = "trans" ]; then

	excludeDeployMachine

	bash $TOOLS_PATH"_translations.bash" find -b
	bash $TOOLS_PATH"_translations.bash" find -f

elif [ $1 = "aws-cli" ]; then

	excludeDeployMachine

	echo -e "\033[95mChecking python & pip versions... \033[0m"

	python --version
	pip --version

	# instalation
	if [ "$2" = "-c" ]; then

		echo -e "\033[95mDefault region: us-east-1, Default Format Output: json [suggestion]\033[0m"

		aws configure

	elif [ "$2" = "-u" ]; then
		echo -e "\033[95mUpdating AWS CLI... \033[0m"
		sudo pip install --upgrade awscli
	else
		echo -e "\033[95mInstalling AWS CLI... \033[0m"
		sudo pip install awscli
	fi

	#check aws version
	echo -e "\033[95mChecking AWS CLI version... \033[0m"
	aws --version

elif [ $1 = "aws-cdn" ]; then

	excludeDeployMachine

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

else
	echo -e "\033[31mInvalid command\033[0m"
fi
