# Audience-Specific Reports Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** تقديم تقرير كامل ومبسّط لصاحب المشروع، وموجز تكليف مستقل وكامل للوكالة لا يُتاح إلا بعد حسم ستة قرارات تمنع التسعير بالتخمين.

**Architecture:** تبقى `AgencyReport` لقطة البيانات الثابتة الوحيدة، وتُبنى منها واجهتان مختلفتان حسب الجمهور. تقرير المالك يفسّر الحالة والمشكلات والخطوات بلغة إنسانية، بينما موجز الوكالة يحوّل القرارات المحسومة إلى حقائق وشروط ونطاق قابل للتسعير، وتمنع بوابة جاهزية مشتركة عرضه أو مشاركته قبل اكتماله.

**Tech Stack:** Laravel 13، Blade، PHPUnit، mPDF، Flutter/Dart، REST API.

---

### Task 1: بوابة جاهزية موجز الوكالة

**Files:**
- Modify: `app/Support/Marketing/BriefQuestions.php`
- Modify: `app/Services/Reports/AgencyReportService.php`
- Test: `tests/Feature/AudienceSpecificAgencyReportsTest.php`

- [ ] **Step 1: Write the failing test**

أضف اختبارًا ينشئ مشروعًا مكتمل التشخيص ثم يؤكد أن `briefCompleteness()` يعيد ستة بنود مستقلة: تعريف النجاح، الهدف الواحد، العملة وشمول الأتعاب، النطاق المطلوب، ملكية الحسابات، وموعد إغلاق استقبال العروض.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/AudienceSpecificAgencyReportsTest.php --filter=brief_gate`
Expected: FAIL لأن البوابة الحالية تعتمد خمسة حقول مختلفة ولا تحتوي موعد الإغلاق.

- [ ] **Step 3: Write minimal implementation**

أضف حقلي `proposal_deadline` و`proposal_submission` إلى نموذج الموجز، واجعل الخدمة تبني قائمة تحقق من ستة قرارات مفهومة، مع `is_ready` و`missing_count` ورسالة بشرية جاهزة للويب والتطبيق.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/AudienceSpecificAgencyReportsTest.php --filter=brief_gate`
Expected: PASS.

### Task 2: فصل التقريرين على الويب وPDF

**Files:**
- Create: `resources/views/agency-reports/partials/owner-document.blade.php`
- Create: `resources/views/app/agency-reports/brief.blade.php`
- Create: `resources/views/agency-reports/owner-pdf.blade.php`
- Modify: `resources/views/agency-reports/partials/document.blade.php`
- Modify: `resources/views/app/agency-reports/show.blade.php`
- Modify: `resources/views/agency-reports/pdf.blade.php`
- Create: `app/Services/Reports/OwnerReportPdfGenerator.php`
- Modify: `app/Http/Controllers/App/AgencyReportController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/AudienceSpecificAgencyReportsTest.php`

- [ ] **Step 1: Write the failing presentation tests**

اختبر أن صفحة المالك تعرض الأقسام التسعة بصوت مباشر، ولا تعرض جسم موجز الوكالة، وأن صفحة الموجز تعرض الأقسام التسعة الخاصة بالوكالة ولا تعرض سجل التنفيذ أو درجات الثقة أو تعارضات المالك أو أدوات التشخيص.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/AudienceSpecificAgencyReportsTest.php --filter=owner_report --filter=agency_brief`
Expected: FAIL لأن الصفحة الحالية تجمع الجمهورين.

- [ ] **Step 3: Implement the owner document**

اعرض: صورة المشروع، تفسير الأرقام، مسار التسرب، ثلاث مشكلات موحّدة، التعارضات، المجهولات المصنفة، خمس خطوات لهذا الأسبوع، دليل التفاوض، قائمة الجاهزية، ثم التفاصيل الخاصة المفيدة مثل سجل التنفيذ وخطة 30/60/90 ومصادر التشخيصات بصياغة مبسطة.

- [ ] **Step 4: Implement the agency brief**

اعرض: تعريف المشروع، خط الأساس، هدف واحد، النطاق، الأصول والوصول، آلية العمل، الملكية والشروط، متطلبات العرض وجدول تسعير من أربعة أسطر، وموعد وطريقة التسليم. احذف الأحكام على المالك والتعارضات والدرجات وسجل التنفيذ وأسماء الأدوات.

- [ ] **Step 5: Implement separate PDFs and routes**

اجعل تنزيل التقرير الأساسي ينتج تقرير المالك، وأضف معاينة وتنزيلًا مستقلين لموجز الوكالة. احتفظ بمولد PDF الحالي للموجز المشترك، وأضف مولدًا منفصلًا لتقرير المالك.

- [ ] **Step 6: Run presentation tests**

Run: `php artisan test tests/Feature/AudienceSpecificAgencyReportsTest.php`
Expected: PASS.

### Task 3: منع مشاركة موجز ناقص

**Files:**
- Modify: `app/Services/Reports/AgencyReportSharing.php`
- Modify: `app/Http/Controllers/App/AgencyReportController.php`
- Modify: `app/Http/Controllers/Api/V1/AgencyReportController.php`
- Modify: `app/Http/Controllers/Site/SharedAgencyReportController.php`
- Test: `tests/Feature/AudienceSpecificAgencyReportsTest.php`

- [ ] **Step 1: Write the failing gate tests**

اختبر أن المعاينة وPDF والمشاركة ترجع خطأ مفهومًا عندما تنقص قرارات البوابة، وأنها تعمل بعد اكتمال الستة، وأن بيانات المشاركة لا تحتوي `owner_guide` أو `behaviour` أو `cross_tool_synthesis` أو `methodology` أو درجات الثقة.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/AudienceSpecificAgencyReportsTest.php --filter=sharing`
Expected: FAIL لأن المشاركة الحالية متاحة بمجرد توليد اللقطة.

- [ ] **Step 3: Implement the sharing contract**

أضف تحققًا مركزيًا من جاهزية اللقطة، ونظّف بيانات الوكالة بقائمة سماح بدل حذف مفتاح واحد، وأعد رسالة: `موجزك ناقص N بنود — الوكالة لا تستطيع التسعير بدونها.`

- [ ] **Step 4: Run sharing tests**

Run: `php artisan test tests/Feature/AudienceSpecificAgencyReportsTest.php --filter=sharing`
Expected: PASS.

### Task 4: اللغة الإنسانية وبرومبتات التوليد

**Files:**
- Modify: `app/Services/Tools/PipelineSchemas.php`
- Modify: `database/data/tools/agency-brief.php`
- Test: `tests/Unit/Services/Tools/PipelineSchemasTest.php`
- Test: `tests/Feature/AudienceSpecificAgencyReportsTest.php`

- [ ] **Step 1: Write the failing language tests**

اختبر أن البرومبت يطلب الشمول لجمهوره، والجمل القصيرة، وشرح معنى كل نتيجة والخطوة التالية، ومنع أسماء الحقول والأكواد والمصطلحات غير المشروحة واللغة الآلية. اختبر كذلك أن عناوين التقريرين ونصوص البوابة عربية بشرية.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/Services/Tools/PipelineSchemasTest.php tests/Feature/AudienceSpecificAgencyReportsTest.php --filter=language`
Expected: FAIL لأن العقد الحالي لا ينص صراحة على شمول كل جمهور وفصل صوت المالك عن صوت الوكالة.

- [ ] **Step 3: Implement the language contract**

حدّث التمهيد العام وبرومبت جاهزية التعاقد ليفرض لغة إنسانية ومحايدة، ويمنع نسخ المتغيرات أو عرض الحيرة الداخلية للوكالة، مع إبقاء كل حقيقة يحتاجها القارئ مرة واحدة داخل مستنده.

- [ ] **Step 4: Run language tests**

Run: `php artisan test tests/Unit/Services/Tools/PipelineSchemasTest.php tests/Feature/AudienceSpecificAgencyReportsTest.php --filter=language`
Expected: PASS.

### Task 5: الويب API والتطبيق

**Files:**
- Modify: `routes/api.php`
- Modify: `app/Http/Controllers/Api/V1/AgencyReportController.php`
- Modify: `mobile/lib/core/api/platform_repository.dart`
- Modify: `mobile/lib/features/agency_reports/models.dart`
- Modify: `mobile/lib/features/agency_reports/agency_report_screen.dart`
- Test: `tests/Feature/AudienceSpecificAgencyReportsTest.php`
- Test: `mobile/test/agency_report_screen_test.dart`

- [ ] **Step 1: Write failing API and widget tests**

اختبر وجود روابط تقرير المالك وموجز الوكالة وحالة البوابة في API، وأن التطبيق يبدأ بتقرير المالك ويعرض زر موجز الوكالة مع عدد البنود الناقصة، ويتيح تنزيل ومشاركة الموجز فقط بعد الجاهزية.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/AudienceSpecificAgencyReportsTest.php --filter=api`
Run: `flutter test test/agency_report_screen_test.dart`
Expected: FAIL لأن API والتطبيق يعاملان اللقطة كمستند وكالة واحد.

- [ ] **Step 3: Implement API and Flutter UI**

أضف مسار PDF لموجز الوكالة وحالة البوابة إلى الحمولة، ثم حوّل شاشة التطبيق إلى تقرير المالك مع بطاقة مستقلة لموجز الوكالة، ورسائل عربية سهلة مطابقة للويب.

- [ ] **Step 4: Run API and Flutter tests**

Run: `php artisan test tests/Feature/AudienceSpecificAgencyReportsTest.php`
Run: `flutter test test/agency_report_screen_test.dart`
Expected: PASS.

### Task 6: التحقق الشامل

**Files:**
- Modify: أي ملفات اختبار قديمة تتوقع المستند المدمج، مع الحفاظ على معنى الحماية والنسخ الثابتة.

- [ ] **Step 1: Run report regression tests**

Run: `php artisan test tests/Feature/AgencyReportTest.php tests/Feature/AgencyBriefBudgetTest.php tests/Feature/AgencyOperationalFileTest.php tests/Feature/AgencyReportSharingTest.php tests/Feature/AgencyStateDocumentTest.php tests/Feature/AgencyReportDeliveryTest.php tests/Feature/AudienceSpecificAgencyReportsTest.php`
Expected: PASS with 0 failures.

- [ ] **Step 2: Run formatting and mobile checks**

Run: `vendor/bin/pint --test`
Run: `flutter analyze`
Run: `flutter test`
Expected: all commands exit 0.

- [ ] **Step 3: Review the audience boundary**

ابحث في مخرجات الوكالة عن العبارات المحظورة (`سجل التنفيذ`, `درجة الثقة`, `افتراض`, `معرّف`, `اسم الأداة`) وتأكد من عدم وجودها، ثم تأكد أن تقرير المالك يحتوي الأقسام التسعة وأن كل معلومة مكررة داخل التقرير أزيلت أو دُمجت.

