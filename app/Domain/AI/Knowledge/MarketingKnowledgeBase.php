<?php

namespace App\Domain\AI\Knowledge;

use App\Domain\AI\Kernel\Knowledge\KnowledgeStore;
use App\Domain\AI\Semantic\SemanticMatcher;

/**
 * قاعدة المعرفة التسويقية — «معرفة المجال» المقنّنة التي يستند إليها الاستدلال.
 *
 * ثلاثة أنواع معرفة:
 *   - أطر (frameworks): نماذج عمل معتمدة (سلّم القيمة، القمع…).
 *   - معايير (benchmarks): أرقام مرجعية حسب القطاع تحوّل الحكم من «موجود/غائب»
 *     إلى «أعلى/أدنى من المعيار».
 *   - أنماط (patterns): حكمة موقف→بصيرة→إجراء، قابلة للاسترجاع الدلالي.
 *
 * المعرفة المقنّنة ثابتة ومُراجَعة (هنا)؛ والمعرفة المتعلّمة ديناميكية
 * (KnowledgeStore). الاسترجاع (RAG المحلي) يوحّد المصدرين بالتشابه الدلالي.
 */
class MarketingKnowledgeBase
{
    public function __construct(
        private readonly SemanticMatcher $matcher,
        private readonly KnowledgeStore $learned,
    ) {}

    /**
     * معايير مرجعية حسب القطاع (تقديرية عامة — تُهذَّب بالتعلّم لاحقاً).
     *
     * @return array{conversion_rate: float, primary_channels: array<int, string>, note: string}
     */
    public function benchmarksFor(?string $sector): array
    {
        $key = $this->normalizeSector($sector);

        return self::BENCHMARKS[$key] ?? self::BENCHMARKS['general'];
    }

    /**
     * @return array<int, array{key: string, name: string, when: string, steps: array<int, string>}>
     */
    public function frameworks(): array
    {
        return self::FRAMEWORKS;
    }

    /**
     * استرجاع دلالي محلي: أعلى K مقاطع معرفة صلةً بالاستعلام (أطر + أنماط + متعلّم).
     *
     * @return array<int, array{text: string, source: string, score: float}>
     */
    public function retrieve(string $query, int $k = 3): array
    {
        $corpus = $this->corpus();
        $scored = [];
        foreach ($corpus as $entry) {
            $score = $this->matcher->similarity($query, $entry['text']);
            if ($score > 0.0) {
                $scored[] = ['text' => $entry['text'], 'source' => $entry['source'], 'score' => $score];
            }
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, max(1, $k));
    }

    /**
     * كتلة نصّية جاهزة للحقن في برومبت إثراء LLM (دور «التطوير» فقط).
     */
    public function promptBlock(string $query, ?string $sector = null, int $k = 3): string
    {
        $bench = $this->benchmarksFor($sector);
        $lines = [
            'معايير القطاع المرجعية: تحويل ~'.$bench['conversion_rate'].'% · قنوات مناسبة: '.implode('، ', $bench['primary_channels']).'.',
        ];
        foreach ($this->retrieve($query, $k) as $hit) {
            $lines[] = '- '.$hit['text'];
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<int, array{text: string, source: string}>
     */
    private function corpus(): array
    {
        $entries = [];
        foreach (self::FRAMEWORKS as $f) {
            $entries[] = ['text' => $f['name'].': '.$f['when'].' — '.implode('؛ ', $f['steps']), 'source' => 'framework'];
        }
        foreach (self::PATTERNS as $p) {
            $entries[] = ['text' => $p, 'source' => 'pattern'];
        }
        foreach ($this->learned->all() as $fact) {
            $text = is_array($fact['data'] ?? null) ? (string) ($fact['data']['text'] ?? '') : '';
            if ($text !== '') {
                $entries[] = ['text' => $text, 'source' => 'learned'];
            }
        }

        return $entries;
    }

    private function normalizeSector(?string $sector): string
    {
        $s = mb_strtolower(trim((string) $sector));

        return match (true) {
            $s === '' => 'general',
            str_contains($s, 'ecom') || str_contains($s, 'متجر') || str_contains($s, 'تجار') => 'ecommerce',
            str_contains($s, 'b2b') || str_contains($s, 'خدمات') || str_contains($s, 'شركات') => 'b2b_services',
            str_contains($s, 'saas') || str_contains($s, 'برمج') || str_contains($s, 'تطبيق') => 'saas',
            str_contains($s, 'تعليم') || str_contains($s, 'دورات') || str_contains($s, 'course') => 'education',
            str_contains($s, 'مطعم') || str_contains($s, 'طعام') || str_contains($s, 'food') => 'food',
            str_contains($s, 'عقار') || str_contains($s, 'real') => 'real_estate',
            str_contains($s, 'صح') || str_contains($s, 'طب') || str_contains($s, 'health') => 'health',
            default => 'general',
        };
    }

    /** @var array<string, array{conversion_rate: float, primary_channels: array<int, string>, note: string}> */
    private const BENCHMARKS = [
        'general' => ['conversion_rate' => 2.5, 'primary_channels' => ['انستغرام', 'قوقل', 'واتساب'], 'note' => 'متوسط عام.'],
        'ecommerce' => ['conversion_rate' => 2.0, 'primary_channels' => ['انستغرام', 'تيك توك', 'قوقل تسوّق'], 'note' => 'التحويل حسّاس للثقة والشحن والدفع.'],
        'b2b_services' => ['conversion_rate' => 3.5, 'primary_channels' => ['لينكدإن', 'قوقل بحث', 'التوصية'], 'note' => 'دورة قرار أطول؛ الدليل والحالة العملية حاسمان.'],
        'saas' => ['conversion_rate' => 3.0, 'primary_channels' => ['قوقل بحث', 'المحتوى', 'التجربة المجانية'], 'note' => 'التفعيل والاحتفاظ أهم من الاكتساب.'],
        'education' => ['conversion_rate' => 4.0, 'primary_channels' => ['انستغرام', 'يوتيوب', 'تيك توك'], 'note' => 'المحتوى المجاني يبني الثقة قبل البيع.'],
        'food' => ['conversion_rate' => 6.0, 'primary_channels' => ['انستغرام', 'تيك توك', 'خرائط قوقل'], 'note' => 'القرب والصورة والتقييمات تحسم.'],
        'real_estate' => ['conversion_rate' => 1.5, 'primary_channels' => ['قوقل بحث', 'انستغرام', 'سناب شات'], 'note' => 'قيمة الصفقة عالية؛ جودة الليد أهم من عدده.'],
        'health' => ['conversion_rate' => 3.0, 'primary_channels' => ['قوقل بحث', 'انستغرام', 'خرائط قوقل'], 'note' => 'الثقة والمصداقية والمراجعات أساس.'],
    ];

    /** @var array<int, array{key: string, name: string, when: string, steps: array<int, string>}> */
    private const FRAMEWORKS = [
        ['key' => 'value_ladder', 'name' => 'سلّم القيمة', 'when' => 'لرفع قيمة العميل عبر الزمن', 'steps' => ['طُعم مجاني/رخيص', 'منتج أساسي', 'ترقية أعلى', 'اشتراك/تجديد']],
        ['key' => 'funnel', 'name' => 'قمع التسويق', 'when' => 'لتحويل الغريب إلى مشترٍ', 'steps' => ['وعي', 'اهتمام', 'رغبة', 'فعل', 'احتفاظ']],
        ['key' => 'jtbd', 'name' => 'المهمة المطلوب إنجازها (JTBD)', 'when' => 'لفهم دافع الشراء الحقيقي', 'steps' => ['الموقف', 'الدافع', 'النتيجة المرجوّة', 'البدائل الحالية']],
        ['key' => 'aida', 'name' => 'AIDA للرسالة', 'when' => 'لبناء رسالة إعلانية', 'steps' => ['انتباه', 'اهتمام', 'رغبة', 'فعل']],
        ['key' => 'positioning', 'name' => 'التموضع بالتمايز', 'when' => 'لتمييز العلامة', 'steps' => ['لمن؟', 'مقابل من؟', 'الفرق الوحيد؟', 'الدليل عليه؟']],
    ];

    /** @var array<int, string> أنماط حكمة قابلة للاسترجاع. */
    private const PATTERNS = [
        'الرسالة العامة («جودة عالية») تُضعف التحويل؛ استبدلها بفرق محدّد ملموس مبنيّ على ميزتك الحقيقية.',
        'الهدف الطموح فوق قاعدة وصول ضعيفة يستنزف الميزانية؛ ثبّت قناة واحدة وابنِ حضوراً أولياً قبل ملاحقة الرقم الكبير.',
        'التردّد عند الدفع بسبب الثقة يُعالَج بعكس المخاطرة: ضمان استرجاع + دفع عند الاستلام + دليل قرب زر الشراء.',
        'اكتساب عميل جديد أغلى 5 مرّات من إعادة تفعيل عميل حالي؛ ابنِ آلية احتفاظ ومتابعة بعد أول بيع.',
        'الوعد بلا دليل يزيد الشكّ لا الثقة؛ اربط كل وعد بشهادة أو رقم أو حالة عملية بجانبه.',
        'توزيع الجهد على قنوات متعددة قبل إتقان واحدة يبعثر الميزانية والتعلّم؛ ركّز حتى نتيجة مستقرّة ثم توسّع.',
        'السعر يبدو أقل إيلاماً حين يُؤطَّر بنقطة مقارنة تُعرَض قبله (سعر البديل الأغلى أو الأقل جودة).',
        'المؤشّر القائد (معدّل التحويل مثلاً) مع عتبة إنذار وإجراء أهم من مؤشّرات كثيرة للزينة.',
    ];
}
