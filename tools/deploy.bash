#! /bin/bash
# Deploy script for testing & production environments
# author: Nicolas Pulido <nicolas.pulido@crazycake.cl>

# owner user name
USER_NAME="$(whoami)"

# project paths
TOOLS_PATH="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_PATH="$(dirname "$TOOLS_PATH")"

# Deploy settings
SSH_DIR="$HOME/.ssh"

# environment vars
DEPLOY_REMOTE_PATH=""

TESTING_KEY=""
TESTING_HOST=""
TESTING_BRANCH="testing"

STAGING_KEY=""
STAGING_HOST=""
STAGING_BRANCH="staging"

PRODUCTION_KEY=""
PRODUCTION_HOST=""
PRODUCTION_BRANCH="production"

TESTING_SSH_CMD=""
STAGING_SSH_CMD=""
PRODUCTION_SSH_CMD=""

# date NOW
NOW=$(date +"%d-%m-%Y %R")

# load environment file if exists
if [ -f "$PROJECT_PATH/.env" ]; then
	source "$PROJECT_PATH/.env"
fi

# help output
scriptHelp() {
	echo -e "\033[93mWebapp deploy script [$APP_ENV].\nValid options:\033[0m"
	echo -e "\033[95m -t <option>: deploy to testing environment. \033[0m"
    echo -e "\033[95m -s <option>: deploy to staging environment (branch staging requried). \033[0m"
    echo -e "\033[95m -p <option>: deploy to production environment (branch production requried). \033[0m"
	echo -e "\033[93m * Option can be '-m' for database migrations. \033[0m"
	exit
}

# read env file
checkEnvVars() {

	#check values
	if [ "$DEPLOY_REMOTE_PATH" = "" ] || [ "$TESTING_KEY" = "" ] || [ "$TESTING_HOST" = "" ]; then
		echo -e "\033[31mInvalid environment file struct. Check that file must end with an empty space.\033[0m"
		exit
	fi

	#set values
	TESTING_SSH_CMD="$SSH_DIR/$TESTING_KEY $TESTING_HOST"
	STAGING_SSH_CMD="$SSH_DIR/$STAGING_KEY $STAGING_HOST"
	PRODUCTION_SSH_CMD="$SSH_DIR/$PRODUCTION_KEY $PRODUCTION_HOST"
}

# check onwer machine (basic AWS security)
if [ $USER_NAME = "ubuntu" ]; then

	#check if directory exists
	if [ "$1" = "" ] || [ ! -d "$1" ]; then
		echo -e "\033[31mRemote folder not found!\033[0m"
	else
		DEPLOY_REMOTE_PATH="$1"
	fi

	echo -e "\033[94mMaking deploy in $DEPLOY_REMOTE_PATH...\033[0m"
	#go to project path
	cd $DEPLOY_REMOTE_PATH
	echo -e "\033[94mCurrent dir: $(pwd) \033[0m"

	# GIT properties
	CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
	# fetch branch changes
	git fetch origin "$CURRENT_BRANCH"

	# commits history
	echo -e "\033[94mCommit history:\033[0m"
	git log -n 4 "$CURRENT_BRANCH" --pretty=format:'%Cblue %H (%ai) -> %ce: %s %Creset'

	# merge changes
	git merge

	#database migrations
	if [ "$2" = "-m" ] || [ "$2" = "-mc" ]; then
		echo -e "\033[31mExecuting DB migration...\033[0m"
		bash app.bash phinx migrate
	fi

	#composer update
	if [ "$2" = "-c" ] || [ "$2" = "-mc" ]; then
		echo -e "\033[31mUpdating composer dependencies...\033[0m"
		bash app.bash composer
	fi

	echo -e "\033[92mDeploy done! \033[0m"
	exit
fi

# check args
if [ "$*" = "" ]; then
	scriptHelp
fi

if [ "$1" = "-t" ] || [ "$1" = "-s" ] || [ "$1" = "-p" ]; then

	checkEnvVars

	# GIT properties
	CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"

	echo -e "\033[94mExecuting GIT tasks...\033[0m"
	# commititng changes
	echo -e "\033[94mNew commit...\033[0m"

	# exec remote command on machine
	if [ "$1" = "-t" ]; then
		#commit
		git commit -a -m "BUILD TESTING [$NOW]"
		# checkout to testing
		echo -e "\033[95mMerging to testing... \033[0m"
		git checkout $TESTING_BRANCH
		# make a pull for colaborative deploys
		git pull

	elif [ "$1" = "-s" ]; then
		#commit
		git commit -a -m "BUILD STAGING [$NOW]"
		# checkout to staging
		echo -e "\033[95mMerging to staging... \033[0m"
		git checkout $STAGING_BRANCH
		# make a pull for colaborative deploys
		git pull

	else
		#commit
		git commit -a -m "BUILD PRODUCTION [$NOW]"
		# checkout to production
		echo -e "\033[95mMerging to production... \033[0m"
		git checkout $PRODUCTION_BRANCH
	fi

	# merge
	git merge $CURRENT_BRANCH --no-edit

	echo -e "\033[95mPushing to the cloud... \033[0m"
	git push

	echo -e "\033[95mChecking out to branch $CURRENT_BRANCH \033[0m"
	git checkout $CURRENT_BRANCH

	echo -e "\033[95mDeploy path: $DEPLOY_REMOTE_PATH $1 $2 \033[0m"

	# conenct to remote machine and execute script
	if [ "$1" = "-t" ]; then
		echo -e "\033[95mSSH: $TESTING_SSH_CMD \033[0m"
		ssh -i $TESTING_SSH_CMD 'bash -s' -- < ./deploy.bash $DEPLOY_REMOTE_PATH "$2"
	elif [ "$1" = "-s" ]; then
		echo -e "\033[95mSSH: $STAGING_SSH_CMD \033[0m"
		ssh -i $STAGING_SSH_CMD 'bash -s' -- < ./deploy.bash $DEPLOY_REMOTE_PATH "$2"
	else
		echo -e "\033[95mSSH: $PRODUCTION_SSH_CMD \033[0m"
		ssh -i $PRODUCTION_SSH_CMD 'bash -s' -- < ./deploy.bash $DEPLOY_REMOTE_PATH "$2"
	fi

	# task done!
	echo -e "\033[92mDone! \033[0m"

else
	echo -e "\033[31mInvalid command\033[0m"
fi
