#!/bin/bash
# Deploy from the Surface. NEVER ships .env or the live DB (server keeps its own).
set -e
PHP="/c/Users/rated/php/php.exe"
EXT="-d extension_dir=/c/Users/rated/php/ext -d extension=openssl -d extension=curl -d extension=json"
BUNDLE="${BUNDLE:-C:/Users/rated/projects/ppc-bundle}"
PREFIX="${PREFIX:-test}"
STAGE_ONLY=1 ENV_FILE=C:/Users/rated/projects/patriot-pest-deploy.env BUNDLE="$BUNDLE" ./deploy/hostinger-deploy.sh
rm -f "$BUNDLE/.env" "$BUNDLE/database/patriot.db"   # never overwrite server env/db
$PHP $EXT deploy/hostinger-tus-upload.php --src "$BUNDLE" --prefix "$PREFIX"
echo "DEPLOYED https://$PREFIX.patriotpest.pro"
