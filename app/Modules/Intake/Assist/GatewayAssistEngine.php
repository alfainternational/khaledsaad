<?php

namespace App\Modules\Intake\Assist;

use App\Modules\Intake\Assist\Contracts\AssistEngine;
use App\Support\AI\AIRequest;
use App\Support\AI\StructuredRunner;

/**
 * توليد الدليل والمقترحات عبر البوابة المضبوطة من الإعدادات.
 *
 * المزوّد واحد لكل المنصة ويُختار من `config('ai.default')`، فلا يوجد مسار
 * استدعاء ثانٍ لا تُسجَّل تكلفته (§٤.٤). والفئة `standard` لا `advanced`: هذه
 * مهمة صياغة قصيرة بسياق معلوم، ووضع أقوى نموذج عليها يبطّئ الاستجابة أمام
 * مستخدم ينتظر بلا فرق يُذكر في الجودة.
 */
class GatewayAssistEngine implements AssistEngine
{
    public function __construct(private readonly StructuredRunner $runner) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function compose(QuestionDescriptor $question, array $context): AssistDraft
    {
        $payload = $this->runner->run(AIRequest::json(
            messages: [
                ['role' => 'system', 'content' => $this->system($question)],
                ['role' => 'user', 'content' => $this->user($question, $context)],
            ],
            schema: AssistDraft::schema($question->isChoice()),
            tier: 'standard',
            stage: 'question_assist',
            salvage: true,
        ));

        /*
         * الترشيح والمقترحات تُصفّى مقابل الخيارات الفعلية **بعد** التوليد لا
         * بالتعليمة وحدها: النموذج يخالفها أحيانًا فيعيد قيمة من عنده. قيمة غير
         * موجودة في الخيارات تُحدِث صفرًا صامتًا في خرائط نقاط المحاور لمن
         * اختارها، أو تسقط في التحقق برسالة «خيار غير معتمد» لا يفهمها أحد.
         */
        $recommended = $this->validRecommendation($question, $payload['recommended_value'] ?? null);

        return new AssistDraft(
            guide: trim((string) ($payload['guide'] ?? '')),
            suggestions: $this->suggestions($question, $payload['suggestions'] ?? []),
            recommendedValue: $recommended,
            recommendationReason: $recommended === null
                ? null
                : trim((string) ($payload['recommendation_reason'] ?? '')),
            basis: array_values(array_filter(array_map(
                fn ($item) => trim((string) $item),
                (array) ($payload['basis'] ?? []),
            ))),
        );
    }

    public function name(): string
    {
        return (string) config('ai.default', 'deepseek');
    }

    private function system(QuestionDescriptor $question): string
    {
        $shared = <<<'TEXT'
        أنت مستشار تسويق خليجي يساعد صاحب نشاط تجاري على الإجابة عن سؤال واحد في استمارة تشخيص.

        اللغة: عربية بلهجة بيضاء بلمسة خليجية. لا فصحى تقريرية جافة، ولا عامية ثقيلة. أسماء المنصات تُكتب كما تُعرف.

        قواعد ملزمة:
        - لا تكتب جملة سببية بصيغة الجزم. كل ما تقوله فرضية مبنية على ما وصفه صاحب النشاط، وقد يكون ناقصًا.
        - لا تخترع رقمًا ولا إحصاءً ولا اسم منافس لم يُذكر في السياق. إن احتجت رقمًا فاجعله مدى تقريبيًّا واذكر أنه تقدير.
        - كل مقترح يجب أن يكون خاصًّا بهذا النشاط تحديدًا: اذكر مدينته أو قطاعه أو نموذجه أو ميزانيته. مقترح يصلح لأي نشاط آخر مقترح مرفوض.
        - `guide` ليس تعريفًا للسؤال بل تعليم: قل بأي شيء تكون الإجابة كافية هنا، وما الذي يجعلها ناقصة. سطران إلى أربعة.
        - `why` في كل مقترح يشرح لماذا يناسب هذا النشاط بعينه، لا لماذا هو مقترح جيد عمومًا.
        - لا تمدح صاحب النشاط ولا تخوّفه. الوصف المحدد يكفي.
        TEXT;

        if ($question->isChoice()) {
            return $shared."\n\n".<<<'TEXT'
            هذا سؤال اختيار من قائمة مغلقة.

            - `recommended_value` يجب أن يكون **حرفيًّا** إحدى القيم المعطاة في `options`. لا تخترع قيمة جديدة ولا تُعد صياغتها ولا تترجمها.
            - `recommendation_reason` يشرح لماذا هذا الخيار أقرب لوصف هذا النشاط، ويذكر صراحةً أنه ترشيح لا حكم.
            - `suggestions` هنا ليست قيمًا تُدخل بل شرحًا للخيارات: كل عنصر يأخذ `value` = قيمة خيار موجودة، و`label` = اسم الخيار، و`why` = ماذا يعني اختياره لنشاط كهذا.
            TEXT;
        }

        return $shared."\n\n".<<<'TEXT'
        هذا سؤال مفتوح.

        - `value` في كل مقترح هو نصٌّ جاهز يُدخل في الخانة كما هو، مكتوب بصوت صاحب النشاط (بصيغة المتكلم أو الوصف المباشر) لا بصوت المستشار.
        - اجعل المقترحات مختلفة في الجوهر لا في الصياغة: احتمالات مختلفة لما قد يكون عليه نشاطه، لا ثلاث صيغ للجملة نفسها.
        - إن كان السؤال عن الجمهور أو العملاء، فكل مقترح شريحة كاملة: من هم، وأين، ولماذا يشترون.
        TEXT;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function user(QuestionDescriptor $question, array $context): string
    {
        $expectation = $question->expectation();

        $lines = [
            'النشاط التجاري:',
            json_encode($context['business'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            '',
            'ما نعرفه عنه من إجاباته السابقة (لا تسأله عنه من جديد، وابنِ عليه):',
            json_encode($context['known_facts'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            '',
            'السؤال المطروح الآن: '.$question->text,
        ];

        if ($question->help !== null && $question->help !== '') {
            $lines[] = 'شرح السؤال المعروض له: '.$question->help;
        }

        if ($question->why !== null && $question->why !== '') {
            $lines[] = 'سبب طرحه: '.$question->why;
        }

        $lines[] = 'نوع الإجابة: '.$question->type;

        if ($question->options !== []) {
            $lines[] = 'الخيارات المتاحة (القيمة ← النص المعروض):';
            foreach ($question->options as $option) {
                $lines[] = '- '.$option['value'].' ← '.$option['label'];
            }
        }

        /*
         * تعريف الكفاية يُمرَّر إلى النموذج نفسه لأنه هو ما ستُقاس به إجابة
         * المستخدم بعد قليل. لو أرشدَ النموذجُ إلى شيء ويقيس النظامُ شيئًا آخر،
         * لخرج المستخدم بإجابة اتّبع فيها الإرشاد ثم رأى درجة كفاية منخفضة بلا
         * سبب مفهوم.
         */
        if ($question->isMeasurable()) {
            $lines[] = '';
            $lines[] = 'تعريف الإجابة الكافية عن هذا السؤال في نظامنا: '.$expectation->sufficientAnswerLooksLike;
            $lines[] = 'الحد الأدنى المتوقَّع للطول: '.$expectation->minWords.' كلمة.';
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<int, array{label: string, value: string, why: string}>
     */
    private function suggestions(QuestionDescriptor $question, mixed $raw): array
    {
        $clean = [];

        foreach ((array) $raw as $item) {
            $value = trim((string) ($item['value'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));

            if ($value === '' || $label === '') {
                continue;
            }

            // في سؤال الاختيار المقترح شرحٌ لخيار قائم؛ ما لا يقابل خيارًا يُسقط.
            if ($question->isChoice() && $this->validRecommendation($question, $value) === null) {
                continue;
            }

            $clean[] = [
                'label' => $label,
                'value' => $value,
                'why' => trim((string) ($item['why'] ?? '')),
            ];
        }

        return $clean;
    }

    private function validRecommendation(QuestionDescriptor $question, mixed $value): ?string
    {
        if (! $question->isChoice() || ! is_scalar($value)) {
            return null;
        }

        $value = (string) $value;

        foreach ($question->options as $option) {
            if ((string) $option['value'] === $value) {
                return $value;
            }
        }

        return null;
    }
}
