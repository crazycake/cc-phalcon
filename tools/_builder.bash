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
FRONTEND_PATH=$PROJECT_PATH"/frontend/"
BACKEND_PATH=$PROJECT_PATH"/backend/"

# load environment file if exists
if [ -f "$PROJECT_PATH/.env" ]; then
	source "$PROJECT_PATH/.env"
fi

buildTask() {

	# GIT properties
	CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"

	# environment protection (prevents env merges)
	if [ "testing" = "$CURRENT_BRANCH" ] || [ "staging" = "$CURRENT_BRANCH" ] || [ "production" = "$CURRENT_BRANCH" ]; then

		echo -e "\033[31mWarning your current branch is: $CURRENT_BRANCH. \033[0m"
		exit
	fi

	# gulp build tasks backend
	if [ -d $BACKEND_PATH"dev/" ]; then
		echo -e "\033[95mExecuting build tasks in backend... \033[0m"
		gulp build -m backend
	fi

	# gulp build tasks frontend
	if [ -d $FRONTEND_PATH"dev/" ]; then
		echo -e "\033[95mExecuting build tasks in frontend... \033[0m"
		gulp build -m frontend
	fi

	# check file is present
	if [ ! -f $TOOLS_PATH"_translations.bash" ]; then
		echo -e "\033[31mTranslations tools are required.\033[0m"
		exit
	fi

	# translations for backend & frontend
	echo -e "\033[95mCompiling frontend translations... \033[0m"

	cd $PROJECT_PATH

	bash $TOOLS_PATH"_translations.bash" build -b
	bash $TOOLS_PATH"_translations.bash" build -f

	cd $PROJECT_PATH

	echo -e "\033[95mChecking CDN_SYNC env var... \033[0m"

	if [ "$CDN_SYNC_BACKEND" = "1" ]; then
		bash _app.bash aws-cdn -b
	fi

	if [ "$CDN_SYNC_FRONTEND" = "1" ]; then
		bash _app.bash aws-cdn -f
	fi
}
