# Admin Content Layout Consistency Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** إصلاح انهيار تنسيق مرشحات إدارة المحتوى وتعميم نفس العقد على الشاشات المشابهة والجداول والتواريخ النسبية.

**Architecture:** يحتفظ النظام بالقوالب الحالية ويضيف عقد عرض مشتركًا في `workspace.css`. تُوسم القوالب بعناصر دلالية قابلة للاختبار، ويُضبط تعريب Carbon مرة واحدة في مزود التطبيق.

**Tech Stack:** Laravel 13، Blade، CSS Grid، Carbon، PHPUnit، Vite.

---

### Task 1: تثبيت عقد التنسيق باختبار فاشل

**Files:**
- Modify: `tests/Feature/AdaptiveInterfaceLayoutTest.php`

- [ ] **Step 1: Write the failing test**

أضف اختبارًا يقرأ `workspace.css` وقوالب وحدة المحتوى ويتحقق من وجود `data-filter-bar` و`filter-bar__field` و`data-table="entity"` و`data-label`، ثم تحقق أن `diffForHumans()` يعيد نصًا عربيًا بعد إقلاع التطبيق.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/AdaptiveInterfaceLayoutTest.php --filter=content_admin`

Expected: FAIL لأن عقد المرشحات والجداول وتعريب Carbon غير موجود بعد.

### Task 2: تنفيذ شبكة المرشحات والحقول الموحدة

**Files:**
- Modify: `resources/css/workspace.css`
- Modify: `resources/views/admin/content/index.blade.php`
- Modify: `resources/views/admin/content/subscribers.blade.php`
- Modify: `resources/views/admin/content/media.blade.php`

- [ ] **Step 1: Add the shared CSS contract**

أضف قواعد `.filter-bar` و`.filter-bar__field` و`.filter-bar__label`، مع قواعد الاستجابة عند `85rem` و`48rem`. وسّع عقد `.form` ليغطي الحقول النصية والقوائم الخام مع استثناء الحقول المخفية والاختيارية والملفات والأزرار.

- [ ] **Step 2: Add semantic filter markup**

لف كل حقل داخل `label.filter-bar__field` وأضف تسمية ظاهرة. أضف `data-filter-bar` للنموذج، واستخدم `page-head__actions` لإجراءات رأس الصفحة.

- [ ] **Step 3: Run the focused test**

Run: `php artisan test --compact tests/Feature/AdaptiveInterfaceLayoutTest.php --filter=content_admin`

Expected: يبقى فشل الجداول أو التعريب فقط إلى أن تكتمل المهمة التالية.

### Task 3: الجداول المتجاوبة وتعريب الوقت

**Files:**
- Modify: `resources/views/admin/content/index.blade.php`
- Modify: `resources/views/admin/content/subscribers.blade.php`
- Modify: `resources/views/admin/content/categories/index.blade.php`
- Modify: `resources/views/admin/content/curriculum.blade.php`
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Apply entity-table metadata**

أضف `data-table="entity"` للجداول وتسميات `data-label` لكل خلية. اجعل عناوين المحتوى قابلة للالتفاف بدل فرض سطر واحد.

- [ ] **Step 2: Normalize raw curriculum controls**

تستفيد حقول منهج الدورة الخام من عقد `.form` العام، وتُعطى التسميات الناقصة حتى لا يعتمد المستخدم على ترتيب الحقول.

- [ ] **Step 3: Configure Arabic relative dates**

استدعِ `Carbon::setLocale('ar')` داخل `AppServiceProvider::boot()`.

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact tests/Feature/AdaptiveInterfaceLayoutTest.php tests/Feature/AdminContentManagementTest.php tests/Feature/AdminContentSubscribersTest.php tests/Feature/AdminContentCategoryTest.php`

Expected: PASS.

### Task 4: التحقق البصري والإصدار

**Files:**
- Verify: `resources/css/workspace.css`
- Verify: built `public/build`

- [ ] **Step 1: Format and build**

Run: `vendor/bin/pint --dirty && npm run build && git diff --check`

Expected: لا أخطاء؛ يسمح بتحذير حجم الحزمة الحالي فقط.

- [ ] **Step 2: Inspect desktop and mobile**

افتح صفحات المحتوى والمشتركين والوسائط والأقسام ومنهج الدورة عند عرض سطح المكتب ثم `390x844`. تحقق من حدود الحقول، قرب الأسهم من النص، عدم وجود فراغ أفقي غير مبرر، وتحول الجدول إلى بطاقات معنونة.

- [ ] **Step 3: Commit, push, deploy, and smoke-test**

ادفع فرع `codex/internal-content-hub`، وانشر الملفات مع `--build`، ثم تحقق أن مسارات الإدارة تعيد 200 للمسؤول وأن الأصول الجديدة محملة بلا أخطاء.
