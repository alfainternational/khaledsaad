#!/usr/bin/env bash
# ============================================================================
#  prod-log.sh — قراءة (grep) سطور من لوق الإنتاج على khaledsaad.net، للقراءة فقط.
#
#  الاستخدام:
#    bash deploy/prod-log.sh "<نمط البحث>" [عدد السطور]
#  مثال:
#    bash deploy/prod-log.sh "تعذر تشغيل أداة ضمن التشخيص الشامل" 8
#
#  لا يكتب شيئًا على الخادم ولا يلمس أي ملف — أمر grep على storage/logs/laravel.log
#  فقط. يقرأ إعداد الاتصال من deploy/cpanel.env ويستعمل مفتاح ed25519 بلا عبارة مرور.
# ============================================================================
set -euo pipefail
cd "$(dirname "$0")/.."

PATTERN=${1:?"مرّر نمط البحث: bash deploy/prod-log.sh \"<نمط>\" [عدد]"}
LINES=${2:-8}

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

# grep داخل علامتَي اقتباس مفردتين على الخادم؛ النمط يُمرَّر بأمان عبر متغيّر بيئة
# بعيدًا عن إعادة تأويل الصدفة. -a كي لا يظنّ grep الملف ثنائيًّا فيصمت.
# shellcheck disable=SC2029
ssh $SSHO "$HOST" \
  "cd '$RP' && P=\$(cat) && grep -a -F \"\$P\" storage/logs/laravel.log 2>/dev/null | tail -n $LINES || echo '(لا تطابق في اللوق)'" <<<"$PATTERN"
