#!/usr/bin/env bash
export PHP_IDE_CONFIG="serverName=gmail.local"
export XDEBUG_CONFIG="remote_connect_back=0 idekey=netbeans-xdebug remote_host=127.0.0.1"
php -dxdebug.remote_enable=1 -dxdebug.remote_autostart=1 "$@"