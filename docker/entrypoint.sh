#!/bin/sh
set -eu

case "${PORT:-10000}" in
    ""|*[!0-9]*)
        echo "PORT must be a valid numeric port." >&2
        exit 1
        ;;
esac

sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

attachment_directory="/var/www/html/uploads/ticket_attachments"
mkdir -p "$attachment_directory"
chown www-data:www-data "$attachment_directory"
chmod 0750 "$attachment_directory"

exec "$@"
