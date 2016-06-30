#! /bin/bash
# core package installer
# author: Nicolas Pulido <nicolas.pulido@crazycake.cl>

# interrupt if error raises
set -e
echo -e "\033[94mCore Package Installer \033[0m"

# project paths
PROJECT_PATH="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_PATH="$(dirname "$PROJECT_PATH")"

TOOLS_PATH=$PROJECT_PATH"/.tools/"
FRONTEND_PATH=$PROJECT_PATH"/frontend/"
BACKEND_PATH=$PROJECT_PATH"/backend/"
#destination path
DEST_PATH=$PROJECT_PATH"/core/"

# core source
CORE_PROJECT_NAME="cc-phalcon"
# symlink to core project
CORE_SRC_PATH=$DEST_PATH$CORE_PROJECT_NAME"/"
# sub-paths
CORE_SRC_TOOLS=$CORE_SRC_PATH"tools/"
CORE_SRC_WEBPACKS=$CORE_SRC_PATH"webpacks/"

# main app bash file
MAIN_TOOL_FILE="app"

# check if cc-phalcon symlink is present
if [ ! -d $CORE_SRC_PATH ]; then
	echo -e "\033[31mCore project symlink folder not found ($CORE_SRC_PATH).\033[0m"
	exit
fi

copyToolFiles() {

	echo -e "\033[94mCopying tool script files to $CORE_SRC_TOOLS... \033[0m"
	rm -rf $TOOLS_PATH
	mkdir -p $TOOLS_PATH
	# copy tool files
	find $CORE_SRC_TOOLS -maxdepth 1 -mindepth 1 -type f -print0 | while read -d $'\0' FILE; do

		#get file props
		FILENAME=$(basename "$FILE")
		EXT="${FILENAME##*.}"
		FILENAME="${FILENAME%.*}"

		echo -e "\033[96mCopying script file $FILENAME.$EXT ... \033[0m"
		# exclude main app script file (project folder)
		if [ "$FILENAME" = "$MAIN_TOOL_FILE" ]; then
	        cp $FILE "$PROJECT_PATH/"
	    else
			cp $FILE "$TOOLS_PATH/"
		fi

	done
}

copyVoltFiles() {

	if [ -d $CORE_SRC_WEBPACKS"volt/" ]; then

		if [ -d $BACKEND_PATH"dev/volt/" ]; then
			echo -e "\033[94mCopying backend volt files ... \033[0m"
			cp -r $CORE_SRC_WEBPACKS"volt/" $BACKEND_PATH"dev/volt/"
		fi

		if [ -d $FRONTEND_PATH"dev/volt/" ]; then
			echo -e "\033[94mCopying frontend volt files ... \033[0m"
			cp -r $CORE_SRC_WEBPACKS"volt/" $FRONTEND_PATH"dev/volt/"
		fi
	fi
}

copyVueFiles() {

	if [ -d $CORE_SRC_WEBPACKS"vue/" ]; then

		if [ -d $BACKEND_PATH"dev/vue/" ]; then
			echo -e "\033[94mCopying backend vue files ... \033[0m"
			cp -r $CORE_SRC_WEBPACKS"vue/" $BACKEND_PATH"dev/vue/"
		fi

		if [ -d $FRONTEND_PATH"dev/vue/" ]; then
			echo -e "\033[94mCopying frontend vue files ... \033[0m"
			cp -r $CORE_SRC_WEBPACKS"vue/" $FRONTEND_PATH"dev/vue/"
		fi
	fi
}

copyWebpacks() {

	#check src path
	if [ -d $CORE_SRC_WEBPACKS"dist/" ]; then

		#copy webpack files
		echo -e "\033[94mCopying webpacks files to $DEST_PATH... \033[0m"
		cp -R $CORE_SRC_WEBPACKS"dist/" $DEST_PATH
	fi
}

buildCorePhar() {

	echo -e "\033[95mBuilding core phar file from $CORE_SRC_PATH... \033[0m"
	cd $CORE_SRC_PATH
	php box.phar build
	cp "$CORE_PROJECT_NAME.phar" "$DEST_PATH$CORE_PROJECT_NAME.phar"
}

# tasks
echo -e "\033[96mCore path: "$CORE_SRC_PATH" \033[0m"

# 1) tools
copyToolFiles

# 2) volt files
copyVoltFiles

# 3) vue files
copyVueFiles

# 4) webpacks folder & files (debug)
copyWebpacks

# 5) php phar core builder
buildCorePhar

# task done!
echo -e "\033[92mDone! \033[0m"
