#!/bin/sh
# ============================================================
# Patriot Pest Control - container entrypoint
# 1. Seed the app DB if it is missing or empty (idempotent; the
#    seed copy ships zero em dashes per ORDER 2). Runs on every
#    boot so the DB self-heals across deploys and volume resets.
# 2. Hand off to supervisord (php-fpm + nginx).
# ============================================================
set -e

if [ -f /app/bin/seed.php ]; then
  echo "[entrypoint] seeding database (idempotent)..."
  php /app/bin/seed.php || echo "[entrypoint] seed failed, continuing"
fi

echo "[entrypoint] starting supervisord"
exec /usr/bin/supervisord -c /etc/supervisord.conf
