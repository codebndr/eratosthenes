#!/bin/bash

echo "Configuring database & fixture data"
php app/console doctrine:database:drop --force
php app/console doctrine:database:create
php app/console doctrine:schema:create
yes | php app/console doctrine:fixtures:load
php app/console codebender:library_files:install
