#!/bin/sh
sudo apt-get install -y unzip
cd ~
# Download the builtin Arduino libraries and examples from codebender's Github
wget https://github.com/codebendercc/arduino-library-files/archive/master.zip
unzip -q master.zip
sudo cp -r arduino-library-files-master /opt/codebender/codebender-arduino-library-files
rm master.zip
# Create the external libraries directory
sudo mkdir /opt/codebender/codebender-external-library-files
sudo mkdir /opt/codebender/codebender-external-library-files-new

# Give apache the correct permissions in order to write to the libraries directories
sudo chown -R `whoami`:$HTTPDUSER /opt/codebender/codebender-arduino-library-files
sudo chown -R `whoami`:$HTTPDUSER /opt/codebender/codebender-external-library-files
sudo chown -R `whoami`:$HTTPDUSER /opt/codebender/codebender-external-library-files-new
cd -
