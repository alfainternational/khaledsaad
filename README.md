# KhaledSaad Marketing Intelligence Platform

منصة Laravel عربية تساعد صاحب المشروع أو الوكالة على الانتقال من التخمين إلى قرار تسويقي واضح: تشخيص، أولويات، مخرجات قابلة للتنفيذ، ثم قياس.

## المنتج باختصار

المنصة ليست مولد محتوى عام. هي نظام تشخيص وتنفيذ تسويقي:

- يبدأ بتشخيص مجاني قبل التسجيل عبر `/diagnose`.
- يحول التشخيص بعد التسجيل إلى مشروع داخل Workspace.
- يبني ملف مشروع تسويقي حي يجمع الجمهور، العرض، الهدف، القنوات، المنافسين، والميزانية.
- يقدم 26 أداة استراتيجية على خمس مراحل.
- يولد تقريراً استراتيجياً شاملاً وخطة 7/30/90.
- يحول توصيات التدقيق إلى Execution Packages فيها مهام وأصل تنفيذ قابل للتعديل.
- يدعم AI Studio بقوالب تسليم عملية للإعلانات، صفحات الهبوط، واتساب، البريد، والبراند.
- يجهز مسار وكالة/White Label لإدارة عدة عملاء وإثبات القيمة.

## الرحلة الأساسية

1. الزائر يدخل رابط موقعه أو اسم النشاط وهدفه.
2. النظام يحلل الموقع/الحسابات/المنافس ويعرض نتيجة جزئية بعد البريد.
3. عند التسجيل، تتحول الحالة إلى مشروع محفوظ.
4. المستخدم يكمل ملف المشروع التسويقي.
5. الأدوات تبني وضوح الفكرة، العميل، السوق، العرض، الخطة، والقياس.
6. التقرير يجمع المخرجات في تشخيص وأولويات وخطة.
7. التوصيات تتحول إلى حزم تنفيذ قابلة للمراجعة والاعتماد.

## البنية التقنية

- Backend: Laravel 13 / PHP 8.3
- Auth: Laravel session + Sanctum API
- Frontend build: Vite + Tailwind CSS
- PDF: mPDF
- AI gateways: Gemini / NVIDIA / OpenAI-compatible fallback حسب الإعدادات
- Domain structure: `app/Domain`, `app/Application`, `app/Support`, `app/Http`

## أهم المسارات

- `/diagnose` التشخيص المجاني قبل التسجيل
- `/dashboard` لوحة المستخدم
- `/projects` إدارة المشاريع
- `/tools` الأدوات الاستراتيجية
- `/studio` AI Studio
- `/reports` التقارير
- `/agency` واجهة الوكالة
- `/admin` لوحة الإدارة

## أهم الملفات

- `routes/web.php` مسارات الويب والتدفقات الأساسية
- `app/Support/Intelligence/GuestDiagnosisService.php` تحليل التشخيص المجاني
- `app/Support/Tooling/ToolBlueprintCatalog.php` تعريف أسئلة ومخرجات الأدوات
- `app/Application/Tooling/RunToolAction.php` تشغيل الأداة وحفظ المخرجات
- `app/Application/Reports/BuildProjectReportAction.php` بناء التقرير الشامل
- `app/Application/Execution/BuildExecutionPackageAction.php` تحويل التوصية إلى حزمة تنفيذ
- `app/Support/AI/StudioTemplateCatalog.php` قوالب AI Studio وعقود الجودة
- `config/agent_registry.php` سجل قدرات الوكلاء

## التشغيل المحلي

```bash
composer install
npm install --ignore-scripts
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

للتطوير المستمر:

```bash
composer run dev
```

## الاختبارات

```bash
php artisan test
```

اختبارات مركزة مفيدة أثناء تطوير المنتج:

```bash
php artisan test tests/Unit/ToolBlueprintCatalogTest.php
php artisan test tests/Feature/App/GuestDiagnosisFunnelTest.php
php artisan test tests/Feature/Execution/ExecutionLayerTest.php
php artisan test tests/Unit/Reports
```

## النشر

راجع ملفات `deploy/` و`DEPLOYMENT.md` قبل أي رفع. مسار النشر الحالي يعتمد على:

- ضبط `.env.production` أو متغيرات الاستضافة.
- بناء أصول الواجهة عبر `npm run build`.
- رفع ملفات Laravel مع `vendor` أو تشغيل `composer install --no-dev` على الخادم.
- تشغيل `php artisan migrate --force`.
- تنظيف وإعادة بناء الكاش: `config:cache`, `route:cache`, `view:cache`.
- تشغيل queue/scheduler إن كانت الاستضافة تدعمهما.

## أولويات المنتج القادمة

- بناء Agency Audit لتقييم خطط وتقارير الوكالات وإعطاء صاحب المشروع أسئلة واضحة يطلبها من الوكالة.
- ربط Execution Packages بقوالب AI Studio حتى لا تبقى الحزمة مجرد توصية، بل تسليم جاهز.
- إضافة Performance Intake يدوي: الإنفاق، العملاء المحتملون، المبيعات، CAC، ROAS، CTR، CPL.
- تبسيط واجهة صاحب المشروع إلى Top 3 priorities قبل عرض التقارير التفصيلية.
- معالجة تحذيرات `npm audit` وتثبيت CI للـ build والاختبارات.

