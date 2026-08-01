<?php

namespace App\Modules\Execution;

/**
 * ما يجعل التوصية قابلة للتنفيذ: خطوات حقيقية ومثال يُنسخ.
 *
 * المسار الواحد لكل التوصيات في المنصة — مسار الأداة، المسار اليدوي،
 * والتشخيص الشامل — حتى لا يوجد سطحان يملآن نفس الحقول بمنطقين.
 *
 * القاعدة: مخرج النموذج يُقبل إن كان صالحًا فعلًا، ويُستبدل بالأرضية
 * الحتمية إن غاب أو كان صوريًّا. «صوري» هنا معرَّف لا متروك للحدس: خطوة
 * واحدة تكرّر نص الوصف ليست خطوات، ومثالٌ من سطرين ليس مثالًا يُنسخ.
 *
 * ما لا يفعله: لا يخترع رقمًا ولا اسمًا ولا مصدرًا. الفراغات تبقى ظاهرة
 * بين قوسين مربعين ليملأها صاحب النشاط (§٤.٣).
 */
class RecommendationEnricher
{
    /** أقصر خطوة يصح أن تُعدّ خطوة. ما دونها عنوان مبتور. */
    private const MIN_STEP_LENGTH = 15;

    private const MAX_STEPS = 6;

    public function __construct(
        private readonly DeterministicExampleFactory $examples,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  مخرج النموذج لتوصية واحدة.
     * @return array{action_steps: array<int, string>, worked_example: array<string, mixed>, example_source: string}
     */
    public function enrich(array $payload, ExampleContext $context): array
    {
        $title = (string) ($payload['title'] ?? '');
        $description = (string) ($payload['description'] ?? '');

        $steps = $this->steps($payload, $description);
        $example = WorkedExample::fromPayload($payload['worked_example'] ?? null);

        if ($example === null) {
            $example = $this->examples->build($title, $description, $context, $steps);
        }

        return [
            'action_steps' => $steps,
            'worked_example' => $example->toArray(),
            'example_source' => $example->source,
        ];
    }

    /**
     * خطوات النموذج إن صحّت، وإلا خطوات الأرضية.
     *
     * الخطوة التي تكرّر الوصف حرفيًّا تُرفض: كانت هذه حالة الحشو القديمة —
     * `action_steps = [description]` — فتبدو التوصية وكأن لها خطة وليس لها.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function steps(array $payload, string $description): array
    {
        $raw = $payload['action_steps'] ?? null;

        if (! is_array($raw)) {
            return $this->examples->fallbackSteps();
        }

        $steps = [];

        foreach ($raw as $step) {
            if (! is_string($step) && ! is_numeric($step)) {
                continue;
            }

            $step = trim((string) $step);

            if (mb_strlen($step) < self::MIN_STEP_LENGTH) {
                continue;
            }

            // تطابق الخطوة مع الوصف = لا خطوة. المقارنة على النص المجرّد
            // حتى لا تمرّ نسخة تختلف بعلامة ترقيم واحدة.
            if ($this->normalize($step) === $this->normalize($description)) {
                continue;
            }

            if (in_array($step, $steps, true)) {
                continue;
            }

            $steps[] = $step;
        }

        // خطوة واحدة ليست تسلسلًا؛ من كان له مسار حقيقي كتب اثنتين فأكثر.
        if (count($steps) < 2) {
            return $this->examples->fallbackSteps();
        }

        return array_slice($steps, 0, self::MAX_STEPS);
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', preg_replace('/[[:punct:]«»؟،؛]/u', '', $value)) ?? '');
    }
}
