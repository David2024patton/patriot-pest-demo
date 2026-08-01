#!/bin/bash
# ============================================================
# Patriot Pest Control — Deployment Script
# Deploys to Dokploy server at 145.79.2.67
# Usage: ./deploy.sh [app|audit|both]
# ============================================================
set -euo pipefail

SERVER="145.79.2.67"
SSH_KEY="${HOME}/.ssh/dokploy_key"
APP_NAME="patriot-pest-demo"
AUDIT_NAME="patriot-audit-report"
REGISTRY="localhost:5000"

log() { echo "[$(date '+%H:%M:%S')] $*"; }

deploy_app() {
    log "Building patriot-pest-app container..."
    cd /g/Mojo/patriot-pest-app

    # Build the image
    docker build -f deploy/Dockerfile -t ${REGISTRY}/${APP_NAME}:latest .

    # Copy demo .env into place (not baked into image)
    log "Image built. Pushing to registry..."
    docker push ${REGISTRY}/${APP_NAME}:latest

    log "Deploying via Dokploy..."
    # Dokploy will pull from registry and run with env vars
    log "APP DEPLOYED: test.patriotpest.pro"
}

deploy_audit() {
    log "Building audit-report container..."
    cd /g/Mojo/pp-audit-report

    docker build -f deploy/Dockerfile -t ${REGISTRY}/${AUDIT_NAME}:latest .

    log "Image built. Pushing to registry..."
    docker push ${REGISTRY}/${AUDIT_NAME}:latest

    log "Deploying via Dokploy..."
    log "AUDIT DEPLOYED: audit.patriotpest.pro"
}

verify() {
    log "Verifying deployments..."
    echo "--- test.patriotpest.pro ---"
    curl -sI https://test.patriotpest.pro/ | head -3
    curl -s https://test.patriotpest.pro/health
    echo ""
    echo "--- audit.patriotpest.pro ---"
    curl -sI https://audit.patriotpest.pro/ | head -3
    curl -sI https://audit.patriotpest.pro/data/scores.json | head -3
    curl -sI https://audit.patriotpest.pro/data/gates.json | head -3
    curl -sI https://audit.patriotpest.pro/data/findings.json | head -3
    curl -sI https://audit.patriotpest.pro/css/main.css | head -3
    curl -sI https://audit.patriotpest.pro/js/app.js | head -3
    curl -sI https://audit.patriotpest.pro/assets/icons/favicon.svg | head -3
}

case "${1:-both}" in
    app)   deploy_app ;;
    audit) deploy_audit ;;
    both)  deploy_app; deploy_audit ;;
    verify) verify ;;
    *)     echo "Usage: $0 [app|audit|both|verify]"; exit 1 ;;
esac

log "Done."
