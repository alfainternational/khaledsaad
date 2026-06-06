# كيفية النشر للإنتاج — khaledsaad.net

> **ملف تعليمات للوكيل (Claude):** ضعه أو أشِر إليه في أي محادثة على مشروع `D:\xampp\htdocs\khaledsaad`.
> الهدف: نشر التعديلات للاستضافة الفعلية مباشرة بأمان عبر سكربت جاهز.

---

## TL;DR — كيف تنشر (انسخ ونفّذ)

```bash
# 1) ملفات نصّية/كود (blade، PHP، إلخ) — لا تحتاج بناء أصول:
bash deploy/cpanel-push.sh المسار/للملف1 المسار/للملف2 ...

# 2) إذا عدّلت CSS أو JS (resources/css أو resources/js): ابنِ الأصول وارفعها:
npm run build
bash deploy/cpanel-push.sh public/build resources/css/app.css <بقية الملفات المعدّلة>

# 3) تحقّق أن الموقع سليم:
curl -s -o /dev/null -w '%{http_code}' --max-time 25 https://khaledsaad.net/        # متوقّع 200
curl -s -o /dev/null -w '%{http_code}' --max-time 25 https://khaledsaad.net/dashboard # متوقّع 302 (تحويل لتسجيل الدخول)
```

السكربت يفعل تلقائياً: **نسخة احتياطية على الخادم** (`_deploy_backups/`) ← رفع الملفات (SFTP) ← `php artisan view:clear` ← إعادة تشغيل opcache. **لا يلمس `.env` ولا ينفّذ هجرات.**

---

## بيانات الاتصال (محفوظة محلياً، خارج git)

- **الخادم:** `91.204.209.18` · **المنفذ:** `22` · **المستخدم:** `kcepljda`
- **جذر الموقع على الاستضافة:** `/home/kcepljda/public_html`
- **الموقع الحيّ:** `https://khaledsaad.net/`
- **الإعداد السري:** `deploy/cpanel.env` (مستثنى من git).
- **مفتاح النشر الآلي:** `deploy/cpanel_deploy.key` (نسخة بلا عبارة مرور، مستثناة من git) — السكربت يستعمله، فلا حاجة لإدخال كلمة سر.
- المفتاح الأصلي (بعبارة مرور): `C:/Users/lenovo/.ssh/khaledsaad_key`.

> إن فُقد `deploy/cpanel_deploy.key`، أعد إنشاءه:
> ```bash
> cp "/c/Users/lenovo/.ssh/khaledsaad_key" deploy/cpanel_deploy.key
> ssh-keygen -p -f deploy/cpanel_deploy.key -P "<عبارة المرور>" -N ""
> ```

---

## حقائق بنية الإنتاج (مهمة قبل أي نشر)

1. **تطبيق Laravel كامل داخل `public_html`** مباشرة (artisan، app، resources…)؛ `index.php` يستدعي `public/index.php`.
2. **لا يوجد node/npm على الخادم** → **ابنِ الأصول محلياً** (`npm run build`) وارفع مجلد `public/build` كاملاً.
3. الأصول تُخدَم من `public_html/public/build`، والـ `manifest.json` يحدّد ملف الـ CSS ذا البصمة (hash) — لذلك بعد أي تعديل CSS **لازم** ترفع `public/build` كاملاً ليتحدّث الـ manifest.
4. **الـ Blade views مكاشة** على الخادم → بعد رفع أي `.blade.php` لازم `view:clear` (السكربت يفعلها تلقائياً).
5. PHP على الخادم 8.5 (محلياً 8.3). تغييرات كلاسات PHP تحتاج تحديث opcache → السكربت يلمس `.lsphp_restart.txt` تلقائياً.
6. **الإنتاج ليس مستودع git** ولا يوجد remote — النشر = رفع ملفات فقط.

---

## قواعد السلامة

- **تحقّق محلياً قبل النشر:** `php -l <file>` لملفات PHP، و`npm run build` ينجح لملفات الأصول.
- **لا ترفع عملاً نصف مكتمل** لموقع حيّ — ابنِ وتحقّق أولاً.
- **لا ترفع** ملفات الاختبار (`tests/`) ولا الأسرار (`.env`, `deploy/cpanel.env`, `*.key`).
- **بعد النشر** تحقّق دائماً بـ curl أن الصفحات تُرجع 200 (والصفحات المحمية 302 لا 500).
- النسخ الاحتياطية للملفات المرفوعة موجودة على الخادم في `~/public_html/_deploy_backups/`.

---

## ملاحظة أمنية (الإنتاج مضبوط)

`.env` على الإنتاج: `APP_ENV=production` و`APP_DEBUG=false` (مضبوط — لا تُعِده إلى local/true).

---

## استكشاف الأخطاء

- **«Connection refused / host فارغ»:** تأكّد أن `deploy/cpanel.env` فيه `CPANEL_HOST=91.204.209.18`.
- **CSS لم يتغيّر على الموقع رغم الرفع:** تأكّد أنك رفعت `public/build` كاملاً (لا ملفاً مفرداً)، وقارن اسم ملف الـ CSS الحيّ بالمحلي:
  ```bash
  ls -t public/build/assets/app-*.css | head -1
  curl -s "https://khaledsaad.net/?v=$(date +%s)" | grep -oE 'app-[A-Za-z0-9_-]*\.css' | head -1
  ```
- **تعديل blade لم يظهر:** السكربت ينفّذ `view:clear`؛ إن بقي، تأكّد أن الملف رُفع للمسار الصحيح.
```
