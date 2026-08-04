<?php

namespace App\Modules\Learning;

use App\Models\MarketingExerciseAttempt;
use App\Models\MarketingLearningRun;
use App\Modules\Brain\BrainReader;

class MarketingLearningRecommender
{
    /**
     * Missing project knowledge is checked in product-value order, not lesson order.
     * The result is deterministic so the reason can always be explained.
     */
    private const PRIORITIES = [
        'business.audience' => [
            'exercise' => 'describe-real-customer',
            'reason' => 'نبدأ بتوضيح عميلك لأن معرفة من تخاطبه ستجعل رسالتك ومحتواك وإعلانك أدق.',
        ],
        'business.value_proposition' => [
            'exercise' => 'core-marketing-message',
            'reason' => 'نبدأ برسالتك لأن سبب الشراء منك يحتاج أن يكون واضحًا قبل زيادة المحتوى أو الإنفاق.',
        ],
        'business.primary_goal' => [
            'exercise' => 'marketing-reality-check',
            'reason' => 'نبدأ بتحديد النتيجة المطلوبة حتى لا تتوزع جهودك على أنشطة لا تخدم هدفًا واحدًا.',
        ],
        'business.active_channels' => [
            'exercise' => 'choose-marketing-channel',
            'reason' => 'نبدأ باختيار القناة الأساسية حتى تركز جهدك حيث يوجد عميلك فعلًا.',
        ],
        'business.tracking_maturity' => [
            'exercise' => 'measure-current-performance',
            'reason' => 'نبدأ بالقياس حتى تعرف ما الذي يستحق الاستمرار وما الذي يحتاج تغييرًا.',
        ],
        'business.content_cadence' => [
            'exercise' => 'weekly-content-calendar',
            'reason' => 'نبدأ بجدول محتوى تستطيع الاستمرار عليه بدل نشر متقطع يصعب قياسه.',
        ],
        'business.retention_motion' => [
            'exercise' => 'customer-loyalty-review',
            'reason' => 'نبدأ بما بعد البيع حتى لا تنتهي العلاقة بعد أول طلب وتدفع كل مرة لجذب عميل جديد.',
        ],
    ];

    public function __construct(
        private readonly MarketingCourseCatalog $catalog,
        private readonly BrainReader $brain,
    ) {}

    /**
     * @return array{exercise: array<string, mixed>|null, reason: string}
     */
    public function next(MarketingLearningRun $run): array
    {
        $run->loadMissing('project');
        $completed = $run->attempts()
            ->where('status', MarketingExerciseAttempt::STATUS_COMPLETED)
            ->pluck('exercise_key')
            ->all();

        if ($run->current_exercise_key !== null && ! in_array($run->current_exercise_key, $completed, true)) {
            return [
                'exercise' => $this->catalog->exercise($run->current_exercise_key),
                'reason' => 'تكمل من حيث توقفت، وإجاباتك السابقة محفوظة.',
            ];
        }

        foreach (self::PRIORITIES as $brainKey => $priority) {
            if ($this->brain->fact($run->project, $brainKey) !== null) {
                continue;
            }

            if (in_array($priority['exercise'], $completed, true)) {
                continue;
            }

            return [
                'exercise' => $this->catalog->exercise($priority['exercise']),
                'reason' => $priority['reason'],
            ];
        }

        $next = collect($this->catalog->exercises())
            ->reject(fn (array $exercise) => in_array($exercise['key'], $completed, true))
            ->sortBy([
                ['lesson_number', 'asc'],
                ['duration_minutes', 'asc'],
            ])
            ->first();

        return [
            'exercise' => $next,
            'reason' => $next === null
                ? 'أكملت جميع المهام. راجع نتائجك واختر ما تريد تحسينه.'
                : 'هذه أقرب مهمة غير منجزة، وستضيف مخرجًا جديدًا إلى خطة مشروعك.',
        ];
    }
}
