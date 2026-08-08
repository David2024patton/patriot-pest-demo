#!/bin/sh
set -e

# Self-heal volume permissions: ensure the www-data user (php-fpm) can
# create/write the SQLite database file and log files at runtime.
# This is a no-op when permissions are already correct but saves us from
# "read-only database" errors after Docker volume mounts reset ownership.
chown -R ppc:ppc /app/database /app/storage 2>/dev/null || true
chmod -R 777 /app/database /app/storage 2>/dev/null || true

exec supervisord -c /etc/supervisord.conf
