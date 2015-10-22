#!/bin/bash

echo "Installing Symfony2 and its dependencies with composer"
curl -s http://getcomposer.org/installer | php
php composer.phar install
