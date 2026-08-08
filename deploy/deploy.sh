#!/bin/bash
# ============================================================
# Patriot Pest Control — Docker/Dokploy deployment script
#
# STATUS (2026-08-08): THIS PATH IS CURRENTLY DEAD. Verified:
#   - Dokploy server.all returns [] (no build server registered)
#   - all 11 patriot-pest-demo deployments are fake ("done", 0 logs)
#   - SSH to 145.79.2.67:22 times out; stored API key is stale
#   - Docker Desktop daemon on the build box returns 500 (engine broken)
#
# The WORKING path is Hostinger shared hosting:
#   ./deploy/hostinger-deploy.sh   (see HOSTINGER_TUS_DEPLOY_RUNBOOK.md)
#
# This script is kept parameterized for the day Dokploy/Docker come back.
# No hardcoded registry, no hardcoded repo path.
# ============================================================
set -euo pipefail

# --- Configuration (override via env) ---
REGISTRY="${REGISTRY:-registry.dokploy.itak.live}"   # e.g. registry.dokploy.<domain> or Docker Hub
APP_NAME="${APP_NAME:-patriot-pest-demo}"
REPO_DIR="${REPO_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
IMAGE="${REGISTRY}/${APP_NAME}:${TAG:-latest}"

log() { echo "[$(date '+%H:%M:%S')] $*"; }

build_and_push() {
    log "building $IMAGE from $REPO_DIR"
    docker build -f "$REPO_DIR/deploy/Dockerfile" -t "$IMAGE" "$REPO_DIR"
    log "pushing $IMAGE"
    docker push "$IMAGE"
    log "push done — trigger the Dokploy deploy (dashboard.itak.live) for ${APP_NAME}"
}

verify() {
    log "verifying deployments..."
    echo "--- test.patriotpest.pro ---"
    curl -sI https://test.patriotpest.pro/ | head -3
    curl -s https://test.patriotpest.pro/health
    echo ""
}

case "${1:-all}" in
    app)     build_and_push ;;
    verify)  verify ;;
    all)     build_and_push; verify ;;
    *)       echo "usage: $0 [app|verify|all]"; exit 1 ;;
esac
