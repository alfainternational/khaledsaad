#!/usr/bin/env bash
# يستورد حزمة دورة التسويق المنشورة بعد رفع الكود والترحيل.
set -euo pipefail
cd "$(dirname "$0")/.."

ENV_FILE=deploy/cpanel.env
KEY=deploy/cpanel_deploy_ed25519
[ -f "$KEY" ] || KEY=deploy/cpanel_deploy.key
[ -f "$ENV_FILE" ] || { echo "missing $ENV_FILE"; exit 1; }
[ -f "$KEY" ] || { echo "missing deploy key"; exit 1; }

get(){ grep -E "^$1=" "$ENV_FILE" | head -1 | cut -d= -f2-; }
HOST_ADDR=$(get CPANEL_HOST)
PORT=$(get CPANEL_PORT)
USER_NAME=$(get CPANEL_USER)
REMOTE_PATH=$(get CPANEL_REMOTE_PATH)
PORT=${PORT:-22}

ssh \
    -i "$KEY" \
    -p "$PORT" \
    -o BatchMode=yes \
    -o ConnectTimeout=25 \
    -o StrictHostKeyChecking=accept-new \
    "${USER_NAME}@${HOST_ADDR}" \
    "cd '$REMOTE_PATH' && php artisan content:import-marketing-course --publish --force"
