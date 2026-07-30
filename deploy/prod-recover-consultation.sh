#!/usr/bin/env bash
# ============================================================================
#  prod-recover-consultation.sh — إعادة تشغيل التشخيص الشامل لجلسة استشارة
#  عالقة على الإنتاج، وربطه بها كي تُحدَّث حالتها عند الانتهاء.
#
#  الاستخدام:
#    bash deploy/prod-recover-consultation.sh <session-uuid>
#
#  ينفّذ أمرًا واحدًا ثابتًا على الخادم: php artisan diagnosis:full --session=<uuid>.
#  الـuuid يُتحقَّق من صيغته محليًّا قبل الإرسال منعًا لأي حقن.
# ============================================================================
set -euo pipefail
cd "$(dirname "$0")/.."

UUID=${1:?"مرّر uuid الجلسة: bash deploy/prod-recover-consultation.sh <uuid>"}
if ! [[ "$UUID" =~ ^[0-9a-fA-F-]{36}$ ]]; then
  echo "uuid غير صالح: $UUID"; exit 1
fi

ENV_FILE=deploy/cpanel.env
KEY=deploy/cpanel_deploy_ed25519
[ -f "$KEY" ] || KEY=deploy/cpanel_deploy.key
[ -f "$ENV_FILE" ] || { echo "missing $ENV_FILE"; exit 1; }
[ -f "$KEY" ]      || { echo "missing deploy key"; exit 1; }

get(){ grep -E "^$1=" "$ENV_FILE" | head -1 | cut -d= -f2-; }
HOST_ADDR=$(get CPANEL_HOST); PORT=$(get CPANEL_PORT); USER_NAME=$(get CPANEL_USER)
RP=$(get CPANEL_REMOTE_PATH); PORT=${PORT:-22}
HOST="${USER_NAME}@${HOST_ADDR}"
SSHO="-i $KEY -p $PORT -o BatchMode=yes -o ConnectTimeout=25 -o StrictHostKeyChecking=accept-new"

# shellcheck disable=SC2029
ssh $SSHO "$HOST" "cd '$RP' && php artisan diagnosis:full --session='$UUID'"
