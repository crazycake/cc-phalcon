#! /bin/bash
# Helper script for WebPay components

# interrupt if error raises
set -e

# Current path
PROJECT_PATH="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_PATH="$(dirname "$PROJECT_PATH")"
MACHINE_USER_NAME="$(whoami)"

# module components paths
KIT_FOLDER=$PROJECT_PATH"/webpay/"
OUTPUTS_FOLDER=$KIT_FOLDER"outputs/"
CGI_BINS=( "kcc32/" "kcc64/" )

# folders that apache must own
APACHE_OWNER_FOLDERS=( "log/" )
APACHE_USER_GROUP="www-data:www-data"

# OSX special cases
if [ "$(uname)" == "Darwin" ]; then
	APACHE_USER_GROUP="$(whoami):_www"
fi

# help output
scriptHelp() {
	echo -e "\033[93mWebpay KCC helper script\nValid commands:\033[0m"
	echo -e "\033[95m -kcc: App environment set up for KCC cgi-bin folder, sets owner group & perms for apache.\033[0m"
	echo -e "\033[95m -clean: Cleans KCC logs files in log subfolder.\033[0m"
	exit
}

# check args
if [ "$*" = "" ]; then
	scriptHelp
fi

if [ $1 = "-kcc" ]; then
	# print project dir
	echo -e "\033[96mProject Dir: "$PROJECT_PATH" \033[0m"
	# set default perms for folders & files (use 'xargs -I {} -0 sudo chmod xxxx {}' if args is to long)
	echo -e "\033[95mApplying user-perms for all folders and files (sudo is required)... \033[0m"

	# loop through apache owner folders
	for CGI_BIN in "${CGI_BINS[@]}"
	do
		CURRENT_PATH=$KIT_FOLDER$CGI_BIN
		echo -e "\033[96mSearching files in: "$CURRENT_PATH" \033[0m"

		if [ -d $CURRENT_PATH ]; then
			# cgi-bin folder
			if [ -d $CURRENT_PATH ]; then
				find $CURRENT_PATH -type d -print0 | xargs -0 sudo chmod 0755
				find $CURRENT_PATH -type f -print0 | xargs -0 sudo chmod 0644
				find $CURRENT_PATH -type f \( -name "*.cgi" -or -name "*.pl" -or -name "genpem" \) -print0 | xargs -0 chmod +x
			fi

			# set apache owner for specifics folders
			echo -e "\033[94mApplying app folders owner-group for apache... \033[0m"
			# loop through apache owner folders
			for APACHE_FOLDER in "${APACHE_OWNER_FOLDERS[@]}"
			do
				if [ -d $CURRENT_PATH$APACHE_FOLDER ]; then
					sudo chown -R $APACHE_USER_GROUP $CURRENT_PATH$APACHE_FOLDER
					sudo chmod -R 775 $CURRENT_PATH$APACHE_FOLDER
				fi
			done
		fi
	done

	#set perms and owner group to outputs folder
	echo -e "\033[96mPreparing directory: "$OUTPUTS_FOLDER" \033[0m"
	sudo chown -R $APACHE_USER_GROUP $OUTPUTS_FOLDER
	sudo chmod -R 775 $OUTPUTS_FOLDER

	#task done!
	echo -e "\033[92mScript successfully executed! \033[0m"

elif [ $1 = "-clean" ]; then

	echo -e "\033[95mCleaning KCC log files... \033[0m"
	find $KIT_FOLDER -type f -name "*.log" -print0 | xargs -0 sudo rm

	#task done!
	echo -e "\033[92mScript successfully executed! \033[0m"

else
	echo -e "\033[31mInvalid command\033[0m"
fi
