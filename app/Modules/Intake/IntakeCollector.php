<?php

namespace App\Modules\Intake;

use App\Models\Project;
use App\Modules\Brain\BrainWriter;
use App\Modules\Shared\Evidence\EvidenceLevel;
use Illuminate\Support\Collection;

/**
 * يحوّل ما قاله صاحب النشاط إلى حقائق في الدماغ.
 *
 * نظير `ReadinessCollector` على الجانب الاستنتاجي: ذاك يقرأ موقعًا حقيقيًّا
 * فيكتب `measured`، وهذا يقرأ إجابات فيكتب `inferred`. الفرق بينهما هو أساس
 * التسعير كله (§٥)، ولذلك لا توجد هنا حالة واحدة تكتب فوق `inferred`.
 *
 * لماذا جامع منفصل بدل كتابة الأدوات مباشرة في الدماغ؟ لأن سبع أدوات تسأل عن
 * الشيء نفسه بسبعة أسماء. لو كتبت كل أداة بمفتاحها لصار في الدماغ سبع حقائق
 * عن «ما يميّزك»، ولانهار التعارض والتعاقب: `BrainWriter` يقارن بالمفتاح، فلا
 * يرى أن `differentiator` و`your_edge` قولان في الشيء نفسه.
 *
 * الجامع لا يستدعي شبكة ولا نموذجًا. تشغيله مرتين بلا تغيير لا يكتب شيئًا،
 * لأن `BrainWriter` يتجاهل إعادة تأكيد القيمة نفسها من المصدر نفسه.
 */
class IntakeCollector
{
    public const SOURCE = 'Intake';

    public function __construct(private readonly BrainWriter $brain) {}

    /**
     * جمع كل ما يمكن جمعه الآن.
     *
     * يعيد المفاتيح التي كُتبت فعلًا — ما نقص منها فجوةٌ تُعلَن في التغطية
     * ولا تُملأ بتقدير (§٤.٣).
     *
     * @return array<int, string>
     */
    public function collect(Project $project): array
    {
        $project->loadMissing(['profile', 'audiences', 'competitors']);

        $answers = $this->answers($project);
        $written = [];

        foreach (IntakeFactMap::all() as $key => $definition) {
            $value = $this->resolve($project, $answers, $definition);

            if ($value === null) {
                /*
                 * كان معروفًا ثم زال مصدره: يُسحب ولا يُترك. حقيقة قديمة بلا
                 * سند تُبقي الدرجة مرتفعة على ماضٍ انقضى — وهذا أسوأ من فجوة
                 * معلنة، لأنه يخفي التراجع بدل أن يكشفه (§٩).
                 */
                $this->brain->retract($project, $key, self::SOURCE, onlyIfOwned: true);

                continue;
            }

            $this->brain->record(
                project: $project,
                key: $key,
                value: $value,
                level: EvidenceLevel::Inferred,
                sourceModule: self::SOURCE,
                sourceReference: 'project_answers',
            );

            $written[] = $key;
        }

        return $written;
    }

    /**
     * إجابات المشروع المهمّة وحدها، مفهرسة بمفتاح الحقل.
     *
     * @return Collection<string, mixed>
     */
    private function answers(Project $project): Collection
    {
        return $project->answers()
            ->whereIn('field_key', IntakeFactMap::answerKeys())
            ->get()
            ->mapWithKeys(fn ($answer) => [$answer->field_key => $answer->value()]);
    }

    /**
     * @param  Collection<string, mixed>  $answers
     * @param  array<string, mixed>  $definition
     */
    private function resolve(Project $project, Collection $answers, array $definition): mixed
    {
        $candidates = [];

        foreach ($definition['answers'] ?? [] as $field) {
            $candidates[] = $answers->get($field);
        }

        if (isset($definition['profile'])) {
            $candidates[] = $project->profile?->{$definition['profile']};
        }

        if (isset($definition['relation'])) {
            $candidates[] = $this->fromRelation($project, $definition['relation']);
        }

        return match ($definition['shape']) {
            'list' => $this->asList($candidates, (bool) ($definition['merge'] ?? false)),
            'number' => $this->asNumber($candidates),
            'choice' => $this->asChoice($candidates, $definition['values'] ?? []),
            default => $this->asText($candidates),
        };
    }

    /**
     * الأسماء المسجَّلة في المشروع نفسه — أقوى من نصّ كُتب في خانة.
     *
     * المنافس «المؤكد» وحده يُحتسب: المرشّح الذي لم يؤكده صاحب النشاط فرضية
     * نظام لا معرفة عنه.
     *
     * @return array<int, string>
     */
    private function fromRelation(Project $project, string $relation): array
    {
        return match ($relation) {
            'audiences' => $project->audiences->pluck('name')->filter()->values()->all(),
            'competitors' => $project->competitors
                ->where('status', 'confirmed')
                ->pluck('name')->filter()->values()->all(),
            default => [],
        };
    }

    /**
     * @param  array<int, mixed>  $candidates
     */
    private function asText(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                $candidate = implode('، ', array_filter(array_map(
                    static fn ($item) => is_scalar($item) ? trim((string) $item) : '',
                    $candidate,
                )));
            }

            if (is_bool($candidate) || $candidate === null) {
                continue;
            }

            $text = trim((string) $candidate);

            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $candidates
     * @return array<int, string>|null
     */
    private function asList(array $candidates, bool $merge): ?array
    {
        $merged = [];

        foreach ($candidates as $candidate) {
            $items = $this->itemsOf($candidate);

            if ($items === []) {
                continue;
            }

            if (! $merge) {
                return $items;
            }

            $merged = [...$merged, ...$items];
        }

        // القيم المكرّرة عبر مصادر مختلفة قول واحد لا قولين — ولو عُدّت مرتين
        // لبلغ المحور هدفه بإجابة واحدة كُرِّرت في أداتين.
        $merged = array_values(array_unique($merged));

        return $merged === [] ? null : $merged;
    }

    /**
     * @return array<int, string>
     */
    private function itemsOf(mixed $candidate): array
    {
        if (is_array($candidate)) {
            return array_values(array_filter(array_map(
                static fn ($item) => is_scalar($item) ? trim((string) $item) : '',
                $candidate,
            )));
        }

        if (is_bool($candidate) || $candidate === null) {
            return [];
        }

        $text = trim((string) $candidate);

        return $text === '' ? [] : [$text];
    }

    /**
     * @param  array<int, mixed>  $candidates
     */
    private function asNumber(array $candidates): int|float|null
    {
        foreach ($candidates as $candidate) {
            if (is_numeric($candidate)) {
                return $candidate + 0;
            }
        }

        return null;
    }

    /**
     * قيمة معيارية أو لا شيء.
     *
     * القيمة الخام غير المعروفة تُهمَل عمدًا: `AxisScorer` يعطي المفتاح غير
     * الموجود في الخريطة صفرًا، فكتابة قيمة مجهولة تُنتج «أجاب وحصل على صفر»
     * بدل «لم يجب» — وهما حالتان مختلفتان تمامًا في قراءة التقرير.
     *
     * @param  array<int, mixed>  $candidates
     * @param  array<string, string>  $values
     */
    private function asChoice(array $candidates, array $values): ?string
    {
        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                $candidate = $candidate[0] ?? null;
            }

            if ($candidate === null) {
                continue;
            }

            $raw = trim((string) $candidate);

            if (isset($values[$raw])) {
                return $values[$raw];
            }
        }

        return null;
    }
}
