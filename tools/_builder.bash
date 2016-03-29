#! /bin/bash
# PhalconPHP app builder script [extended functions]

# interrupt if error raises
set -e
echo -e "\033[94mPhalcon App Builder... \033[0m"

# current path
PROJECT_PATH="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_PATH="$(dirname "$PROJECT_PATH")"
# project paths
TOOLS_PATH=$PROJECT_PATH"/.tools/"
FRONTEND_PATH=$CURRENT_PATH"/frontend/"
BACKEND_PATH=$CURRENT_PATH"/backend/"

buildTask() {

	# check file is present
	if [ ! -f $TOOLS_PATH"_translations.bash" ]; then
		echo -e "\033[31mTranslations tools are required.\033[0m"
		exit
	fi

	# backend
	if [ -d $BACKEND_PATH"dev/" ]; then
		echo -e "\033[95mExecuting build tasks in backend... \033[0m"
		gulp build -m backend
	fi

	# frontend
	if [ -d $FRONTEND_PATH"dev/" ]; then
		echo -e "\033[95mExecuting build tasks in frontend... \033[0m"
		gulp build -m frontend
	fi

	echo -e "\033[95mCompiling frontend translations... \033[0m"

	# translations for backend & frontend
	cd $PROJECT_PATH
	bash $TOOLS_PATH"_translations.bash" -c -b
	bash $TOOLS_PATH"_translations.bash" -c -f
}
