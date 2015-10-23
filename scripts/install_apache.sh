#!/bin/bash
set -x
set -e

PACKAGENAME=eratosthenes

# Copy the apache VirtualHost configuration file to the available site configurations directory
sudo cp /opt/codebender/$PACKAGENAME/apache-config-2.4 /etc/apache2/sites-available/codebender-$PACKAGENAME
cd /etc/apache2/sites-enabled
# Add the vhost configuration file to the enabled sites
sudo ln -s ../sites-available/codebender-$PACKAGENAME 00-codebender.conf
# Reload apache in order to enable the new configurations
sudo service apache2 reload
