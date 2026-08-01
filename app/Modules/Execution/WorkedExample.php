<?php

namespace App\Modules\Execution;

/**
 * المثال التطبيقي: النص الذي ينسخه صاحب النشاط ويستعمله كما هو.
 *
 * التمييز الحاسم: هذا ليس وصفًا لما ينبغي كتابته، بل النص نفسه. «اكتب رسالة
 * تعريف قصيرة» توصية؛ و«السلام عليكم أستاذ فهد، أنا…» مثال. صاحب النشاط
 * الذي فهم المسألة ولم يعرف كيف ينفّذها يحتاج الثاني لا الأول.
 *
 * تدرّج الدليل (§٤.١): المثال دائمًا `inferred` — هو اجتهاد منهجي مبني على
 * ما وصفه المستخدم، لا شيء مرصود. يحمل وسم «فرضية» في كل سطح يظهر فيه،
 * وسقف ثقته ٧٥ كبقية الفرضيات.
 */
final class WorkedExample
{
    /**
     * أنواع المخرج التي يصحّ أن يُسلَّم بها مثال. النوع يحدّد كيف يُعرض
     * وأين يُلصق، فقائمة مغلقة لا نص حر: «رسالة» تُنسخ إلى واتساب، و«بنية
     * صفحة» تُنسخ إلى المحرر، والخلط بينهما يربك القارئ.
     *
     * @var array<string, string>
     */
    public const KINDS = [
        'message' => 'رسالة جاهزة',
        'email' => 'بريد جاهز',
        'post' => 'منشور جاهز',
        'ad' => 'نص إعلان جاهز',
        'script' => 'سكربت مكالمة أو فيديو',
        'page_outline' => 'بنية صفحة',
        'checklist' => 'قائمة تنفيذ',
        'spreadsheet' => 'جدول متابعة',
        'reply' => 'ردّ جاهز',
    ];

    /**
     * @param  array<int, string>  $notes  ما يجب أن يغيّره قبل الاستخدام.
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $title,
        public readonly string $body,
        public readonly array $notes = [],
        public readonly string $source = 'deterministic',
    ) {}

    /**
     * بناء من مخرج نموذج غير موثوق. يُرجع null بدل الرمي: مثال معطوب لا
     * يُسقط توصية سليمة، والأرضية الحتمية تتكفّل بالفراغ.
     *
     * @param  mixed  $payload
     */
    public static function fromPayload($payload, string $source = 'ai'): ?self
    {
        if (! is_array($payload)) {
            return null;
        }

        $body = trim((string) ($payload['body'] ?? ''));
        $title = trim((string) ($payload['title'] ?? ''));

        // نص أقصر من ذلك ليس مثالًا يُنسخ بل عنوانًا آخر للتوصية.
        if (mb_strlen($body) < 40 || $title === '') {
            return null;
        }

        $kind = (string) ($payload['kind'] ?? 'message');

        return new self(
            kind: isset(self::KINDS[$kind]) ? $kind : 'message',
            title: $title,
            body: $body,
            notes: array_values(array_filter(array_map(
                fn ($note) => trim((string) $note),
                is_array($payload['notes'] ?? null) ? $payload['notes'] : [],
            ), fn (string $note) => $note !== '')),
            source: $source,
        );
    }

    /**
     * @param  array<string, mixed>|null  $stored
     */
    public static function fromStored(?array $stored): ?self
    {
        if ($stored === null) {
            return null;
        }

        return self::fromPayload($stored, (string) ($stored['source'] ?? 'deterministic'));
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? self::KINDS['message'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'kind_label' => $this->kindLabel(),
            'title' => $this->title,
            'body' => $this->body,
            'notes' => $this->notes,
            'source' => $this->source,
            // المثال اجتهاد لا رصد، مهما كان مصدره. يُثبَّت هنا لا في
            // الواجهة حتى لا يسقط الوسم في سطح ينسى إضافته.
            'evidence_level' => 'inferred',
        ];
    }

    /**
     * مخطط الحقل كما يُطلب من النموذج — مصدر واحد لمسار التركيب ومسار
     * تطوير المهمة، فلا ينجرف تعريفان لنفس الشيء.
     *
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['kind', 'title', 'body'],
            'properties' => [
                'kind' => ['type' => 'string', 'enum' => array_keys(self::KINDS)],
                'title' => ['type' => 'string', 'minLength' => 5],
                'body' => ['type' => 'string', 'minLength' => 40],
                'notes' => [
                    'type' => 'array',
                    'maxItems' => 4,
                    'items' => ['type' => 'string'],
                ],
            ],
        ];
    }
}
