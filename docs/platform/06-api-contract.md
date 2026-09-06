# 06 — عقد الـ API المشترك (`/api/v1`)

> الويب (PWA) وتطبيق Flutter يستهلكان **نفس العقد**. أي منطق موجود في الخادم
> ممنوع تكراره في Dart أو في JavaScript.

## 1. المبادئ

1. **الخادم يرسل ما يُعرض، لا ما يُحسب.** كل رقم يصل جاهزًا، ومعه نصّه المترجم.
2. **لا enum خام في JSON بلا مرافق.** كل قيمة نطاق تُرسل كزوج:
   ```json
   "ownership": { "value": "mine", "label": "أنا أملكها" }
   ```
   العميل يعرض `label` ويفرّع على `value`. هذا يجعل INV-3 مستحيل الخرق من العميل.
3. **الترجمة على الخادم.** رأس `Accept-Language` يحدّد لغة `label` وكل النصوص.
4. **الإصدار في المسار** (`/api/v1`). كسر العقد يعني `/api/v2`، لا تعديلًا صامتًا.
5. **`snake_case`** في JSON، ثابتًا.

## 2. المصادقة

- Laravel Sanctum. الويب بالكوكيز، Flutter برمز شخصي (token).
- كل رمز مرتبط بجهاز، قابل للإبطال من «أمان الحساب».
- تحديث الرمز صامت؛ عند `401` يعيد العميل المصادقة دون فقد مسودّة محلية.

## 3. شكل الاستجابة الموحّد

```json
{
  "data": { },
  "meta": { "locale": "ar", "server_time": "2026-09-06T12:00:00Z" }
}
```

### الأخطاء — تحمل نصًّا جاهزًا للعرض

```json
{
  "error": {
    "code": "provider_unavailable",
    "kind": "ours",
    "title": "تعذّر التشغيل لأسباب لدينا",
    "message": "إجاباتك محفوظة وسنشغّلها تلقائيًا فور الجاهزية.",
    "user_action": null,
    "retry_after": 900
  }
}
```

**`kind` حقل حاسم ويُفرض في الاختبارات:**

| `kind` | المعنى | `user_action` |
|---|---|---|
| `ours` | عطل لدينا (مزوّد الذكاء، انقطاع) | **يجب أن يكون `null`** — لا نحمّله ما ليس عليه |
| `theirs` | يحتاج فعلًا من المستخدم (رصيد، خطة) | إجراء واحد واضح |
| `input` | خطأ في المدخلات | تصحيح الحقل |

هذا هو INV-8 مُنفَّذًا على مستوى العقد، فيرثه الويب والتطبيق تلقائيًا.

## 4. نقاط النهاية الأساسية

```
GET    /api/v1/me                          الحساب، الخطة، الرصيد، الحصص
GET    /api/v1/projects
POST   /api/v1/projects
GET    /api/v1/projects/{id}               مع score{value,band,label,confidence}
GET    /api/v1/projects/{id}/facts          الحقائق + الثقة + المصدر
PATCH  /api/v1/projects/{id}/facts          تأكيد أو تصحيح حقيقة

GET    /api/v1/tools                        الأدوات + التكلفة + الحقائق المطلوبة
POST   /api/v1/tools/{key}/preflight        ← إلزامي قبل أي تدفق (INV-4)
POST   /api/v1/runs                         يبدأ run
GET    /api/v1/runs/{id}                    الحالة + التقدّم + الأسئلة المتبقية
PATCH  /api/v1/runs/{id}/answers            حفظ تدريجي (لا يفقد شيئًا — INV-5)
POST   /api/v1/runs/{id}/execute            بعد preflight ناجح

GET    /api/v1/reports                      قائمة حقيقية بفلاتر
GET    /api/v1/reports/{id}
GET    /api/v1/tasks?filter=this_week|suggested|overdue|done
PATCH  /api/v1/tasks/{id}                   تبنٍّ / تقدّم / إنجاز + قيمة المؤشر
GET    /api/v1/pulse/weekly

GET    /api/v1/navigation                   عناصر الملاحة وحالاتها (INV-6)
GET    /api/v1/design-tokens                إصدار التوكنز (لتحديثها دون إصدار متجر)
```

### `preflight` — الاستجابة

```json
{
  "data": {
    "outcome": "partial_budget",
    "cost": { "credits": 59, "tools": 11 },
    "affordable": { "credits": 40, "tools": 7 },
    "estimated_minutes": 14,
    "questions_estimated": 22,
    "headline": "رصيدك يكفي ٧ أدوات من ١١",
    "options": [
      { "key": "run_affordable", "label": "ابدأ بالأهم ضمن رصيدك" },
      { "key": "upgrade",        "label": "رقِّ الخطة" }
    ]
  }
}
```

`questions_estimated` **بعد** خصم الحقائق المعروفة — فيرى المستخدم أثر قاعدة الحقائق مباشرة.

## 5. التزامن والحالة

- كل مورد يحمل `updated_at` و`etag`؛ العميل يستخدم `If-None-Match`.
- `PATCH /runs/{id}/answers` **idempotent** بمفتاح من العميل — يحمي من ضعف الشبكة.
- عند تعارض (عدّل المستخدم من الويب والتطبيق): **آخر كتابة تفوز على مستوى الحقل**،
  مع `conflicts[]` في الاستجابة ليعرض العميل ما تغيّر. لا دمج صامت.

## 6. التوليد والاختبار

- مواصفة OpenAPI في `docs/api/openapi.yaml` **مولَّدة من الكود** (Scribe أو ما يماثله)،
  لا مكتوبة يدويًا — الوثيقة اليدوية تتعفّن.
- نماذج Dart تُولَّد من نفس المواصفة. **ممنوع كتابة موديل Dart يدويًا.**
- اختبار عقد في CI يفشل عند أي تغيير غير مُعلَن.

```php
it('كل قيمة enum في الاستجابة مرفقة بـ label');
it('كل خطأ من نوع ours له user_action = null');   // INV-8
it('preflight يسبق أي execute');
```
