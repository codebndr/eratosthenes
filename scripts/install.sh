#!/bin/bash
set -x
set -e

PACKAGENAME=eratosthenis

sudo apt-get update
sudo locale-gen en_US.UTF-8
sudo apt-get install -y php5-intl

sudo apt-get install -y apache2 libapache2-mod-php5 php-pear php5-curl php5-sqlite acl curl git

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

sudo cp /opt/codebender/$PACKAGENAME/apache-config /etc/apache2/sites-available/codebender-eratosthenis
cd /etc/apache2/sites-enabled
sudo ln -s ../sites-available/codebender-eratosthenis codebender-eratosthenis

sudo a2enmod rewrite
sudo a2enmod alias
sudo service apache2 restart

#Set permissions for app/cache and app/logs

rm -rf Symfony/app/cache/*
rm -rf Symfony/app/logs/*


cd /opt/codebender/$PACKAGENAME/Symfony
sudo curl -s http://getcomposer.org/installer | sudo php
sudo php composer.phar install


sudo rm -rf /opt/codebender/$PACKAGENAME/Symfony/app/cache/*
sudo rm -rf /opt/codebender/$PACKAGENAME/Symfony/app/logs/*

sudo dd if=/dev/zero of=/opt/codebender/$PACKAGENAME/cache-fs bs=1024 count=0 seek=200000
sudo dd if=/dev/zero of=/opt/codebender/$PACKAGENAME/logs-fs bs=1024 count=0 seek=200000

yes | sudo mkfs.ext4 /opt/codebender/$PACKAGENAME/cache-fs
yes | sudo mkfs.ext4 /opt/codebender/$PACKAGENAME/logs-fs

echo "/opt/codebender/$PACKAGENAME/cache-fs /opt/codebender/$PACKAGENAME/Symfony/app/cache ext4 loop,acl 0 0" | sudo tee -a /etc/fstab > /dev/null 2>&1
echo "/opt/codebender/$PACKAGENAME/logs-fs /opt/codebender/$PACKAGENAME/Symfony/app/logs ext4 loop,acl 0 0" | sudo tee -a /etc/fstab > /dev/null 2>&1

sudo mount /opt/codebender/$PACKAGENAME/Symfony/app/cache/
sudo mount /opt/codebender/$PACKAGENAME/Symfony/app/logs/

sudo rm -rf /opt/codebender/$PACKAGENAME/Symfony/app/cache/*
sudo rm -rf /opt/codebender/$PACKAGENAME/Symfony/app/logs/*

sudo setfacl -R -m u:www-data:rwX -m u:ubuntu:rwX /opt/codebender/$PACKAGENAME/Symfony/app/cache /opt/codebender/$PACKAGENAME/Symfony/app/logs
sudo setfacl -dR -m u:www-data:rwx -m u:ubuntu:rwx /opt/codebender/$PACKAGENAME/Symfony/app/cache /opt/codebender/$PACKAGENAME/Symfony/app/logs

