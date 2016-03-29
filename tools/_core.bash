#! /bin/bash
# core package installer for dev bulding

# interrupt if error raises
set -e
echo -e "\033[94mCore Package Installer... \033[0m"

# project paths
PROJECT_PATH="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
TOOLS_PATH=$PROJECT_PATH"/.tools/"
PACKAGES_PATH=$PROJECT_PATH"/packages/"
WEBPACKS_PATH=$PROJECT_PATH"/webpacks/"
FRONTEND_PATH=$CURRENT_PATH"/frontend/"
BACKEND_PATH=$CURRENT_PATH"/backend/"

# cc-phalcon paths
CORE_NAMESPACE="cc-phalcon"
CORE_PATH=$PACKAGES_PATH$CORE_NAMESPACE"/"
CORE_TOOLS_PATH=$CORE_PATH"/tools/"
CORE_WEBPACKS_PATH=$CORE_PATH"/webpacks/"

# main app bash file
MAIN_TOOL_FILE="_app"

# check if cc-phalcon symlink is present
if [ ! -d $CORE_PATH ]; then
	echo -e "\033[31mCore package symlink folder not found ($CORE_PATH).\033[0m"
	exit
fi

echo -e "\033[96mProject Path: "$PROJECT_PATH" \033[0m"
echo -e "\033[96mCore Path: "$CORE_PATH" \033[0m"
echo -e "\033[94mCopying packages files... \033[0m"

# 1) tools
mkdir -p $TOOLS_PATH
# copy tool files
find $CORE_TOOLS_PATH -maxdepth 1 -mindepth 1 -type f -print0 | while read -d $'\0' FILE; do

	#get file props
	FILENAME=$(basename "$FILE")
	EXT="${FILENAME##*.}"
	FILENAME="${FILENAME%.*}"

	echo -e "\033[96mCopying file $FILENAME.$EXT ... \033[0m"
	# exclude main app script file (project folder)
    if [ "$FILENAME" = "$MAIN_TOOL_FILE" ]; then
        cp $FILE "$PROJECT_PATH/"
    else
		cp $FILE "$TOOLS_PATH/"
	fi

done

# 2) volt files
if [ -d $BACKEND_PATH"app/views/" ]; then
	echo -e "\033[96mCopying backend volt files ... \033[0m"
	cp -r $CORE_WEBPACKS_PATH"volt/" $BACKEND_PATH"app/views/"
fi

if [ -d $FRONTEND_PATH"app/views/" ]; then
	echo -e "\033[96mCopying frontend volt files ... \033[0m"
	cp $CORE_WEBPACKS_PATH"volt/" $FRONTEND_PATH"app/views/"
fi

# 3) webpacks files
mkdir -p $WEBPACKS_PATH"dist/"
mkdir -p $WEBPACKS_PATH"private/"
#copy webpack files
echo -e "\033[96mCopying webpacks files to $PACKAGES_PATH... \033[0m"
cp -r $CORE_WEBPACKS_PATH"dist/" $WEBPACKS_PATH"dist/"
cp -r $CORE_WEBPACKS_PATH"private/" $WEBPACKS_PATH"private/"

# 4) php phar core builder
# build the PHP phar file and copy to packages folder
echo -e "\033[95mBuilding core phar file from $CORE_PATH... \033[0m"
cd $CORE_PATH
php box.phar build
cp "$CORE_NAMESPACE.phar" "$PACKAGES_PATH$CORE_NAMESPACE.phar"

# task done!
echo -e "\033[92mScript successfully executed! \033[0m"
