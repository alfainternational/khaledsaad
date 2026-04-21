# جرد التطوير: المسارات والفجوات (مقابل CLAUDE.md)

آخر تحديث: مُنشأ تلقائياً ضمن تنفيذ خطة التطوير الشاملة.

## ما هو منفّذ في [routes/web.php](routes/web.php)

| المسار في CLAUDE.md | الحالة في الكود |
|---------------------|-----------------|
| `/` | `home` — منصّب |
| `/paths` | `paths.index` — منصّب |
| `/tools` | `tools.index` — منصّب |
| `/studio` | `studio.index` — منصّب |
| `/templates` | `templates.index` — منصّب |
| `/reports` | `reports.index` — منصّب |
| `/projects` | CRUD — منصّب |
| `/account` | `account.index` + تحديث — منصّب |
| `/admin` | مجموعة كاملة — منصّب |
| `/onboarding` | `onboarding.show` + `store`؛ مسارات `/onboarding/context` وغيرها تُعيد التوجيه إلى هنا (انظر القسم التالي) |

## مسارات Onboarding الإضافية (مقابل CLAUDE.md)

الوثيقة تذكر مسارات اختيارية؛ **في الكود** تُعاد التوجيه (302) إلى `/onboarding` لتوافق الروابط أو الإشارات:

- `onboarding.context` → `/onboarding/context`
- `onboarding.who-are-you` → `/onboarding/who-are-you`
- `onboarding.your-goal` → `/onboarding/your-goal`
- `onboarding.suggested-path` → `/onboarding/suggested-path`

**التجربة الفعلية:** تدفّق واحد في `onboarding.show` + `store`. تقسيم واجهات لاحقاً يبقى ممكناً دون كسر الروابط.

## API العامة النسخة 1

- **قبل التنفيذ:** واجهات JSON للأدوات/AI كانت تحت `web` + `auth` داخل [routes/web.php](routes/web.php) (`prefix('api')`) — **ليست** REST عامة موحّدة.
- **بعد التنفيذ:** مسارات **Sanctum** تحت `/api/v1/*` في [routes/api.php](routes/api.php):
  - `GET /api/v1/ping` — صحة الخدمة
  - `POST /api/v1/tokens` — إصدار توكن (حقول: `email`, `password`, `device_name`)؛ حد معدّل 5/دقيقة
  - `GET /api/v1/me` — المستخدم الحالي (Bearer)
  - `GET /api/v1/workspaces` — مساحات العمل النشطة للمستخدم (Bearer)

## ملاحظة

مجلد `D:\xampp\htdocs\cloud` (عميل Bun/TS) **ليس** جزءاً من مسارات Laravel ولا يُسجَّل هنا كمسار ويب.
