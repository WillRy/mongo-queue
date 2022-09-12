#!/bin/bash

dockerize -wait tcp://db:3306 -timeout 90s

chown -R www-data:www-data .

composer install

docker-php-entrypoint 

apache2-foreground
