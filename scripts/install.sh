#!/bin/bash
set -x
set -e

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

# Install dependencies
sudo apt-get install -y apache2 libapache2-mod-php5 php-pear php5-xdebug php5-curl php5-sqlite acl curl git

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

cp app/config/parameters.yml.dist app/config/parameters.yml

../scripts/install_dependencies.sh

../scripts/install_composer.sh

../scripts/warmup_cache.sh

../scripts/install_apache.sh

