#! /bin/bash
# Tar Helper Script
# author: Nicolas Pulido <nicolas.pulido@crazycake.cl>

# stop execution for exceptions
set -e

#Current path
PROJECT_PATH="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_PATH="$(dirname "$PROJECT_PATH")"

# help output
scriptHelp() {
    echo -e "\033[93mWebapp Tar helper script\nValid commands:\033[0m"
    echo -e "\033[95m compress <path>: Compress an input folder (tar.gz file).\033[0m"
    exit
}

# commands
case "$1" in

compress)

    if [ ! -d "$2" ]; then
        echo -e "\033[31mInput directory not found.\033[0m"
    	exit
    fi

    # print project dir
    echo -e "\033[96mProject Dir: "$PROJECT_PATH" \033[0m"

    echo -e "\033[95mCompressing input folder: $2... \033[0m"
    tar -zcvf output.tar.gz "$2"

    #task done!
    echo -e "\033[92mDone! \033[0m"
    ;;

#default
*)
	scriptHelp
    ;;
esac
