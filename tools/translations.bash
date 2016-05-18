#! /bin/bash
# Phalcon getText script helper for finding translations & compiling po files

# interrupt if error raises
set -e

# current path
PROJECT_PATH="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_PATH="$(dirname "$PROJECT_PATH")"
# App Name
APP_NAME=${PWD##*/}
APP_NAME="${APP_NAME/-webapp/}"
APP_NAMESPACE="$(echo "$APP_NAME" | tr '[:upper:]' '[:lower:]')"

# cc-phalcon directory
APP_CORE_PATH=$PROJECT_PATH"/packages/cc-phalcon/"

# translation filenames
MO_FILE=$APP_NAMESPACE".mo"
TEMP_FILE="temp_file"

# load environment file if exists
if [ -f "$PROJECT_PATH/.env" ]; then
	source "$PROJECT_PATH/.env"
fi

# help output
scriptHelp() {
	echo -e "\033[93m WebApp Translations Script\nValid actions:\033[0m"
	echo -e "\033[95m build <module> : build po files in app folder. \033[0m"
    echo -e "\033[95m find <module> : Find for new translations in app folder. \033[0m"
	echo -e "\033[93m * Module option can be '-b' or '-f' (backend or frontend). \033[0m"
	exit
}

# check machine
if [ ! $APP_ENV = "local" ]; then
	echo -e "\033[31mThis script is for local environment only.\033[0m"
	exit
# set module
elif [ "$2" = "-b" ]; then
	MODULE_PATH=$PROJECT_PATH"/backend/"
elif [ "$2" = "-f" ]; then
	MODULE_PATH=$PROJECT_PATH"/frontend/"
else
	echo -e "\033[31mInvalid module option.\033[0m"
	scriptHelp
fi

# Module properties
APP_PATH=$MODULE_PATH"app/"
APP_LANGS_PATH=$APP_PATH"langs/"

# check that directories exists
if [ ! -d $MODULE_PATH ] || [ ! -d $APP_LANGS_PATH ]; then
	exit
fi

# compile and generate mo files
if [ "$1" = "build" ]; then

	echo -e "\033[94mSearching for .po files in ($APP_NAMESPACE) $APP_LANGS_PATH \033[0m"

	# search .po files
	LANG_FILES=`find "$APP_LANGS_PATH" -type f -name '*.po'`

	# generate .mo files in LC_MESSAGES subfolder for each lang code
	echo "$LANG_FILES" | while read PO_FILE ; do
		CODE=`basename "$PO_FILE" .po`
		TARGET_DIR="$APP_LANGS_PATH$CODE/LC_MESSAGES"
		mkdir -p "$TARGET_DIR"
		echo -e "\033[94mCompiling language file: $CODE \033[0m"
		msgfmt -o "$TARGET_DIR/$MO_FILE" "$PO_FILE"
	done

	# task done!
	echo -e "\033[92mDone! \033[0m"

# search and generate pot files
elif [ "$1" = "find" ]; then

	echo -e "\033[94mSearching for .php files in $APP_PATH  \033[0m"

	# find files (exclude some folders)
	find $APP_PATH $APP_CORE_PATH -type f -not -path "*app/logs*" > $TEMP_FILE
	# generate pot file with xgettext
	xgettext -o $APP_LANGS_PATH"trans.pot" \
		-d $APP_PATH -L php --from-code=UTF-8 \
		-k'trans' -k'transPlural:1,2' \
		--copyright-holder="CrazyCake" \
		--package-name="crazycake" \
		--package-version='`date -u +"%Y-%m-%dT%H:%M:%SZ"`' \
		--no-wrap -f $TEMP_FILE

	# delete temp file
	rm $TEMP_FILE

	# find code langs
	CODE_LANGS=`find "$APP_LANGS_PATH" -mindepth 1 -maxdepth 1 -type d`

	# merge po file
	echo "$CODE_LANGS" | while read CODE_DIR ; do
		cd "$CODE_DIR"
		CODE=`basename "$CODE_DIR"`
		if [ -f "$CODE".po ]; then
			echo -e "\033[94mUpdating new entries for lang code: $CODE  \033[0m"
			sudo msgmerge -U "$CODE".po ../trans.pot
		else
			echo -e "\033[94mGenerating new entries for lang code: $CODE  \033[0m"
			sudo msginit -i ../trans.pot --no-translator -l "$CODE"
		fi
	done

	# task done!
	echo -e "\033[92mDone! \033[0m"

else
	echo -e "\033[31mArgument option is missing\033[0m"
	scriptHelp
fi
