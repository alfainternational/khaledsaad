<?php

namespace App\Support\AI;

use App\Domain\AI\Models\AITemplate;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;

class StudioTemplateReadinessGate
{
    public function __construct(
        private readonly StudioTemplateContractRegistry $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function assess(AITemplate $template, Workspace $workspace, ?Project $project, array $context): array
    {
        $definition = $this->registry->definitionFor($template);
        $profile = $context['workspace_profile'] ?? [];
        $brief = $context['project_brief'] ?? [];
        $briefReports = $context['project_brief_assessment']['reports'] ?? [];
        $briefSignals = $this->briefSignals($brief, $briefReports);
        $toolCodes = collect($context['tool_summaries'] ?? [])->pluck('tool_code')->filter()->all();
        $humanSignals = count($context['client_notes'] ?? [])
            + count($context['approval_notes'] ?? [])
            + count($context['comment_notes'] ?? [])
            + $briefSignals['human_notes'];

        $requirements = [
            'project' => [
                'missing' => $project === null,
                'label' => 'لا يوجد مشروع مرتبط يمدّ القالب بسياق تشغيلي فعلي.',
                'reason' => 'القالب يصبح عاماً ومفصولاً عن عميل وسياق تنفيذي حقيقي.',
                'critical' => false,
            ],
            'audience' => [
                'missing' => trim((string) ($profile['audience'] ?? '')) === '' && $briefSignals['audience'] === false,
                'label' => 'الجمهور المستهدف غير محدد بوضوح في ملف المساحة.',
                'reason' => 'الكتابة ستنتهي بصياغات عامة لا تعرف من تخاطب.',
                'critical' => true,
            ],
            'primary_goal' => [
                'missing' => trim((string) ($profile['primary_goal'] ?? '')) === '' && $briefSignals['goal'] === false,
                'label' => 'الهدف التجاري أو التسويقي الحالي غير محدد.',
                'reason' => 'المخرج لن يعرف هل يكتب للبيع أم للثقة أم للوعي أم للإغلاق.',
                'critical' => true,
            ],
            'country' => [
                'missing' => trim((string) ($profile['country'] ?? '')) === '' && $briefSignals['market'] === false,
                'label' => 'السوق أو الدولة المرجعية للمحتوى غير محددة.',
                'reason' => 'اللهجة والأمثلة والسياق السوقي ستبقى عامة أو تخمينية.',
                'critical' => false,
            ],
            'human_signal' => [
                'missing' => $humanSignals === 0,
                'label' => 'لا توجد ملاحظات بشرية كافية من العميل أو الفريق أو الاعتماد.',
                'reason' => 'الملف التحليلي لن يلتقط تفضيلات حقيقية أو حساسية القرار.',
                'critical' => true,
            ],
            'evidence_signal' => [
                'missing' => empty($context['tool_summaries']) && empty($context['tool_runs']),
                'label' => 'لا توجد مخرجات أدوات أو تشغيلات سابقة تعطي مادة تحليلية كافية.',
                'reason' => 'التوليد سيتحول إلى اجتهاد إنشائي بلا إشارات تثبت الاتجاه.',
                'critical' => true,
            ],
            'offer_signal' => [
                'missing' => ! $this->hasAnySignal($toolCodes, ['offer-builder', 'promise-builder', 'package-builder', 'pricing-strategy']) && $briefSignals['offer'] === false,
                'label' => 'لا توجد إشارات عرض أو وعد أو تسعير يمكن البناء عليها.',
                'reason' => 'القالب سيكتب دون عرض واضح أو سبب شراء حقيقي.',
                'critical' => true,
            ],
            'followup_signal' => [
                'missing' => ! $this->hasAnySignal($toolCodes, ['follow-up-sequence', 'offer-builder', 'customer-journey', 'campaign-builder']),
                'label' => 'لا توجد إشارات متابعة أو رحلة عميل أو عرض تساعد على بناء تسلسل متابعة.',
                'reason' => 'رسائل المتابعة ستصبح مكررة أو غير مرتبطة بسبب تواصل حقيقي.',
                'critical' => true,
            ],
            'content_signal' => [
                'missing' => ! $this->hasAnySignal($toolCodes, ['content-plan', 'marketing-plan', 'campaign-builder', 'positioning']) && $briefSignals['channels'] === false,
                'label' => 'لا توجد مادة استراتيجية كافية لبناء خطة محتوى أسبوعية منضبطة.',
                'reason' => 'الخطة ستتحول إلى أفكار عامة بدل محتوى مربوط بالرسالة والقمع.',
                'critical' => true,
            ],
            'diagnosis_signal' => [
                'missing' => ! $this->hasAnySignal($toolCodes, ['diagnosis', 'swot-analysis', 'problem-definition', 'performance-review']),
                'label' => 'لا توجد إشارات تشخيص أو أداء يمكن أن يقوم عليها التشخيص البراندي.',
                'reason' => 'التشخيص سيصبح انطباعياً لا قرارياً.',
                'critical' => true,
            ],
            'positioning_signal' => [
                'missing' => ! $this->hasAnySignal($toolCodes, ['positioning', 'ideal-customer', 'competitor-analysis', 'market-analysis', 'offer-builder']) && $briefSignals['positioning'] === false,
                'label' => 'لا توجد إشارات تمركز أو جمهور أو منافسة كافية لبناء موضع قابل للدفاع.',
                'reason' => 'بيان التموضع سيخرج عاماً ومسطحاً بلا فرق حقيقي.',
                'critical' => true,
            ],
            'voice_signal' => [
                'missing' => ! $this->hasAnySignal($toolCodes, ['tagline-builder', 'positioning', 'ideal-customer', 'content-plan']) && $humanSignals === 0 && $briefSignals['voice'] === false,
                'label' => 'لا توجد إشارات كافية لبناء دليل صوت ونبرة مخصص لهذا البراند.',
                'reason' => 'دليل الصوت سيصبح مثالياً نظرياً وغير مرتبط بالشخصية الفعلية.',
                'critical' => true,
            ],
        ];

        $requiredKeys = collect($definition['critical_context'] ?? [])
            ->filter(fn (mixed $key): bool => is_string($key) && $key !== '')
            ->values();

        $missingByKey = $requiredKeys
            ->mapWithKeys(function (string $key) use ($requirements): array {
                $requirement = $requirements[$key] ?? null;

                if ($requirement === null || $requirement['missing'] !== true) {
                    return [];
                }

                return [$key => $requirement];
            });

        $missing = $missingByKey
            ->values()
            ->all();

        $criticalMissing = collect($missing)->filter(fn (array $item): bool => $item['critical'] === true)->count();
        $strictBlockingKeys = collect($definition['strict_blocking_context'] ?? [])
            ->filter(fn (mixed $key): bool => is_string($key) && $key !== '')
            ->values();
        $strictBlockingMissing = $strictBlockingKeys
            ->filter(fn (string $key): bool => $missingByKey->has($key))
            ->values()
            ->all();
        $isBlocking = $strictBlockingMissing !== []
            || $criticalMissing >= 2
            || ($criticalMissing >= 1 && count($missing) >= 3);

        return [
            'is_blocking' => $isBlocking,
            'definition' => $definition,
            'missing' => $missing,
            'missing_keys' => array_values($missingByKey->keys()->all()),
            'strict_blocking_missing_keys' => $strictBlockingMissing,
            'questions' => $definition['missing_questions'] ?? [],
        ];
    }

    /**
     * @param  array<int, string>  $toolCodes
     * @param  array<int, string>  $expected
     */
    private function hasAnySignal(array $toolCodes, array $expected): bool
    {
        return collect($toolCodes)->intersect($expected)->isNotEmpty();
    }

    /**
     * @param  array<string, mixed>  $brief
     * @param  array<string, mixed>  $briefReports
     * @return array<string, bool|int>
     */
    private function briefSignals(array $brief, array $briefReports): array
    {
        return [
            'audience' => trim((string) data_get($brief, 'audience.ideal_customer', '')) !== ''
                || ! empty($briefReports['audience_snapshot']),
            'goal' => trim((string) data_get($brief, 'goals.primary_goal', '')) !== '',
            'market' => trim((string) data_get($brief, 'business.market', '')) !== '',
            'offer' => trim((string) data_get($brief, 'business.offer', '')) !== ''
                || ! empty($briefReports['offer_positioning']),
            'channels' => trim((string) data_get($brief, 'current_marketing.channels', '')) !== '',
            'positioning' => trim((string) data_get($brief, 'positioning.edge', '')) !== ''
                || trim((string) data_get($brief, 'competition.gap', '')) !== '',
            'voice' => trim((string) data_get($brief, 'brand.voice', '')) !== ''
                || trim((string) data_get($brief, 'brand.tone_rules', '')) !== '',
            'human_notes' => collect([
                data_get($brief, 'business.summary'),
                data_get($brief, 'audience.pain_points'),
                data_get($brief, 'current_marketing.current_state'),
                data_get($brief, 'execution.delivery_notes'),
            ])->filter(fn ($item) => is_string($item) && trim($item) !== '')->count(),
        ];
    }
}
