#! /bin/bash
## Tar Helper Script

# stop execution for exceptions
set -e

#Current path
PROJECT_PATH="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_PATH="$(dirname "$PROJECT_PATH")"

# help output
scriptHelp() {
    echo -e "\033[93mWebapp Tar helper script\nValid commands:\033[0m"
    echo -e "\033[95m compress <path>: Compress an input folder (tar file).\033[0m"
    exit
}

# check args
if [ "$*" = "" ]; then
    scriptHelp
fi

if [ $1 = "compress" ]; then

    if [ ! -d "$2" ]; then
        echo -e "\033[31mInput directory not found.\033[0m"
    	exit
    fi

    # print project dir
    echo -e "\033[96mProject Dir: "$PROJECT_PATH" \033[0m"

    echo -e "\033[95mCompressing input folder: $2... \033[0m"
    tar -zcvf output.tar.gz "$2"

    #task done!
    echo -e "\033[92mScript successfully executed! \033[0m"

else
    echo -e "\033[31mInvalid command\033[0m"
fi
