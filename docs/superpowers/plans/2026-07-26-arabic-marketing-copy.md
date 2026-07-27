# Arabic Marketing Copy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** تحويل النصوص العربية الظاهرة في الويب والتطبيق إلى تجربة تسويقية واضحة ومتسقة تقود صاحب المشروع من فهم القيمة إلى بدء التشخيص ثم استخدام التقرير.

**Architecture:** تبقى بنية Laravel وFlutter ومنطق الأعمال كما هي. تُعدّل طبقة العرض والنصوص الثابتة والرسائل فقط، مع استخدام فاحص اللغة الحالي واختبارات الرحلات للحماية من التراجع.

**Tech Stack:** Laravel Blade، PHP 8.3، Flutter/Dart، PHPUnit، Vite.

---

### Task 1: حصر النصوص وبناء خط أساس

**Files:**
- Review: `resources/views/**/*.blade.php`
- Review: `app/Http/Controllers/**/*.php`
- Review: `app/Notifications/*.php`
- Review: `database/data/tools/*.php`
- Review: `mobile/lib/**/*.dart`

- [ ] استخراج قائمة الملفات التي تحتوي عربية وتصنيفها إلى عام، تسجيل، عميل، إدارة، تقارير، إشعارات، وجوال.
- [ ] تشغيل `php artisan product:audit` وتسجيل نتيجة ما قبل التعديل.
- [ ] رصد المصطلحات الداخلية والوعود والعبارات الغامضة والتكرار.

### Task 2: تحسين رحلة الزائر والتسجيل

**Files:**
- Modify: `resources/views/home.blade.php`
- Modify: `resources/views/partials/site-header.blade.php`
- Modify: `resources/views/partials/site-footer.blade.php`
- Modify: `resources/views/site/tools/*.blade.php`
- Modify: `resources/views/site/try/*.blade.php`
- Modify: `resources/views/auth/*.blade.php`
- Modify: `app/Http/Controllers/Api/V1/PublicContentController.php`
- Modify: `mobile/lib/features/public/*.dart`
- Modify: `mobile/lib/features/auth/*.dart`

- [ ] توضيح الوعد الرئيسي والنتيجة التي يحصل عليها صاحب المشروع.
- [ ] إزالة الكلام الداخلي عن الأدوات والعمليات حين لا يفيد الزائر.
- [ ] جعل الدعوات للإجراء تصف الخطوة التالية.
- [ ] توحيد رسائل الطمأنة حول الحساب والحفظ والدفع وفق السلوك المثبت.
- [ ] توحيد نسخة الويب والجوال مع مراعاة قصر نص الجوال.

### Task 3: تحسين تجربة العميل والإدارة

**Files:**
- Modify: `resources/views/app/**/*.blade.php`
- Modify: `resources/views/admin/**/*.blade.php`
- Modify: `resources/views/partials/panel-nav.blade.php`
- Modify: `app/Http/Controllers/App/**/*.php`
- Modify: `app/Http/Controllers/Admin/**/*.php`
- Modify: `mobile/lib/features/projects/**/*.dart`
- Modify: `mobile/lib/features/tools/**/*.dart`
- Modify: `mobile/lib/features/reports/**/*.dart`
- Modify: `mobile/lib/features/account/**/*.dart`
- Modify: `mobile/lib/features/admin/**/*.dart`

- [ ] تحويل العناوين إلى وصف مباشر لما يراه المستخدم.
- [ ] تحسين الحالات الفارغة والتأكيدات والأخطاء لتقترح إجراءً واضحًا.
- [ ] استبدال المصطلحات الداخلية في واجهة العميل مع إبقائها عند حاجة المدير المتخصص.
- [ ] توحيد أسماء التقرير والمشروع والخطة والرصيد والمراجعة اليدوية.

### Task 4: تحسين التقارير والأدوات والإشعارات

**Files:**
- Modify: `database/data/tools/*.php`
- Modify: `resources/views/reports/*.blade.php`
- Modify: `resources/views/agency-reports/**/*.blade.php`
- Modify: `app/Notifications/*.php`

- [ ] مراجعة أسماء الأدوات ووعودها وأسئلتها وأسباب طلب المعلومات.
- [ ] جعل التقرير يشرح المعنى والخطوة التالية قبل التفاصيل.
- [ ] توحيد عناوين الإشعارات ونصوصها والإجراء المرتبط بها.
- [ ] الحفاظ على جميع المتغيرات والعناصر النائبة كما هي.

### Task 5: التوثيق والتحقق

**Files:**
- Create: `docs/arabic-content-style-guide.md`
- Create: `docs/ux-writing-audit.md`
- Create: `docs/content-change-log.md`
- Create: `docs/unresolved-content-decisions.md`

- [ ] توثيق النبرة والقاموس والقواعد وأمثلة قبل وبعد.
- [ ] توثيق النطاق والمشكلات والقرارات غير المحسومة والتغييرات المنفذة.
- [ ] تشغيل `php artisan product:audit` واختبارات Laravel ذات الصلة.
- [ ] تشغيل `npm run build`، ثم `flutter analyze --no-pub` و`flutter test --no-pub` إن كانت البيئة تسمح.
- [ ] فحص بقايا اللهجة، والمصطلحات الداخلية، والنصوص الإنجليزية الظاهرة، والعناصر النائبة.
