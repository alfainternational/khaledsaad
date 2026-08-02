#!/usr/bin/env bash
# ============================================================================
#  prod-finish-score-explanation.sh — خطوتا ما بعد نشر شرح الدرجة.
#
#  تُشغَّل مرة واحدة بعد رفع ملفات شرح الدرجة:
#
#   1) config:clear — `config/mobile.php` تغيّر (1.0.6/12 ← 1.0.7/13). إن كان
#      الإعداد مخزَّنًا على الخادم فالملف المرفوع لا يُقرأ أصلًا، وتبقى بوابة
#      التحديث تعلن نسخة أقدم من الـAPK المنشور فعلًا.
#
#   2) reports:backfill-score-explanation — التقارير الصادرة قبل هذا النشر
#      تحمل «10 / 10» بلا سؤال ولا إجابة ولا حصة، لأن التفصيل يُخزَّن لحظة
#      الإصدار. الأمر يشرحها من إجابات تشغيلها نفسها.
#
#  يعمل بالتجربة أولًا (--dry-run) ثم يسأل قبل الكتابة. وأمر التعبئة نفسه
#  يرفض لمس أي تقرير تتغيّر درجته عند إعادة الحساب.
#
#  الاستخدام: bash deploy/prod-finish-score-explanation.sh
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

echo "==> تفريغ كاش الإعداد وقراءة إصدار التطبيق المعلن"
# shellcheck disable=SC2029
ssh $SSHO "$HOST" "cd '$RP' && php artisan config:clear && php artisan tinker --execute=\"echo 'mobile='.config('mobile.version').'+'.config('mobile.build').PHP_EOL;\""

echo
echo "==> تجربة تعبئة شرح الدرجة (بلا كتابة)"
# shellcheck disable=SC2029
ssh $SSHO "$HOST" "cd '$RP' && php artisan reports:backfill-score-explanation --dry-run"

echo
read -r -p "أنفّذ التعبئة فعليًّا؟ [y/N] " answer
case "$answer" in
    [yY]*) ;;
    *) echo "أُلغيت التعبئة. لا شيء كُتب."; exit 0 ;;
esac

echo "==> تنفيذ التعبئة"
# shellcheck disable=SC2029
ssh $SSHO "$HOST" "cd '$RP' && php artisan reports:backfill-score-explanation"

echo "تم. افتح https://khaledsaad.net/app/reports/40 وافتح «درجتك وسبب كل نقطة فيها»."
