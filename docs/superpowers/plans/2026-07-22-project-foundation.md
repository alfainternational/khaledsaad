# KhaledSaad Project Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** تأسيس مشروع Laravel وFlutter جديد داخل مسار XAMPP الرئيسي، وتجميع المراجع، وإضافة بوابة DeepSeek آمنة قابلة للاختبار.

**Architecture:** يعمل Laravel كتطبيق الويب والـAPI والوسيط الوحيد للذكاء الاصطناعي، بينما يستهلك Flutter الـAPI الداخلي ولا يتصل بمزود الذكاء الاصطناعي مباشرة. تستخدم البيئة المحلية MySQL وdatabase queue وتبقى جميع الملفات داخل جذر المشروع.

**Tech Stack:** PHP 8.3, Laravel 13, Blade, Tailwind CSS, MySQL, Laravel Sanctum, Flutter 3.44, Dart 3.12, DeepSeek OpenAI-compatible API, PHPUnit.

---

### Task 1: Consolidate Local References

**Files:**
- Create: `docs/references/README.md`
- Move: `Marketing_Intelligence_Platform_PRD_AR.docx` into `docs/references/`
- Move: `Marketing_Platform_Execution_Backlog_AR.xlsx` into `docs/references/`
- Create: `docs/architecture/FOUNDATION.md`

- [x] **Step 1:** إنشاء مجلدات التوثيق داخل جذر المشروع.
- [x] **Step 2:** نقل ملفي PRD وBacklog إلى `docs/references`.
- [x] **Step 3:** توثيق المراجع والقرارات التقنية وحدود مساحة العمل.
- [x] **Step 4:** التحقق من وجود الملفين وحجميهما بعد النقل.

### Task 2: Bootstrap Laravel in the Project Root

**Files:**
- Create: Laravel 13 scaffold in the project root
- Modify: `.env`
- Modify: `.env.example`
- Modify: `.gitignore`

- [x] **Step 1:** إنشاء Laravel 13 في مجلد مؤقت داخل الجذر لأن الجذر غير فارغ.
- [x] **Step 2:** نقل ملفات Laravel المولدة إلى الجذر وحذف مجلد التوليد المؤقت فقط.
- [x] **Step 3:** إضافة `/oldone/` إلى `.gitignore` من دون قراءة المجلد أو الاعتماد عليه.
- [x] **Step 4:** ضبط MySQL المحلي على قاعدة `khaledsaad_platform` وضبط queue على `database`.
- [x] **Step 5:** تشغيل `php artisan --version` و`php artisan test` والتأكد من النجاح.

### Task 3: Add the DeepSeek Gateway Contract with TDD

**Files:**
- Create: `app/Contracts/AI/ArtificialIntelligenceGateway.php`
- Create: `app/Support/AI/DeepSeekGateway.php`
- Create: `app/Providers/ArtificialIntelligenceServiceProvider.php`
- Create: `config/ai.php`
- Create: `tests/Unit/Support/AI/DeepSeekGatewayTest.php`
- Modify: `bootstrap/providers.php`
- Modify: `.env.example`

- [x] **Step 1:** كتابة اختبار فاشل يثبت إرسال المصادقة والنموذج والرسائل إلى عنوان DeepSeek الصحيح.
- [x] **Step 2:** تشغيل الاختبار والتأكد أن الفشل سببه غياب البوابة.
- [x] **Step 3:** إضافة العقد والبوابة بأقل تنفيذ ينجح معه الاختبار، باستخدام Laravel HTTP client.
- [x] **Step 4:** كتابة اختبار فاشل يثبت أن أخطاء المزود تتحول إلى استثناء واضح من دون كشف المفتاح.
- [x] **Step 5:** تنفيذ معالجة الخطأ وتشغيل اختبارات البوابة ثم المجموعة الكاملة.

### Task 4: Bootstrap the Flutter Application

**Files:**
- Create: `mobile/` Flutter application
- Modify: `mobile/lib/main.dart`
- Create: `mobile/lib/core/config/app_environment.dart`
- Create: `mobile/test/core/config/app_environment_test.dart`

- [x] **Step 1:** توليد تطبيق Flutter باسم `khaledsaad_app` داخل `mobile`.
- [x] **Step 2:** كتابة اختبار فاشل يثبت أن عنوان الـAPI يُقرأ من `--dart-define` وله عنوان محلي افتراضي.
- [x] **Step 3:** تنفيذ `AppEnvironment` بأقل كود ينجح معه الاختبار.
- [x] **Step 4:** تشغيل `flutter analyze` و`flutter test` والتأكد من النجاح.

### Task 5: Foundation Verification

**Files:**
- Modify: this plan to mark completed steps

- [x] **Step 1:** تشغيل اختبارات Laravel كاملة.
- [x] **Step 2:** تشغيل تحليل واختبارات Flutter كاملة.
- [x] **Step 3:** التأكد أن ملفات المراجع موجودة في المسار المحلي الجديد.
- [x] **Step 4:** التأكد أن `DEEPSEEK_API_KEY` غير موجود في أي ملف متتبع أو كود تطبيق الهاتف.
- [x] **Step 5:** تسجيل نتيجة التحقق ومسارات المخرجات في تقرير التسليم.
