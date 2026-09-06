# 04 — قاعدة الحقائق المشتركة

> يعالج العبء الأكبر على التبني: ٦٠ سؤالًا، و١١ أداة لكل منها استبيانها.
> ويعالج B2 (القيم الخام) من جذره.

## 1. الفكرة

**الأدوات تعلن حقائق مطلوبة، لا أسئلة.** المحرّك يسأل فقط عن الناقص أو المنتهي صلاحيته.

اليوم: `أداة → أسئلتها → إجابات محبوسة داخل الأداة`
المطلوب: `أداة → حقائق مطلوبة → قاعدة حقائق المشروع → يسأل عن الناقص فقط`

## 2. النموذج

```php
// project_facts
$t->foreignId('project_id')->index();
$t->string('key');                       // FactKey enum: budget_monthly, icp_clarity…
$t->json('value');
$t->string('type');                      // enum|number|money|text|list|boolean
$t->decimal('confidence', 3, 2);         // 0.00 – 1.00
$t->string('source');                    // user|inferred|crawled|imported|human_review
$t->timestamp('confirmed_at')->nullable();
$t->timestamp('valid_until')->nullable();
$t->unique(['project_id', 'key']);
// project_fact_history: نفس الأعمدة + سبب التغيير (لتتبّع تطوّر المشروع)
```

```php
final class FactStore {
    public function get(Project $p, FactKey $k): ?Fact;
    public function missing(Project $p, array $required): array;   // ما يجب سؤاله
    public function stale(Project $p): array;                      // ما انتهت صلاحيته
    public function put(Project $p, FactKey $k, mixed $v, FactSource $s, float $c): Fact;
}

interface Runnable { public function requiredFacts(): array; }  // FactKey[]
```

## 3. المكاسب المركّبة

1. **السؤال يُطرح مرة واحدة لكل مشروع.** الأداة العاشرة تكاد لا تسأل شيئًا —
   فتصبح «أدوات أكثر» ميزةً لا عبئًا. هذا يقلب اقتصاد المنتج رأسًا على عقب.
2. **الاستنتاج التلقائي.** أداة «افحص موقعي» القائمة تملأ حقائق `source=crawled`
   بثقة منخفضة. المستخدم **يؤكّد بضغطة** بدل أن يكتب — والتأكيد أقل احتكاكًا من الإدخال بمراحل.
3. **«معلومات تحتاج إلى تأكيد» تصبح مبنية على بيانات** (`confidence < 0.6`) لا نصًّا ثابتًا.
4. **`valid_until` يعطي سببًا مشروعًا للتواصل**: «ميزانيتك مسجّلة من ٤ أشهر — أهي كما هي؟»
   إعادة تفاعل مبنية على قيمة، لا إزعاج.
5. **النِّسب تصبح ذات معنى.** «أكملت ١٠٠٪ / ٣٣٪ / ٣٦٪» الحالية تبدو عشوائية لأنها
   لكل أداة على حدة؛ نسبة واحدة على مستوى المشروع أصدق وأوضح.

## 4. ترتيب الأسئلة بمكسب المعلومة

لكل `FactKey` وزن يعكس أثره على الدرجة والتوصيات. المحرّك يرتّب تنازليًا.

**قاعدة القيمة قبل الاكتمال:**

```
بعد ٨–١٠ حقائق عالية الوزن  →  تقرير مبدئي فوري (ثقة: متوسطة)
                             →  «أضف ٤ معلومات لرفع الدقة إلى عالية»  [أضفها →]
```

هذا يحلّ مشكلة الستين سؤالًا **من جذرها** بدل تخفيفها: المستخدم يرى قيمة بعد ٣ دقائق،
فيقرّر بنفسه أن يستثمر وقتًا أطول. ولو انقطع، خرج ومعه شيء.

## 5. القيم دائمًا مصنّفة (INV-3)

كل حقيقة نوعها `enum` لها enum مصنّف — وهنا يُقتل `unknown` و`mine` و`biweekly` من المنبع:

```php
enum AccountOwnership: string implements HasLabel {
    case Mine = 'mine';
    case Agency = 'agency';
    case Mixed = 'mixed';
    case Unknown = 'unknown';
    public function label(): string {
        return __("enums.account_ownership.{$this->value}");
    }
}
```

```php
// Domain/Shared/HasLabel.php
interface HasLabel { public function label(): string; }
```

```blade
{{-- المكوّن الوحيد المسموح لطباعة قيمة نطاق --}}
<x-value :of="$fact->value" />
{{-- في بيئة التطوير: يرمي استثناءً إن لم تكن القيمة HasLabel --}}
```

**حالة `unknown` خاصة:** لا تُعرض «غير معروف» فحسب، بل دائمًا مع دعوة:
«الميزانية — لم تُحدَّد بعد [أضفها]». المعلومة الناقصة فرصة تفاعل، لا فراغ.

## 6. الترحيل من الوضع الحالي

1. اجرد كل أسئلة الأدوات الـ ١١ وأنشئ `FactKey` موحّدًا لكل معنى مكرّر.
2. جدول تعيين `tool_question → fact_key` (وثّقه — ستحتاجه في المراجعة).
3. رحّل الإجابات القائمة إلى `project_facts` بـ `source=user, confidence=1.0`
   و`confirmed_at` = تاريخ الإجابة.
4. حوّل الأدوات لتعلن `requiredFacts()`.
5. أبقِ استبيانات الأدوات القديمة عاملة خلف feature flag حتى تستقر التغطية.

## 7. اختبارات

```php
it('لا يسأل عن حقيقة مؤكَّدة وصالحة');
it('يسأل عن حقيقة انتهت صلاحيتها');
it('يولّد تقريرًا مبدئيًا بعد الحد الأدنى من الحقائق');
it('كل FactKey من نوع enum له enum مصنّف ينفّذ HasLabel');
it('كل حالة enum لها ترجمة في ar وen وfr');
```
