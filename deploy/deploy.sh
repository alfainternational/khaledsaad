#!/usr/bin/env bash
# ============================================================================
#  deploy.sh — سكربت نشر آمن لمنصة khaledsaad على خادم Linux
#  يُنفَّذ من جذر المشروع على الخادم بعد git pull.
#
#  الاستخدام:
#    cd /var/www/khaledsaad
#    git pull origin main
#    ./deploy/deploy.sh
# ============================================================================

set -euo pipefail

echo "==> 1/9  تفعيل وضع الصيانة (down)"
php artisan down --render="errors::503" --retry=30 || true

echo "==> 2/9  تحديث تبعيات Composer (بدون dev)"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "==> 3/9  تحديث أصول الواجهة (Vite)"
npm ci --omit=dev
npm run build

echo "==> 4/9  تنفيذ الهجرات --force"
php artisan migrate --force

echo "==> 5/9  تنظيف الكاشات القديمة"
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear

echo "==> 6/9  توليد كاشات الإنتاج"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "==> 7/9  إعادة تشغيل الـ Queue Workers"
php artisan queue:restart

echo "==> 8/9  storage:link (مرّة واحدة فقط، آمن للتكرار)"
php artisan storage:link || true

echo "==> 9/9  رفع الصيانة (up)"
php artisan up

echo ""
echo "النشر اكتمل بنجاح."
echo "تذكير: راجع /var/log/khaledsaad/queue.log وسجلات التطبيق."
