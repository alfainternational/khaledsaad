<?php

namespace App\Modules\Execution;

use App\Models\Project;
use App\Modules\Shared\Sectors\Sector;

/**
 * ما نعرفه عن النشاط قبل كتابة المثال — من `Brain` والملف، لا بسؤال جديد (§٩).
 *
 * القيم كلها اختيارية عمدًا: مشروع لم يُكمل ملفه يستحق مثالًا أيضًا، لكن
 * بنائبٍ صريح («نشاطك») بدل اسم مخترع. اختراع اسم عميل أو رقم في مثال
 * يُنسخ حرفيًّا أخطر من اختراعه في تحليل — لأنه يُرسَل كما هو.
 */
final class ExampleContext
{
    public function __construct(
        public readonly string $businessName,
        public readonly ?string $sector = null,
        public readonly ?string $audience = null,
        public readonly ?string $valueProposition = null,
        public readonly ?string $geography = null,
        public readonly ?string $primaryGoal = null,
        public readonly ?string $website = null,
    ) {}

    public static function fromProject(?Project $project): self
    {
        $profile = $project?->profile;

        // extras قد تكون null لا مصفوفة فارغة، والفهرسة على null تُطلق
        // تحذيرًا في PHP 8 حتى مع ?? — فتُطبَّع قبل القراءة لا بعدها.
        $extras = is_array($profile?->extras) ? $profile->extras : [];

        return new self(
            businessName: self::clean($project?->name) ?? __('نشاطك'),
            sector: $project?->sector,
            audience: self::clean($extras['audience'] ?? null)
                ?? self::clean($extras['target_audience'] ?? null),
            valueProposition: self::clean($profile?->value_proposition),
            geography: self::clean($profile?->geography),
            primaryGoal: self::clean($profile?->primary_goal),
            website: self::clean($profile?->website),
        );
    }

    /**
     * تسمية ما يبيعه النشاط بلسان قطاعه، لا بكلمة «منتج» المحايدة.
     * مدرسة تبيع «مقعدًا»، ومكتب عقاري يبيع «وحدة»، والمتجر يبيع «منتجًا».
     */
    public function offeringNoun(): string
    {
        return match ($this->sector) {
            Sector::EDUCATION => __('المقعد الدراسي'),
            Sector::REAL_ESTATE => __('الوحدة'),
            Sector::ECOMMERCE => __('المنتج'),
            default => __('الخدمة'),
        };
    }

    /**
     * كيف يسمّي القطاع مشتريَه. الفارق ليس تجميليًّا: «ولي أمر» و«مشتري»
     * لا يُخاطبان بنفس النبرة، ومثالٌ يخاطب الأول بلسان الثاني يُهمَل.
     */
    public function buyerNoun(): string
    {
        return match ($this->sector) {
            Sector::EDUCATION => __('ولي الأمر'),
            Sector::REAL_ESTATE => __('الباحث عن سكن'),
            Sector::ECOMMERCE => __('المشتري'),
            default => __('العميل'),
        };
    }

    /**
     * الوعد كما كتبه صاحب النشاط، أو نائب صريح عنه.
     *
     * النائب مكتوب بصيغة الفراغ الظاهر لا بادعاء: المستخدم يرى «[اكتب هنا…]»
     * فيعرف أن عليه ملؤه، بدل أن يرسل وعدًا لم يقله أحد.
     */
    public function promise(): string
    {
        return $this->valueProposition ?? __('[اكتب هنا في سطر واحد: ما الذي تعطيه ولا يعطيه غيرك]');
    }

    public function audienceLabel(): string
    {
        return $this->audience ?? $this->buyerNoun();
    }

    public function placeLabel(): string
    {
        return $this->geography ?? __('[مدينتك]');
    }

    private static function clean(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
