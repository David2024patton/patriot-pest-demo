#!/bin/sh
# ============================================================
# Patriot Pest Control - container entrypoint
# 0. Fix the database volume ownership so the app can write it.
#    The Dockerfile chowns /app/database at image build time, but
#    a mounted volume overrides that with host ownership on boot.
#    php-fpm runs as www-data (uid 82, the base image default), so
#    the volume must be world-writable (777) for the app to create
#    and write the SQLite file. Re-applied BEFORE seeding (fixes an
#    existing volume) and AFTER seeding (the seed runs as root and
#    creates the DB file, which must also be writable by www-data).
#    Guarded: only as root. Idempotent: safe on every boot. Tolerant:
#    a failed fix (read-only mount) still boots so pages keep serving.
# 1. Seed the app DB if it is missing or empty (idempotent; the
#    seed copy ships zero em dashes per ORDER 2). Runs on every
#    boot so the DB self-heals across deploys and volume resets.
# 2. Hand off to supervisord (php-fpm + nginx).
# ============================================================
set -e

fix_database_perms() {
  chown -R ppc:ppc /app/database 2>/dev/null || true
  chmod -R 777 /app/database 2>/dev/null || true
}

if [ "$(id -u)" -eq 0 ]; then
  echo "[entrypoint] fixing database volume permissions (root)..."
  if fix_database_perms; then
    echo "[entrypoint] database volume permissions OK"
  else
    echo "[entrypoint] volume permission fix failed, continuing (mount may be read-only)"
  fi
else
  echo "[entrypoint] not root, skipping database volume permission fix"
fi

if [ -f /app/bin/seed.php ]; then
  echo "[entrypoint] seeding database (idempotent)..."
  php /app/bin/seed.php || echo "[entrypoint] seed failed, continuing"
fi

# The seed runs as root and (re)creates the DB file, so re-apply the
# permissions after seeding or the php-fpm worker (www-data) cannot
# write the file even though the directory is correct.
if [ "$(id -u)" -eq 0 ]; then
  echo "[entrypoint] re-applying database volume permissions after seed (root)..."
  fix_database_perms || echo "[entrypoint] post-seed permission fix failed, continuing"
fi

echo "[entrypoint] starting supervisord"
exec /usr/bin/supervisord -c /etc/supervisord.conf
