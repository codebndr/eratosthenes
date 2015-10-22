#!/bin/bash
set -x
set -e

# In order to install this Symfony app, you need to run this script
# from the root directory of the prohect like 'scripts/install.sh'
# In order to successfully run the tests, you will have to set the
# env vars `$GIT_LIBMGR_APP_NAME`, `$GIT_LIBMGR_CLIENT_ID` and
# `$GIT_LIBMGR_CLIENT_SECRET` to valid values

PACKAGENAME=eratosthenes

if [[ "$OSTYPE" != "linux-gnu" ]]; then
	echo "Only GNU linux is supported"
	exit 1
fi

echo "Configuring environment for Linux"
sudo apt-get update

# Ubuntu Server (on AWS?) lacks UTF-8 for some reason. Give it that
sudo locale-gen en_US.UTF-8
# Make sure we have up-to-date stuff
sudo apt-get install -y php5-intl

# Change mysql default passwords to `hello`, then install mysql-server
sudo debconf-set-selections <<< 'mysql-server mysql-server/root_password password hello'
sudo debconf-set-selections <<< 'mysql-server mysql-server/root_password_again password hello'
sudo apt-get -y install mysql-server

# Install dependencies
sudo apt-get install -y apache2 libapache2-mod-php5 php5-mysql php-pear php5-xdebug php5-curl php5-sqlite acl curl git

# Enable Apache configs
sudo a2enmod rewrite
sudo a2enmod alias

# Restart Apache
sudo service apache2 reload


# Set Max nesting lvl to something Symfony is happy with
export ADDITIONAL_PATH=`php -i | grep -F --color=never 'Scan this dir for additional .ini files'`
echo 'xdebug.max_nesting_level=256' | sudo tee ${ADDITIONAL_PATH:42}/symfony2.ini

HTTPDUSER=`ps aux | grep -E '[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx' | grep -v root | head -1 | cut -d\  -f1`

if [[ ${#HTTPDUSER} -eq 0 ]]; then
	echo "Failed to set HTTPDUSER"
	echo `ps aux`
	exit 1
fi

sudo mkdir -p /opt/codebender
sudo cp -r . /opt/codebender/$PACKAGENAME
sudo chown -R `whoami`:$HTTPDUSER /opt/codebender/$PACKAGENAME
cd /opt/codebender/$PACKAGENAME

#Set permissions for app/cache and app/logs, after they are created (pre-existing contents will be deleted)
rm -rf Symfony/app/cache/*
rm -rf Symfony/app/logs/*

mkdir -p `pwd`/Symfony/app/cache/
mkdir -p `pwd`/Symfony/app/logs/

sudo rm -rf `pwd`/Symfony/app/cache/*
sudo rm -rf `pwd`/Symfony/app/logs/*

sudo setfacl -R -m u:www-data:rwX -m u:`whoami`:rwX `pwd`/Symfony/app/cache `pwd`/Symfony/app/logs
sudo setfacl -dR -m u:www-data:rwx -m u:`whoami`:rwx `pwd`/Symfony/app/cache `pwd`/Symfony/app/logs

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

../scripts/install_apache.sh

