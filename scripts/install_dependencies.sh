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
cd -
