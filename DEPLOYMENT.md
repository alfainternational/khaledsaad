# DEPLOYMENT.md — دليل نشر منصة khaledsaad للإنتاج

> هذا الدليل يغطي نشر المنصة على خادم إنتاج حقيقي. كل خطوة هنا **إلزامية** قبل فتح الموقع للمستخدمين.
>
> المرجع المعماري: [CLAUDE.md](CLAUDE.md). لا ينفذ نشر يخالف ما فيه.

---

## 0. متطلبات الخادم (Server Requirements)

| العنصر | الحد الأدنى |
|---|---|
| OS | Ubuntu 22.04 LTS أو Windows Server + IIS/Apache |
| PHP | 8.3+ مع الامتدادات: `mbstring, intl, pdo_mysql, redis, bcmath, gd, zip, fileinfo, openssl` |
| MySQL | 8.0+ |
| Node.js | 20+ (للبناء فقط — لا يعمل وقت التشغيل) |
| Composer | 2.8+ |
| Redis | 7+ (مُوصى به للـ cache/queue/session) |
| Web Server | Nginx + PHP-FPM (الأفضل) أو Apache mod_php |
| SSL | شهادة صالحة (Let's Encrypt مجّاني) |

---

## 1. قبل النشر — Pre-Deploy Checklist

**لا تُنفَّذ خطوة بدون إتمام كل البنود قبلها.**

### 1.1 الأمان والمفاتيح
- [ ] **تدوير** كل مفاتيح API التي ظهرت في `.env` المحلي (Google Gemini, NVIDIA, PayPal) وإصدار مفاتيح إنتاج جديدة.
- [ ] تأكيد أن `.env` **ليس** ضمن Git (`git ls-files | grep -E "^\.env$"` يجب أن لا يُرجع شيئاً).
- [ ] إنشاء `APP_KEY` جديد خاص بالإنتاج: `php artisan key:generate --show`.
- [ ] تفعيل ميزة **2FA** على حساب PayPal / Google Cloud / مزوّد الخادم.

### 1.2 البيانات البيئية
- [ ] نسخ `.env.production.example` إلى `.env` على الخادم وتعبئة كل القيم.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`.
- [ ] `LOG_LEVEL=warning` (لا `debug` في الإنتاج).
- [ ] `SESSION_SECURE_COOKIE=true` (مطلوب لـ HTTPS).
- [ ] ضبط SMTP حقيقي (Postmark / SES / Resend / Mailgun).
- [ ] ضبط `AI_PROVIDER` على قيمة فعلية (`gemini` أو `nvidia` أو `fallback`) مع مفاتيحها.
- [ ] ضبط PayPal إمّا **sandbox** للتجربة أو **live** مع ملء كل معرّفات `PAYPAL_PLAN_*`.

### 1.3 قاعدة البيانات
- [ ] إنشاء قاعدة بيانات إنتاج منفصلة (ليست `khaledsaad_marketing` المحلية).
- [ ] إنشاء مستخدم MySQL محدود الصلاحيات (لا root).
- [ ] تفعيل نسخ احتياطية يومية تلقائية.
- [ ] التأكد من `utf8mb4_unicode_ci` كـ character set.

### 1.4 البنية التحتية
- [ ] شهادة SSL مفعّلة وصالحة (اختبار: [ssllabs.com](https://www.ssllabs.com/ssltest/)).
- [ ] DNS يشير للخادم الصحيح (A record + AAAA إن وجد IPv6).
- [ ] Redis مثبّت ويعمل (`redis-cli ping` يُرجع `PONG`).
- [ ] Cron task مفعّل: `* * * * * cd /var/www/khaledsaad && php artisan schedule:run >> /dev/null 2>&1`.
- [ ] Firewall يسمح فقط بـ 80, 443, 22 (SSH مقيّد بـ IP إن أمكن).

### 1.5 الكود
- [ ] كل الاختبارات تنجح محلياً (`php artisan test` — حالياً 70/70).
- [ ] `npm run build` ينجح بدون أخطاء.
- [ ] لا ملفات تطويرية في الجذر (`auth_probe.php`, `setup-run.php`, إلخ — **محذوفة**).
- [ ] `.gitignore` يستثني `scratch/`, `.env*` (عدا `.env.example` و `.env.production.example`).

---

## 2. النشر الأول (First Deploy)

### 2.1 تجهيز الخادم (Linux/Ubuntu)
```bash
# PHP 8.3 + Extensions
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3-fpm php8.3-mysql php8.3-redis php8.3-mbstring \
    php8.3-intl php8.3-gd php8.3-zip php8.3-bcmath php8.3-xml php8.3-curl

# Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo bash -
sudo apt install -y nodejs

# MySQL + Redis + Nginx + Supervisor
sudo apt install -y mysql-server redis-server nginx supervisor certbot python3-certbot-nginx

# إنشاء مجلد اللوقات
sudo mkdir -p /var/log/khaledsaad
sudo chown www-data:www-data /var/log/khaledsaad
```

### 2.2 سحب الكود
```bash
sudo mkdir -p /var/www/khaledsaad
sudo chown $USER:www-data /var/www/khaledsaad
cd /var/www/khaledsaad
git clone <your-private-repo-url> .
```

### 2.3 تنصيب التبعيات والأصول
```bash
composer install --no-dev --optimize-autoloader --prefer-dist
npm ci --omit=dev
npm run build
```

### 2.4 ضبط `.env` والصلاحيات
```bash
cp .env.production.example .env
# عبّئ القيم الفعلية في .env (انظر قسم 1.2)
php artisan key:generate

# صلاحيات
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
sudo chmod 600 .env
```

### 2.5 قاعدة البيانات والـ Seeding
```bash
php artisan migrate --force
php artisan db:seed --class=PlatformBootstrapSeeder --force
```

### 2.6 كاشات الإنتاج
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan storage:link
```

### 2.7 SSL عبر Let's Encrypt
```bash
sudo certbot --nginx -d your-domain.com -d www.your-domain.com
```

### 2.8 ضبط Nginx
```bash
sudo cp deploy/nginx/khaledsaad.conf /etc/nginx/sites-available/
# عدّل your-domain.com في الملف
sudo ln -s /etc/nginx/sites-available/khaledsaad.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 2.9 ضبط Queue Worker
```bash
sudo cp deploy/supervisor/khaledsaad-queue.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start khaledsaad-queue-default:*
```

### 2.10 ضبط Cron
```bash
crontab -e -u www-data
# أضف:
* * * * * cd /var/www/khaledsaad && php artisan schedule:run >> /dev/null 2>&1
```

---

## 3. النشر التحديثي (Subsequent Deploys)

استخدم السكربت الجاهز:

```bash
cd /var/www/khaledsaad
git pull origin main
./deploy/deploy.sh
```

السكربت ينفذ: maintenance → composer → npm build → migrate → caches → queue:restart → up.

---

## 4. النشر على Windows (XAMPP) — محلي فقط

> للتطوير المحلي. **لا يُستخدم كخادم إنتاج حقيقي.**

```powershell
composer install
npm install
npm run build
php artisan migrate --seed
php artisan storage:link
```

لتشغيل Queue Worker كخدمة Windows، انظر:
`deploy/windows/install-queue-service.ps1` (يتطلب NSSM + صلاحيات Admin).

---

## 5. ما بعد النشر — Post-Deploy Verification

### 5.1 فحوصات سريعة
- [ ] `https://your-domain.com/up` يعيد 200 (Laravel health check).
- [ ] تسجيل دخول بمستخدم تجريبي يعمل.
- [ ] لوحة الآدمن `/admin/login` تعمل.
- [ ] الواجهة تُحمِّل CSS/JS المبنية (افتح DevTools → Network → تأكد من أصول `/build/assets/*`).
- [ ] Queue Worker يعمل: `sudo supervisorctl status khaledsaad-queue-default:*`.
- [ ] إرسال إيميل تسجيل يصل فعلياً (سجّل مستخدماً جديداً).

### 5.2 اختبارات الأمان
- [ ] `https://your-domain.com/.env` يعيد **403/404** (ليس محتوى الملف).
- [ ] `https://your-domain.com/storage/logs/laravel.log` يعيد **403**.
- [ ] فحص SSL على [ssllabs.com](https://www.ssllabs.com/ssltest/) — يجب A أو A+.
- [ ] فحص Headers على [securityheaders.com](https://securityheaders.com) — يجب A أو A+.
- [ ] `curl -I https://your-domain.com` يُظهر `Strict-Transport-Security`.

### 5.3 المراقبة
- [ ] مراقبة `storage/logs/laravel.log` لمدة 24 ساعة بعد الإطلاق.
- [ ] مراقبة `/var/log/nginx/khaledsaad.error.log`.
- [ ] مراقبة `/var/log/khaledsaad/queue.log` للتأكد من عدم وجود Failed Jobs متراكمة.
- [ ] إعداد أداة مراقبة خارجية (Sentry / Bugsnag / Laravel Telescope في Staging فقط).
- [ ] إعداد Uptime monitor (UptimeRobot / BetterUptime) على `/up`.

---

## 6. Rollback — التراجع عند الكارثة

```bash
cd /var/www/khaledsaad

# تراجع الكود
git log --oneline -5                    # اختر commit سليم
git reset --hard <commit-hash>

# تراجع الهجرات (إن كانت الهجرة الأخيرة ضارة)
php artisan migrate:rollback --step=1 --force

# تجديد الكاشات
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart

# استعادة نسخة قاعدة بيانات سليمة إن احتاج
mysql -u user -p khaledsaad_prod < backup.sql
```

---

## 7. الصيانة الدورية

### يومياً
- مراقبة السجلات، Failed Jobs، Disk space، CPU/RAM.

### أسبوعياً
- مراجعة `audit_logs` للأنشطة المشبوهة.
- مراجعة نسخ النسخ الاحتياطية واستعادة اختبارية.
- تحديث Composer/npm patches (`composer update --lock`).

### شهرياً
- مراجعة صلاحيات المستخدمين والمشرفين.
- تدوير مفاتيح API.
- فحص أمني شامل.
- مراجعة Feature Flags ومسح المنتهية.

---

## 8. مراجع
- [CLAUDE.md](CLAUDE.md) — الدستور المعماري (الأقسام 18, 29, 35, 37).
- [docs/platform/NFR_CHECKLIST.md](docs/platform/NFR_CHECKLIST.md) — متطلبات الجودة.
- [docs/platform/INTEGRATIONS_RUNBOOK.md](docs/platform/INTEGRATIONS_RUNBOOK.md) — تشغيل التكاملات.
- [deploy/](deploy/) — كل ملفات البنية التحتية الجاهزة.
