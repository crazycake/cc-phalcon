#! /bin/bash
# GetText helper script. Finds translations & compile po files.
# author: Nicolas Pulido <nicolas.pulido@crazycake.tech>

# interrupt if error raises
set -e

# current path
PROJECT_PATH="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_PATH="$(dirname "$PROJECT_PATH")"

# app namespace
APP_NAME=${PWD##*/}

# help output
help() {
	echo -e "\033[93m > "$APP_NAME" translations CLI \033[0m"
	echo -e "\033[95m build: build po files in app folder. \033[0m"
	echo -e "\033[95m find: find for new translations in app folder. \033[0m"
	exit
}

# commands
case "$1" in

# compile and generate mo files
build)

	echo -e "\033[94mSearching for .po files in langs folder \033[0m"

	# generate .mo files in LC_MESSAGES subfolder for each lang code
	docker exec -it $APP_NAME bash -c \
		'find /var/www/app/langs/ -type f -name "*.po" | while read PO_FILE ; do

			CODE=`basename "$PO_FILE" .po`
			TARGET_DIR="/var/www/app/langs/$CODE/LC_MESSAGES"
			mkdir -p "$TARGET_DIR"
			echo -e "\033[94mCompiling language file: $CODE \033[0m"
			msgfmt -o "$TARGET_DIR/app.mo" "$PO_FILE"
		done'

	echo -e "\033[92mDone! \033[0m"
	;;

# search and generate pot files
find)

	echo -e "\033[95mCompiling volt files from container... \033[0m"

	# execute volt compailer in container
	docker exec -it $APP_NAME bash -c 'php /var/www/app/cli/cli.php main compileVolt'

	echo -e "\033[95mSearching for keyword 'trans' in php files... \033[0m"

	# find files (exclude some folders)
	docker exec -it $APP_NAME bash -c 'find /var/www/app/ /var/www/storage/cache/ -type f -name "*.php" > .translations'

	# generate pot file with xgettext and clean temp file
	docker exec -it $APP_NAME bash -c \
		'xgettext \
				--output app/langs/trans.pot \
				--directory app/ \
				--language="php" \
				--from-code=UTF-8 \
				--keyword='trans' \
				--keyword='transPlural:1,2' \
				--package-version=`date -u +"%Y-%m-%dT%H:%M:%SZ"` \
				--package-name="crazycake" \
				--copyright-holder="CrazyCake" \
				--no-wrap -f .translations && \
		rm .translations && \
		find /var/www/storage/cache -type f \( ! -iname ".*" \) -print0 | xargs -0 rm'

	# merge po file
	docker exec -it $APP_NAME bash -c \
		'find /var/www/app/langs/ -mindepth 1 -maxdepth 1 -type d | while read CODE_DIR; do

			cd "$CODE_DIR"
			CODE=`basename "$CODE_DIR"`

			if [ -f "$CODE".po ]; then
				msgmerge -U "$CODE".po ../trans.pot
			else
				msginit -i ../trans.pot --no-translator -l "$CODE"
			fi
		done'

	echo -e "\033[92mDone! \033[0m"
	;;

# defaults
*)
	help
	;;
esac
