#! /bin/bash
# webapp main script for OSX or Ubuntu
# author: Nicolas Pulido <nicolas.pulido@crazycake.cl>

# stop script if an error occurs
set -e
# set project path
PROJECT_PATH="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# app namespace
APP_NAME=${PWD##*/}
APP_NAME="${APP_NAME/-webapp/}"

# module components paths
MODULES_NAME=("frontend" "backend" "api" "cli")
MOD_NAME=""

# tools & packages path
TOOLS_PATH=$PROJECT_PATH"/.tools/"
STORAGE_PATH=$PROJECT_PATH"/storage/"
COMPOSER_PATH=$PROJECT_PATH"/packages/composer/"

# npm global dependencies
NPM_GLOBAL_DEPENDENCIES="gulp uglify-js npm-check eslint"

# apache set up
USER_NAME="$(whoami)"
APACHE_USER_GROUP="www-data"

# OSX special cases
if [ "$(uname)" == "Darwin" ]; then
	APACHE_USER_GROUP="_www"
fi

# folders that apache must own
APACHE_MODULES_FOLDERS=("app/langs/" "public/uploads/" "public/assets/")

# load environment file if exists
if [ -f "$PROJECT_PATH/.env" ]; then
	source "$PROJECT_PATH/.env"
fi

# help output
scriptHelp() {
	echo -e "\033[93m"$APP_NAME" webapp CLI [$APP_ENV]\033[0m"
	echo -e "\033[94mApp commands:\033[0m"
	echo -e "\033[95m env: App environment set up, sets owner group & perms for apache folders.\033[0m"
	echo -e "\033[95m composer <option>: Installs/Updates composer dependencies. Use -s to composer self-update.\033[0m"
	echo -e "\033[95m cli: Executes PHP App CLI.\033[0m"
	echo -e "\033[95m db: Executes DB migrations. Run command for help (phinx engine).\033[0m"
	echo -e "\033[95m clean: Cleans storage folder.\033[0m"
	echo -e "\033[95m wkhtmltopdf: Installs wkhtmltopdf library, required for webapps that uses PDF-maker engine.\033[0m"
	echo -e "\033[96mDev commands:\033[0m"
	echo -e "\033[95m build: Executes build process for entire webapp. \033[0m"
	echo -e "\033[95m deploy <env> <option>: Deploy a phalcon app. env: -t, -s or -p. option: -m [migration], -c [composer], -mc [both]. \033[0m"
	echo -e "\033[95m watch <module>: Runs watcher daemon for backend or frontend. Modules: -b or -f.\033[0m"
	echo -e "\033[95m watch-mailing <module>: Runs mailing watcher daemon for backend or frontend. Modules: -b or -f.\033[0m"
	echo -e "\033[95m npm-global: Installs/Updates global required npm dependencies.\033[0m"
	echo -e "\033[95m npm: Update npm project dependencies. Use -u for package updates. \033[0m"
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

# deploy task
appDeploy() {

	# making deploy
	if [ ! "$1" = "-t" ] && [ ! "$1" = "-s" ] && [ ! "$1" = "-p" ]; then
		echo -e "\033[95mNo environment option given for deploy.\033[0m"
	fi

	# build app first
	appBuild

	# only staging or production
	if [ "$1" = "-s" ] || [ "$1" = "-p" ]; then
		echo -e "\033[95mChecking CDN_SYNC env var... \033[0m"
		cd $PROJECT_PATH

		if [ "$CDN_SYNC_BACKEND" = "1" ]; then
			bash app.bash aws-cdn -b
		fi

		if [ "$CDN_SYNC_FRONTEND" = "1" ]; then
			bash app.bash aws-cdn -f
		fi
	fi

	#call deploy bash file
	cd $TOOLS_PATH
	bash deploy.bash "$1" "$2"
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

# check args
if [ "$*" = "" ]; then

	scriptHelp

elif [ $1 = "env" ]; then

	# print project dir
	echo -e "\033[96mProject Dir: "$PROJECT_PATH" \033[0m"

	APACHE_USER_STATE="$( id -nG $USER_NAME | grep -c $APACHE_USER_GROUP )"

	# add user to apache group?
	if [ "$APACHE_USER_STATE" = "1" ]; then

	    echo -e "\033[96mUser belongs to $APACHE_USER_GROUP [ok] \033[0m"
	else

	    echo -e "\033[95mAdding user '$USER_NAME' to group '$APACHE_USER_GROUP' \033[0m"

		if [ "$(uname)" == "Darwin" ]; then
			sudo dseditgroup -o edit -a $USER_NAME -t user $APACHE_USER_GROUP
		else
			sudo usermod -a -G $APACHE_USER_GROUP $USER_NAME
		fi
	fi

	# set default perms for folders & files (use 'xargs -I {} -0 sudo chmod xxxx {}' if args is to long)
	echo -e "\033[95mUpdating user-perms for all folders & files (sudo is required)... \033[0m"

	#project storage folder
	if [ -d $STORAGE_PATH ]; then
		sudo chown -R $USER_NAME:$APACHE_USER_GROUP $STORAGE_PATH
		sudo chmod -R 0775 $STORAGE_PATH
	fi

	#module folders
	for MOD_NAME in "${MODULES_NAME[@]}"
	do
		#set directory & file permissions
		if [ -d $MOD_NAME ]; then
			find $MOD_NAME -type d -not -path "*dev*" -print0 | sudo xargs -0 chmod 0755
			find $MOD_NAME -type f -not -path "*dev*" -print0 | sudo xargs -0 chmod 0644
		fi

		#apache subfolders
		for FOLDER_PATH in "${APACHE_MODULES_FOLDERS[@]}"
		do
			if [ -d $MOD_NAME"/"$FOLDER_PATH ]; then
				sudo chown -R $USER_NAME:$APACHE_USER_GROUP $MOD_NAME"/"$FOLDER_PATH
				sudo chmod -R 0775 $MOD_NAME"/"$FOLDER_PATH
			fi
		done
	done

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
	# check if folder is created
	elif [ ! -d $COMPOSER_PATH"vendor" ]; then
		# directory not exists, install
		echo -e "\033[95mInstalling composer libraries...\033[0m"
		php composer.phar install --no-dev
	else
		# directory exists, update
		echo -e "\033[95mUpdating composer libraries...\033[0m"
		php composer.phar update --no-dev
	fi

	php composer.phar dump-autoload --optimize --no-dev
	cd $PROJECT_PATH
	# task done!
	echo -e "\033[95mComposer optimized autoload dump created! \033[0m"
	echo -e "\033[92mDone! \033[0m"

elif [ $1 = "db" ]; then

	if [ ! -f $COMPOSER_PATH"vendor/bin/phinx" ]; then
		echo -e "\033[31mphinx library not found in composer project folder.\033[0m"
		exit
	fi

	echo -e "\033[95mRunning phinx command... \033[0m"

	php $COMPOSER_PATH"vendor/bin/phinx" "${@:2}"

elif [ $1 = "cli" ]; then

	echo -e "\033[95mRunning PHP App CLI... \033[0m"

	php $PROJECT_PATH"/cli/cli.php" "main" "${@:2}"

elif [ $1 = "wkhtmltopdf" ]; then

	echo -e "\033[95mInstalling wkhtmltopdf... \033[0m"
	#call script
	bash $TOOLS_PATH"/wkhtmltopdf.bash"

elif [ $1 = "clean" ]; then

	# clean storage
	if [ -d $STORAGE_PATH ]; then
		find $STORAGE_PATH -type f -not ".gitkeep" -print0 | xargs -0 sudo rm
	fi

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

	echo -e "\033[95mRunning gulp...\033[0m"

	gulp watch -m $MOD_NAME

elif [ $1 = "watch-mailing" ]; then

	excludeDeployMachine

	handleModuleArgument "$2"

	echo -e "\033[95mRunning gulp...\033[0m"

	gulp watch-mailing -m $MOD_NAME

elif [ $1 = "npm-global" ]; then

	excludeDeployMachine

	echo -e "\033[95mUpdating npm global packages... \033[0m"

	#modules instalation
	sudo npm install -g $NPM_GLOBAL_DEPENDENCIES

elif [ $1 = "npm" ]; then

	excludeDeployMachine

	echo -e "\033[95mUpdating npm project packages... \033[0m"

	if [ "$2" = "-u" ]; then
		echo -e "\033[95mChecking for updates... \033[0m"
		npm-check -u
	fi

	#package instalation (sudo is not required for OSX)
	if [ "$(uname)" == "Darwin" ]; then
		npm install
		npm prune
	else
		sudo npm install
		sudo npm prune
	fi

elif [ $1 = "core" ]; then

	excludeDeployMachine

	bash $TOOLS_PATH"core.bash"

elif [ $1 = "trans" ]; then

	excludeDeployMachine

	bash $TOOLS_PATH"translations.bash" find -b
	bash $TOOLS_PATH"translations.bash" find -f

elif [ $1 = "aws-cli" ]; then

	excludeDeployMachine

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
