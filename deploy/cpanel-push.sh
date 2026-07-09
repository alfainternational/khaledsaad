#!/usr/bin/env bash
# ============================================================================
#  cpanel-push.sh — نشر ملفات محددة إلى استضافة cPanel (khaledsaad.net) عبر SSH
#  يعمل من جذر المشروع على جهاز التطوير (Windows/git-bash أو Linux).
#
#  الاستخدام:
#    ./deploy/cpanel-push.sh <ملف1> <ملف2> ...        # رفع ملفات نصّية/كود
#    ./deploy/cpanel-push.sh --build <ملفات...>        # يبني الأصول ويرفع public/build أيضاً
#
#  يقرأ الإعداد من deploy/cpanel.env (سري، خارج git) ويستخدم مفتاحاً بلا عبارة
#  مرور deploy/cpanel_deploy.key للنشر غير التفاعلي.
#
#  ما ينفّذه: نسخة احتياطية على الخادم → رفع → php artisan view:clear → إعادة
#  تشغيل opcache. لا يلمس .env ولا ينفّذ هجرات.
# ============================================================================
set -euo pipefail
cd "$(dirname "$0")/.."

ENV_FILE=deploy/cpanel.env
KEY=deploy/cpanel_deploy.key
[ -f "$ENV_FILE" ] || { echo "missing $ENV_FILE"; exit 1; }
[ -f "$KEY" ]      || { echo "missing $KEY (passphrase-less deploy key)"; exit 1; }

get(){ grep -E "^$1=" "$ENV_FILE" | head -1 | cut -d= -f2-; }
HOST_ADDR=$(get CPANEL_HOST); PORT=$(get CPANEL_PORT); USER_NAME=$(get CPANEL_USER)
RP=$(get CPANEL_REMOTE_PATH); PORT=${PORT:-22}
HOST="${USER_NAME}@${HOST_ADDR}"
SSHO="-i $KEY -p $PORT -o BatchMode=yes -o ConnectTimeout=20 -o StrictHostKeyChecking=accept-new"
SCPO="-i $KEY -P $PORT -o BatchMode=yes -o ConnectTimeout=20 -o StrictHostKeyChecking=accept-new"

BUILD=0; FILES=()
for a in "$@"; do [ "$a" = "--build" ] && BUILD=1 || FILES+=("$a"); done

# بوّابة ما قبل النشر: كشف تعارض حالة الأحرف (يعمل على ويندوز ويفشل على Linux).
# تخطٍّ اختياري: SKIP_CASE_CHECK=1
if [ "${SKIP_CASE_CHECK:-0}" != "1" ] && command -v php >/dev/null 2>&1; then
  echo "==> فحص حالة أحرف الكلاسات (case)"
  php deploy/check-class-case.php || { echo "أُلغي النشر: أصلح تعارضات الحالة أعلاه أولاً."; exit 1; }
fi

if [ "$BUILD" = "1" ]; then
  echo "==> بناء الأصول (vite)"; npm run build; FILES+=("public/build")
fi
[ ${#FILES[@]} -eq 0 ] && { echo "لا ملفات للرفع. مرّر مسارات أو استخدم --build"; exit 1; }

BK="_deploy_backups/push-$(date +%Y%m%d-%H%M%S)"
echo "==> نسخة احتياطية على الخادم: $BK"
ssh $SSHO "$HOST" "cd $RP && mkdir -p $BK && for f in ${FILES[*]}; do if [ -e \"\$f\" ]; then mkdir -p \"$BK/\$(dirname \$f)\"; cp -r \"\$f\" \"$BK/\$f\"; fi; done"

echo "==> رفع الملفات"
for f in "${FILES[@]}"; do
  # الرفع إلى المجلد الأب لتفادي تداخل scp عند وجود مجلد الوجهة (build/build)
  ssh $SSHO "$HOST" "mkdir -p $RP/$(dirname "$f")"
  scp $SCPO -r "$f" "$HOST:$RP/$(dirname "$f")/" >/dev/null && echo "   UP  $f"
done

echo "==> تنظيف كاش الـ views + إعادة تشغيل opcache"
ssh $SSHO "$HOST" "cd $RP && php artisan view:clear >/dev/null 2>&1 || true; touch .lsphp_restart.txt 2>/dev/null || true; echo cleared"
echo "نشر مكتمل إلى https://khaledsaad.net/"
