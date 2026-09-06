# 12 — الاختبارات وبوابات الجودة

> **الفكرة الحاكمة:** كل عطل في التدقيق ينتمي إلى **فئة**، لا إلى حالة فردية.
> إصلاح الحالة يعيدها بعد شهرين بشكل جديد. البوابة تمنع الفئة كلها.
> وجود `ui-repairs.css` هو الدليل على أن هذا يحدث فعلًا.

## 1. الهرم

| الطبقة | الأداة | النطاق |
|---|---|---|
| معماري | Pest `arch()` | الثوابت البنيوية — الأرخص والأسرع |
| وحدة | Pest | `Domain/` بالكامل |
| تكامل | Pest + قاعدة اختبار | حالات الاستخدام والـ API |
| عقد | OpenAPI diff | كسر عقد `/api/v1` |
| متصفح | Playwright | المسارات الحرجة، RTL، الوضع الداكن |
| Flutter | widget + integration | الشاشات الحرجة |
| بصري | لقطات مرجعية | ar/en × فاتح/داكن |

## 2. الاختبارات المعمارية — أهم بوابة

```php
// tests/Arch/InvariantsTest.php

arch('INV-1: الرصيد يُقرأ من CreditWallet فقط')
    ->expect('App')->not->toUse(['App\Models\Account::credits'])
    ->ignoring('App\Domain\Credits');

arch('INV-2: لا حساب درجات خارج Domain\Scoring')
    ->expect('App\Http')->not->toUse('App\Domain\Scoring\ScoreCalculator');

arch('Domain نقيّ من إطار العمل')
    ->expect('App\Domain')
    ->not->toUse(['Illuminate\Support\Facades', 'request', 'auth', 'session']);

arch('لا منطق أعمال في المتحكّمات')
    ->expect('App\Http\Controllers')->toHaveMethodsDocumented()
    ->and('App\Http\Controllers')->not->toUse('Illuminate\Support\Facades\DB');
```

## 3. بوابات ضد فئات الأعطال المرصودة

```php
// INV-3 / B2 — لا قيم خام في الواجهة
it('لا تعرض أي صفحة قيمة نطاق خام', function (string $route) {
    $html = $this->actingAs($this->user)->get($route)->content();
    expect($html)->not->toMatch(
      '/\b(unknown|none|mine|biweekly|awareness|rough|irregular|site_and_social|failed|pending|ads|seo)\b/'
    );
})->with(['/app', '/app/projects', '/app/tools', '/app/consultations', '/app/reports/1', '/app/tasks']);

// INV-6 / B1 — الملاحة لا تكذب
it('كل عنصر ملاحة له مسار موجود', fn () =>
    expect(Nav::items()->pluck('route'))->each->toBeRegisteredRoute());
it('لا عنصرا ملاحة يشيران إلى نفس المسار', fn () =>
    expect(Nav::items()->where('state', NavState::Available)->pluck('route'))->toBeUnique());

// B5 — التعددية العربية
it('لا نص يجمع رقمًا باسم مفرد', fn () =>
    expect(langFiles())->not->toMatch('/:count\s+(رصيد|مشروع|تقرير|مهمة)\b/'));

// A3 — الدرجة الواحدة
it('كل عرض للدرجة يمر عبر ScoreReader');
it('لا تظهر درجتان بلاحقة /100 دون تسميتين مختلفتين في نفس الصفحة');

// B4 — العنوان المكرر
it('لا يتكرر عنوان التقرير داخل بطاقته');

// i18n
it('كل مفتاح ترجمة موجود في ar وen وfr');
it('كل حالة enum لها ترجمة في اللغات الثلاث');
```

## 4. المسار الذهبي (Playwright) — يمنع تكرار A1

```
✓ حساب مجاني برصيد صفر يرى تكلفة الاستشارة قبل السؤال الأول
✓ إسقاط المزوّد أثناء التشغيل ⇒ لا خصم، والإجابات محفوظة، والرسالة من نوع "ours"
✓ توليد تقرير ⇒ ظهور مهام تلقائيًا في /app/tasks
✓ قطع الشبكة أثناء الإجابة ⇒ لا تضيع إجابة
✓ كل رابط ملاحة يغيّر المحتوى فعليًا
✓ الشاشات الحرجة على 375px في ar/en × فاتح/داكن
```

## 5. خط الـ CI

```yaml
jobs:
  quality:
    - composer validate && php artisan about
    - vendor/bin/pint --test              # التنسيق
    - vendor/bin/phpstan analyse          # المستوى 8 على Domain
    - vendor/bin/pest --coverage --min=80 # Domain: 90%
    - npx stylelint "resources/css/**"    # نقاط التوقّف، !important، left/right
    - npm run tokens:verify               # المولَّدات مطابقة لـ tokens.json
    - npm run i18n:verify                 # اكتمال المفاتيح والـ enums
    - npx playwright test
    - npm run openapi:diff                # كسر العقد يفشل البناء
  flutter:
    - flutter analyze && flutter test
```

## 6. قواعد stylelint الإلزامية

- `media-feature-name-value-allowed-list` — نقاط التوقّف الأربع فقط.
- `declaration-no-important` — عدا طبقة `utilities`.
- `property-disallowed-list: [left, right, margin-left, margin-right, padding-left, padding-right]`.
- `declaration-property-value-allowed-list` — الألوان من `var(--*)` فقط.

## 7. سياسة إضافة بوابة

**كل عطل يُصلَح يضيف اختبارًا يمنع فئته، لا حالته.**
PR إصلاح بلا اختبار جديد يُرفض. هذه القاعدة وحدها هي ما يمنع عودة كل ما في هذه الوثيقة.
