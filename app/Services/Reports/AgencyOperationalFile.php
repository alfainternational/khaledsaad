<?php

namespace App\Services\Reports;

use App\Models\BenchmarkSnapshot;
use App\Models\ContentFeedback;
use App\Models\Project;
use App\Models\Report;
use App\Models\Task;
use App\Models\ToolRunFile;
use Illuminate\Support\Collection;

/**
 * الأقسام التشغيلية التي تحتاجها الوكالة لتسعّر وتبدأ، لا لتفهم فقط.
 *
 * ثلاثة مبادئ تحكم كل ما هنا:
 * - كل رقم يحمل مصدره ومستوى الثقة فيه؛ رقم من ذاكرة صاحب المشروع لا
 *   يُقرأ كرقم من تحليلات مربوطة، والخلط بينهما يُنتج تسعيرًا خاطئًا.
 * - الغائب يُعلن غيابه بدل أن يُحذف صفه، لأن «غير معروف» معلومة تشغيلية
 *   تُحدد سؤال أول اجتماع.
 * - لا يُشتق رقم من رقم مجهول: نسبة التحويل لا تُحسب ما لم يكن البسط
 *   والمقام مصرّحًا بهما.
 */
class AgencyOperationalFile
{
    /**
     * مقاييس الأداء التي تسأل عنها كل وكالة في أول اجتماع.
     *
     * kind يحدد كيف تُقرأ الثقة: القياسي يعتمد على نضج التتبع، والمصرّح
     * يعرفه صاحب المشروع من دفاتره ولا علاقة له بأدوات التحليلات.
     *
     * @var array<string, array{label: string, unit: ?string, kind: string, benchmark: ?string}>
     */
    private const METRICS = [
        'monthly_visitors' => ['label' => 'زيارات شهرية', 'unit' => 'زيارة', 'kind' => 'tracked', 'benchmark' => null],
        'monthly_leads' => ['label' => 'استفسارات شهرية', 'unit' => 'استفسار', 'kind' => 'tracked', 'benchmark' => null],
        'monthly_customers' => ['label' => 'عملاء شهريًا', 'unit' => 'عميل', 'kind' => 'tracked', 'benchmark' => null],
        'known_cac' => ['label' => 'تكلفة استحواذ العميل', 'unit' => null, 'kind' => 'tracked', 'benchmark' => 'cost_per_customer'],
        'average_order_value' => ['label' => 'متوسط قيمة الطلب', 'unit' => null, 'kind' => 'stated', 'benchmark' => null],
        'average_price' => ['label' => 'متوسط السعر', 'unit' => null, 'kind' => 'stated', 'benchmark' => null],
        'repeat_rate' => ['label' => 'نسبة العملاء المتكررين', 'unit' => '%', 'kind' => 'stated', 'benchmark' => null],
        'response_time' => ['label' => 'زمن الرد على استفسار', 'unit' => null, 'kind' => 'stated', 'benchmark' => null],
        'sales_cycle' => ['label' => 'طول دورة البيع', 'unit' => null, 'kind' => 'stated', 'benchmark' => null],
        'margin_known' => ['label' => 'هامش الربح', 'unit' => null, 'kind' => 'stated', 'benchmark' => null],
    ];

    /**
     * الأصول الرقمية التي يحتاج فريق التنفيذ الوصول إليها في اليوم الأول.
     *
     * @var array<string, array{label: string, keys: array<int, string>, why: string}>
     */
    private const ASSETS = [
        'website' => [
            'label' => 'الموقع الإلكتروني',
            'keys' => ['website', 'website_url', 'has_website', 'domain_ready'],
            'why' => 'وجهة كل حملة؛ بدونه لا صفحة هبوط ولا قياس.',
        ],
        'analytics' => [
            'label' => 'تحليلات الزيارات',
            'keys' => ['tracking_setup', 'tracking_maturity', 'measurement'],
            'why' => 'بدونها لا خط أساس ولا إثبات أثر.',
        ],
        'search_console' => [
            'label' => 'أدوات مشرفي المواقع',
            'keys' => ['search_console'],
            'why' => 'مصدر بيانات البحث العضوي الوحيد المجاني والموثوق.',
        ],
        'ads' => [
            'label' => 'حسابات الإعلانات',
            'keys' => ['ad_platforms', 'channels_used', 'active_channels'],
            'why' => 'تاريخ الإنفاق السابق يمنع تكرار تجربة فاشلة.',
        ],
        'google_business' => [
            'label' => 'ملف النشاط على خرائط جوجل',
            'keys' => ['google_business', 'local_business'],
            'why' => 'أسرع مكسب لأي نشاط له موقع فعلي.',
        ],
        'customer_data' => [
            'label' => 'قاعدة بيانات العملاء',
            'keys' => ['customer_data_ownership', 'followup_system'],
            'why' => 'أرخص قناة إعادة تسويق، وأخطرها قانونيًا إن أُسيء استخدامها.',
        ],
        'content' => [
            'label' => 'مكتبة المحتوى والمواد',
            'keys' => ['proof_assets', 'product_content_ready', 'content_pages', 'formats'],
            'why' => 'غيابها يعني تكلفة إنتاج تُضاف إلى العرض.',
        ],
        'marketplaces' => [
            'label' => 'المنصات والأسواق الأخرى',
            'keys' => ['marketplace_presence', 'presale_presence'],
            'why' => 'قنوات بيع قائمة قد تتعارض أو تتكامل مع الخطة.',
        ],
    ];

    /**
     * مفاتيح الملكية والصلاحيات — تُقرأ مرة وتُطبَّق على كل صفوف الجرد.
     */
    private const OWNERSHIP_KEYS = ['who_owns_assets', 'customer_data_ownership', 'protection'];

    /**
     * ذاكرة الطلب الواحد: الأقسام الأربعة تقرأ نفس الإجابات، ولا داعي
     * لاستعلام جديد لكل قسم.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $answerCache = [];

    public function __construct(private readonly AgencyStateLedger $ledger) {}

    /**
     * القسم 3: الوضع الحالي بالأرقام — قيمة، مرجع سوق، ومستوى ثقة.
     *
     * @return array<string, mixed>
     */
    public function numbers(Project $project): array
    {
        $answers = $this->answers($project);
        $tracking = $this->scalar($answers['tracking_maturity'] ?? null);
        $rows = [];

        foreach (self::METRICS as $key => $metric) {
            $value = $this->scalar($answers[$key] ?? null);

            if ($value === null && $metric['kind'] === 'stated') {
                // المصرّح الغائب يظهر كسؤال مفتوح لا كصف فارغ في جدول.
                $rows[] = $this->row($key, $metric, null, 'unknown', $project);

                continue;
            }

            $rows[] = $this->row(
                $key,
                $metric,
                $value,
                $this->confidence($value, $metric['kind'], $tracking),
                $project,
            );
        }

        $derived = $this->conversionRate($answers, $tracking);

        if ($derived !== null) {
            $rows[] = $derived;
        }

        $known = collect($rows)->reject(fn (array $row) => $row['confidence'] === 'unknown');

        return [
            'rows' => $rows,
            'tracking_maturity' => $tracking,
            'tracking_label' => $this->ledger->optionLabel('tracking_maturity', $tracking),
            'summary' => [
                'total' => count($rows),
                'known' => $known->count(),
                'measured' => $known->where('confidence', 'measured')->count(),
                'estimated' => $known->where('confidence', 'estimated')->count(),
            ],
            'note' => 'مستوى الثقة مشتق من نضج التتبع المعلن، لا من تقدير المنصة. الرقم المقدَّر صالح للتوجيه لا للتعاقد عليه.',
        ];
    }

    /**
     * القسم 4: جرد الأصول والوصول — ما الموجود، ومن يملكه، وهل يُمنح يوم 1.
     *
     * @return array<string, mixed>
     */
    public function assets(Project $project): array
    {
        $answers = $this->answers($project);
        $ownership = collect(self::OWNERSHIP_KEYS)
            ->map(fn (string $key) => $this->readable($key, $answers[$key] ?? null))
            ->filter()
            ->implode(' · ');

        $rows = [];

        foreach (self::ASSETS as $key => $asset) {
            $evidence = collect($asset['keys'])
                ->map(fn (string $field) => $this->readable($field, $answers[$field] ?? null))
                ->filter()
                ->values();

            $rows[] = [
                'key' => $key,
                'label' => $asset['label'],
                'why' => $asset['why'],
                'status' => $evidence->isEmpty() ? 'unknown' : 'declared',
                'status_label' => $evidence->isEmpty() ? 'غير معروف' : 'مصرّح به',
                'detail' => $evidence->implode(' · '),
            ];
        }

        $unknown = collect($rows)->where('status', 'unknown')->pluck('label')->values()->all();

        return [
            'rows' => $rows,
            'ownership_note' => $ownership !== '' ? $ownership : null,
            'unknown' => $unknown,
            'readiness_percent' => $rows === []
                ? 0
                : (int) round((count($rows) - count($unknown)) / count($rows) * 100),
            'note' => 'ما وُسم «غير معروف» ليس غيابًا للأصل بل غياب لتصريح عنه؛ يُحسم بسؤال واحد قبل بدء التنفيذ.',
        ];
    }

    /**
     * القسم 10: سجل السلوك — هل ينفّذ هذا العميل ما يُتفق عليه.
     *
     * @param  Collection<int, Report>  $reports
     * @return array<string, mixed>
     */
    public function behaviour(Project $project, Collection $reports): array
    {
        $tasks = $project->tasks;
        $done = $tasks->where('status', Task::STATUS_DONE)->count();
        $reportIds = $project->reports()->pluck('id');

        $feedback = ContentFeedback::where('subject_type', Report::class)
            ->whereIn('subject_id', $reportIds)
            ->get();

        return [
            'tasks' => [
                'total' => $tasks->count(),
                'done' => $done,
                'in_progress' => $tasks->where('status', Task::STATUS_DOING)->count(),
                'open' => $tasks->where('status', Task::STATUS_TODO)->count(),
                'completion_percent' => $tasks->isEmpty() ? null : (int) round($done / $tasks->count() * 100),
            ],
            'feedback' => [
                'useful' => $feedback->where('verdict', ContentFeedback::VERDICT_UP)->count(),
                'not_useful' => $feedback->where('verdict', ContentFeedback::VERDICT_DOWN)->count(),
            ],
            'engagement' => [
                'tools_completed' => $reports->count(),
                'reports_total' => $reportIds->count(),
                'first_activity' => $project->created_at?->toDateString(),
                'last_activity' => $project->reports()->max('created_at'),
            ],
            'trend' => $this->trend($project),
            'note' => 'مؤشر على وتيرة التنفيذ لا حكم على صاحب المشروع؛ يُقرأ لضبط توقعات سرعة الاعتماد والتنفيذ.',
        ];
    }

    /**
     * الملحقان: الأدلة المرفوعة، والأصول الجاهزة للنشر فورًا.
     *
     * @return array<string, mixed>
     */
    public function appendix(Project $project, string $evidenceVisibility): array
    {
        $files = ToolRunFile::whereIn('tool_run_id', $project->runs()->pluck('id'))->get();
        $geo = $project->geoPack;

        return [
            'files' => [
                'count' => $files->count(),
                'mode' => $evidenceVisibility,
                'items' => $evidenceVisibility === 'full'
                    ? $files->map(fn (ToolRunFile $file) => [
                        'name' => $file->original_name,
                        'type' => $file->mime_type,
                        'size_kb' => (int) round(((int) $file->size_bytes) / 1024),
                        'extracted' => $file->extraction_status === 'done',
                        // مقتطف يثبت أن الملف قُرئ فعلًا، دون نقل مضمونه كاملًا.
                        'excerpt' => $file->extracted_text === null
                            ? null
                            : mb_substr(trim($file->extracted_text), 0, 220),
                    ])->values()->all()
                    : [],
            ],
            'ready_assets' => $geo === null ? null : [
                'facts' => is_array($geo->facts) ? count($geo->facts) : 0,
                'faq' => is_array($geo->faq) ? count($geo->faq) : 0,
                'has_jsonld' => filled($geo->jsonld),
                'has_llms_txt' => filled($geo->llms_txt),
                'credibility' => $geo->credibility,
                'generated_at' => $geo->generated_at?->toDateString(),
                'note' => 'حزمة ظهور جاهزة للنشر على الموقع دون إنتاج جديد — مكسب أسبوع أول ملموس.',
            ],
        ];
    }

    /**
     * @param  array{label: string, unit: ?string, kind: string, benchmark: ?string}  $metric
     * @return array<string, mixed>
     */
    private function row(
        string $key,
        array $metric,
        ?string $value,
        string $confidence,
        Project $project,
    ): array {
        return [
            'key' => $key,
            'label' => $metric['label'],
            'unit' => $metric['unit'],
            'value' => $value === null ? null : $this->readable($key, $value),
            'benchmark' => $metric['benchmark'] === null
                ? null
                : $this->benchmark($metric['benchmark'], $project),
            'confidence' => $confidence,
            'confidence_label' => $this->confidenceLabel($confidence),
        ];
    }

    /**
     * نسبة التحويل لا تُصرَّح بل تُشتق — وثقتها لا تتجاوز أضعف طرفيها.
     *
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>|null
     */
    private function conversionRate(array $answers, ?string $tracking): ?array
    {
        $visitors = (float) $this->scalar($answers['monthly_visitors'] ?? null);
        $customers = (float) $this->scalar($answers['monthly_customers'] ?? null);

        if ($visitors <= 0 || $customers <= 0) {
            return null;
        }

        $confidence = $this->confidence('1', 'tracked', $tracking);

        return [
            'key' => 'conversion_rate',
            'label' => 'نسبة التحويل من زيارة إلى عميل',
            'unit' => '%',
            'value' => number_format($customers / $visitors * 100, 2),
            'benchmark' => null,
            'confidence' => $confidence === 'measured' ? 'derived' : $confidence,
            'confidence_label' => $confidence === 'measured'
                ? 'مشتقة من رقمين مقيسين'
                : $this->confidenceLabel($confidence),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function benchmark(string $metric, Project $project): ?array
    {
        $snapshot = BenchmarkSnapshot::where('metric', $metric)
            ->where(fn ($query) => $query->where('industry', $project->industry)->orWhereNull('industry'))
            ->orderByDesc('fetched_at')
            ->first();

        if ($snapshot === null) {
            return null;
        }

        return [
            'range' => number_format((float) $snapshot->value_low).' – '.number_format((float) $snapshot->value_high),
            'unit' => $snapshot->unit,
            'source' => $snapshot->source_name,
            'source_url' => $snapshot->source_url,
            'fetched_at' => $snapshot->fetched_at?->toDateString(),
        ];
    }

    /**
     * الاتجاه عبر الإصدارات: لقطة واحدة لا تفرّق بين ركود وصعود.
     *
     * @return array<string, mixed>|null
     */
    private function trend(Project $project): ?array
    {
        $scores = $project->reports()
            ->whereNotNull('score')
            ->whereHas('toolRun.toolVersion.tool', fn ($query) => $query->where('key', 'marketing-score'))
            ->orderBy('created_at')
            ->get(['score', 'created_at']);

        if ($scores->count() < 2) {
            return null;
        }

        $first = $scores->first();
        $last = $scores->last();
        $delta = (int) $last->score - (int) $first->score;

        return [
            'from' => (int) $first->score,
            'to' => (int) $last->score,
            'delta' => $delta,
            'direction' => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'),
            'direction_label' => match (true) {
                $delta > 0 => 'صاعدة',
                $delta < 0 => 'هابطة',
                default => 'ثابتة',
            },
            'measurements' => $scores->count(),
            'days' => (int) $first->created_at->diffInDays($last->created_at),
        ];
    }

    private function confidence(?string $value, string $kind, ?string $tracking): string
    {
        if ($value === null || $value === '') {
            return 'unknown';
        }

        if ($kind === 'stated') {
            return 'stated';
        }

        return match ($tracking) {
            'full' => 'measured',
            'basic' => 'partial',
            default => 'estimated',
        };
    }

    private function confidenceLabel(string $confidence): string
    {
        return match ($confidence) {
            'measured' => 'مقيس بتتبع كامل',
            'partial' => 'مقيس جزئيًا',
            'estimated' => 'مقدَّر من الذاكرة',
            'stated' => 'مصرّح به من دفاتر المشروع',
            'derived' => 'مشتق',
            default => 'غير معروف',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function answers(Project $project): array
    {
        if (isset($this->answerCache[$project->id])) {
            return $this->answerCache[$project->id];
        }

        /*
         * نفس مصدر دفتر الحالة حرفيًا — ذاكرة الإجابات مدموجة بملف المشروع.
         * قراءة الذاكرة وحدها كانت تُظهر أصلًا «غير معروف» بينما يعرضه
         * الدفتر في الصفحة نفسها، فيتناقض قسمان في مستند واحد.
         */
        return $this->answerCache[$project->id] = collect($this->ledger->knownAnswers($project))
            ->map(fn (array $answer) => $answer['value'])
            ->all();
    }

    private function scalar(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        if ($value === null || $value === '' || ! is_scalar($value)) {
            return null;
        }

        return trim((string) $value);
    }

    private function readable(string $key, mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        return $this->ledger->optionLabel($key, $value);
    }
}
