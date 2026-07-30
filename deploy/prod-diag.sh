#!/usr/bin/env bash
# ============================================================================
#  prod-diag.sh — تشخيص طابور الإنتاج (khaledsaad.net)، للقراءة فقط.
#
#  أوامر ثابتة لا تقبل أي مدخل متغيّر: تقرأ حالة الطابور والوظائف الفاشلة
#  والدفعات المعلّقة، وتفحص وجود cron يشغّل queue:work. لا تكتب شيئًا ولا
#  تعالج ولا تحذف — تشخيص محض.
#
#  الاستخدام: bash deploy/prod-diag.sh
# ============================================================================
set -euo pipefail
cd "$(dirname "$0")/.."

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

REMOTE='cd '"'$RP'"' || exit 1
echo "== cron يشغّل queue؟ =="
( crontab -l 2>/dev/null | grep -iE "queue|artisan|schedule" ) || echo "(لا cron يخص الطابور)"
echo
echo "== حالة الطابور والدفعات =="
php artisan tinker --execute="\$p=DB::table('"'"'jobs'"'"')->count(); \$f=DB::table('"'"'failed_jobs'"'"')->count(); \$b=DB::table('"'"'job_batches'"'"')->whereNull('"'"'finished_at'"'"')->count(); \$q=config('"'"'queue.default'"'"'); echo \"driver=\$q pending_jobs=\$p failed_jobs=\$f open_batches=\$b\".PHP_EOL;" 2>/dev/null
echo
echo "== آخر 3 وظائف فاشلة (الصنف والسبب المختصر) =="
php artisan queue:failed 2>/dev/null | tail -6 || echo "(تعذّر عرض الفاشلة)"'

# shellcheck disable=SC2029
ssh $SSHO "$HOST" "$REMOTE"
