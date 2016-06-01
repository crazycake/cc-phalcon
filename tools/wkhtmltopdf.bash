#! /bin/bash
# Script file for wkhtmltopdf installation
# author: Nicolas Pulido <nicolas.pulido@crazycake.cl>

# interrupt if error raises
set -e

# check platform
if [ "$(uname)" == "Darwin" ]; then
    echo -e "\033[95mInstall wkhtmltopdf via binary file. URL: http://wkhtmltopdf.org/downloads.html \033[0m"
    exit
else
    # install steps
    echo -e "\033[95mInstalling wkhtmltopdf dependencies... \033[0m"
    # essential dependencies
    sudo apt-get install -y --force-yes openssl build-essential xorg libssl-dev xvfb
    echo -e "\033[95mAdding wkhtmltopdf repository... \033[0m"
    sudo add-apt-repository ppa:ecometrica/servers
    sudo apt-get update -y
    echo -e "\033[95mInstalling wkhtmltopdf library... \033[0m"
    sudo apt-get install -y --force-yes wkhtmltopdf
    echo -e "\033[95mCreating custom wkhtmltopdf executable... \033[0m"
    sudo echo 'xvfb-run -a -s "-screen 0 640x480x16" wkhtmltopdf "$@"' > $HOME/wkhtmltopdf.sh
    sudo mv $HOME/wkhtmltopdf.sh /usr/local/bin/wkhtmltopdf.sh
    sudo chmod a+x /usr/local/bin/wkhtmltopdf.sh
    echo -e "\033[93mLibrary installed! Test with:\033[0m"
    echo -e "\033[95m /usr/local/bin/wkhtmltopdf.sh --lowquality http://www.google.com test.pdf \033[0m"
    echo -e "\033[92mDone! \033[0m"
fi
