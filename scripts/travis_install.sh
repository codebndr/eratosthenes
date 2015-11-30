#!/bin/bash
set -x
set -e

# This installation script is meant to run in Travis CI environment (currently Ubuntu 12.04)
PACKAGENAME=eratosthenes

	echo "Configuring environment for Linux"
	sudo apt-get update


	# Install dependencies
	sudo apt-get install -y apache2 libapache2-mod-php5 php-pear php5-curl php5-sqlite php5-mysql php5-memcached acl curl git
	# Enable Apache configs
	sudo a2enmod rewrite
	sudo a2enmod alias
	# Reload Apache
	sudo service apache2 reload

HTTPDUSER="root"

if [[ ${#HTTPDUSER} -eq 0 ]]; then
	echo "Failed to set HTTPDUSER"
	echo `ps aux`
	exit 1
fi

sudo mkdir -p /opt/codebender
sudo cp -r . /opt/codebender/$PACKAGENAME
sudo chown -R `whoami`:$HTTPDUSER /opt/codebender/$PACKAGENAME
cd /opt/codebender/$PACKAGENAME

#Set permissions for app/cache and app/logs

rm -rf Symfony/app/cache/*
rm -rf Symfony/app/logs/*

cd Symfony

set +x

cat app/config/parameters.yml.dist | grep -iv "github_app_name:" | grep -iv "github_app_client_id:" | grep -iv "github_app_client_secret:" | grep -iv "database_password:" > app/config/parameters.yml

echo "    database_password: hello" >> app/config/parameters.yml

echo "    github_app_name: '$GIT_LIBMGR_APP_NAME'" >> app/config/parameters.yml

echo "    github_app_client_id: '$GIT_LIBMGR_CLIENT_ID'" >> app/config/parameters.yml

echo "    github_app_client_secret: '$GIT_LIBMGR_CLIENT_SECRET'" >> app/config/parameters.yml

set -x

../scripts/install_composer.sh

../scripts/install_dependencies.sh

../scripts/warmup_cache.sh

../scripts/travis_install_apache.sh