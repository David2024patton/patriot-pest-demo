#!/bin/bash
# ============================================================
# Patriot Pest Control — Hostinger shared-hosting deploy
# test.patriotpest.pro (the WORKING path; Dokploy is dead, see deploy.sh)
#
# Stages the app into a flattened bundle (public/ -> root, index.php
# bootstrap path patched), uploads it via TUS, then health-checks.
#
# Usage:
#   ./deploy/hostinger-deploy.sh                  # stage + upload + verify
#   PREFIX=test ./deploy/hostinger-deploy.sh      # target subdomain
#   BUNDLE=C:/tmp/ppc-test-deploy ./deploy/hostinger-deploy.sh  # reuse staged bundle
#
# Secrets: the production .env is NOT in the repo (gitignored). Point
# ENV_FILE at the test env (default C:/tmp/ppc-test-deploy/.env).
# ============================================================
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PREFIX="${PREFIX:-test}"
BUNDLE="${BUNDLE:-C:/tmp/ppc-test-deploy}"
ENV_FILE="${ENV_FILE:-C:/tmp/ppc-test-deploy/.env}"
DB_FILE="${DB_FILE:-$REPO_ROOT/database/patriot.db}"   # gitignored (PII) — must exist locally
HTACCESS="$REPO_ROOT/deploy/htaccess.production"

log() { echo "[$(date '+%H:%M:%S')] $*"; }

stage_bundle() {
    log "staging bundle -> $BUNDLE"
    rm -rf "$BUNDLE"
    mkdir -p "$BUNDLE/database"

    # app code + runtime dirs (modular first: no monolith, no junk)
    cp -r "$REPO_ROOT/app"        "$BUNDLE/app"
    cp -r "$REPO_ROOT/bin"        "$BUNDLE/bin"
    cp -r "$REPO_ROOT/storage"    "$BUNDLE/storage"
    cp -r "$REPO_ROOT/templates"  "$BUNDLE/templates"
    cp -r "$REPO_ROOT/public/assets" "$BUNDLE/assets"
    cp -r "$REPO_ROOT/public/cost"   "$BUNDLE/cost"   # marketing cost explainer standalone pages

    # front controller + router (public/ docroot is flattened to the subdomain root)
    cp "$REPO_ROOT/public/router.php" "$BUNDLE/router.php"
    cp "$REPO_ROOT/public/index.php"  "$BUNDLE/index.php"
    # the app assumes public/ exists; on the flattened subdomain bootstrap lives beside index.php
    sed -i "s#require dirname(__DIR__) . '/app/bootstrap.php';#require __DIR__ . '/app/bootstrap.php';#" "$BUNDLE/index.php"
    grep -q "require __DIR__ . '/app/bootstrap.php'" "$BUNDLE/index.php" \
        || { echo "!! index.php bootstrap patch failed"; exit 1; }

    # data + config
    cp "$DB_FILE"    "$BUNDLE/database/patriot.db"
    cp "$HTACCESS"   "$BUNDLE/.htaccess"
    cp "$ENV_FILE"   "$BUNDLE/.env"     # gitignored; never commit real keys
    log "staged $(find "$BUNDLE" -type f | wc -l) files"
}

verify() {
    local base="https://$PREFIX.patriotpest.pro"
    log "verifying $base"
    curl -s -o /dev/null -w "  / -> %{http_code}\n" "$base/"
    curl -s "$base/health"; echo
    curl -s -o /dev/null -w "  /login -> %{http_code}\n" "$base/login"
    curl -sI "$base/" | grep -i "strict-transport-security" | sed 's/^/  /'
}

if [ -n "${STAGE_ONLY:-}" ]; then stage_bundle; exit 0; fi

stage_bundle
python "$REPO_ROOT/deploy/hostinger-tus-upload.py" --src "$BUNDLE" --prefix "$PREFIX"
verify
log "DEPLOYED: https://$PREFIX.patriotpest.pro"
