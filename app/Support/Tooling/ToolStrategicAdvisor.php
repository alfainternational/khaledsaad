<?php

namespace App\Support\Tooling;

use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use Illuminate\Support\Str;

class ToolStrategicAdvisor
{
    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $brief
     * @param  array<string, mixed>  $briefAssessment
     * @param  array<int, array<string, mixed>>  $upstreamContext
     * @return array<string, mixed>
     */
    public function advise(
        Tool $tool,
        array $profile,
        ?Project $project,
        array $brief,
        array $briefAssessment,
        array $upstreamContext = [],
    ): array {
        $signals = $this->signalValues($profile, $brief, $briefAssessment, $upstreamContext);
        $requirements = $this->requirementsFor($tool->code);
        $knownSignals = [];
        $missingSignals = [];

        foreach ($requirements as $signalKey => $label) {
            $value = trim((string) ($signals[$signalKey] ?? ''));

            if ($value !== '') {
                $knownSignals[] = [
                    'label' => $label,
                    'value' => Str::limit($value, 120, '…'),
                ];
                continue;
            }

            $missingSignals[] = $label;
        }

        $readyScore = (int) round((count($knownSignals) / max(count($requirements), 1)) * 100);
        $fieldSuggestions = array_filter($this->fieldSuggestions($tool->code, $signals), fn ($value) => is_string($value) && trim($value) !== '');
        $nextAction = $this->nextActionForTool($tool->code, $missingSignals);
        $projectName = $project?->name ?? 'المشروع الحالي';

        return [
            'readiness_score' => $readyScore,
            'signals' => $knownSignals,
            'missing_signals' => $missingSignals,
            'field_suggestions' => $fieldSuggestions,
            'summary' => [
                'headline' => $readyScore >= 70
                    ? 'هذه الأداة تملك سياقاً كافياً لتخرج بمادة أقرب للتنفيذ.'
                    : 'الأداة ستستفيد أكثر إذا أغلقت الفجوات التالية قبل التشغيل الكامل.',
                'text' => 'يجري ربط '.$projectName.' بهذه الأداة عبر ملف المشروع والنتائج السابقة، حتى لا تبدأ من شاشة فارغة في كل مرة.',
                'bullets' => array_values(array_filter([
                    $knownSignals !== [] ? 'الإشارات الجاهزة الآن: '.implode('، ', array_column($knownSignals, 'label')).'.' : null,
                    $missingSignals !== [] ? 'أهم ما ينقص هذه الأداة: '.implode('، ', array_slice($missingSignals, 0, 3)).'.' : null,
                    $nextAction['reason'] ?? null,
                ])),
            ],
            'next_action' => $nextAction,
        ];
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $brief
     * @param  array<string, mixed>  $briefAssessment
     * @param  array<int, array<string, mixed>>  $upstreamContext
     * @return array<string, string>
     */
    private function signalValues(array $profile, array $brief, array $briefAssessment, array $upstreamContext): array
    {
        $upstreamHeadline = trim((string) ($upstreamContext[0]['headline'] ?? ''));

        return [
            'audience' => trim((string) data_get($brief, 'audience.ideal_customer', $profile['audience'] ?? '')),
            'pain' => trim((string) data_get($brief, 'audience.pain_points', '')),
            'goal' => trim((string) data_get($brief, 'goals.primary_goal', $profile['primary_goal'] ?? '')),
            'channel' => trim((string) data_get($brief, 'current_marketing.channels', '')),
            'offer' => trim((string) data_get($brief, 'business.offer', '')),
            'promise' => trim((string) data_get($brief, 'positioning.promise', '')),
            'positioning' => trim((string) data_get($brief, 'positioning.edge', '')),
            'market_gap' => trim((string) data_get($brief, 'competition.gap', '')),
            'voice' => trim((string) data_get($brief, 'brand.voice', '')),
            'priority' => trim((string) data_get($brief, 'execution.priority', '')),
            'next_asset' => trim((string) data_get($brief, 'execution.next_asset', '')),
            'metric' => trim((string) data_get($brief, 'goals.success_metric', '')),
            'timeframe' => trim((string) data_get($brief, 'goals.timeframe', '')),
            'current_state' => trim((string) data_get($brief, 'current_marketing.current_state', '')),
            'market' => trim((string) data_get($brief, 'business.market', $profile['country'] ?? '')),
            'executive' => implode('، ', array_slice((array) data_get($briefAssessment, 'reports.executive_brief', []), 0, 2)),
            'upstream' => $upstreamHeadline,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function requirementsFor(string $toolCode): array
    {
        return match ($toolCode) {
            'diagnosis' => [
                'goal' => 'الهدف الحالي',
                'priority' => 'عنق الزجاجة الحالي',
                'audience' => 'الجمهور المرجعي',
            ],
            'ideal-customer' => [
                'audience' => 'صورة العميل',
                'pain' => 'ألم الجمهور',
                'goal' => 'الهدف التجاري',
                'channel' => 'القنوات الحالية',
            ],
            'positioning' => [
                'offer' => 'العرض الحالي',
                'audience' => 'الجمهور',
                'positioning' => 'ميزة التمركز',
                'market_gap' => 'فجوة السوق',
            ],
            'offer-builder' => [
                'offer' => 'العرض الأساسي',
                'audience' => 'الشريحة المستهدفة',
                'promise' => 'الوعد أو النتيجة',
                'positioning' => 'الفرق عن البدائل',
                'next_asset' => 'المخرج المتوقع من العرض',
            ],
            'marketing-plan' => [
                'goal' => 'الهدف الحالي',
                'audience' => 'الشريحة المقصودة',
                'channel' => 'القناة الحالية',
                'priority' => 'الأولوية التنفيذية',
                'offer' => 'العرض أو الرسالة',
            ],
            'content-plan' => [
                'goal' => 'هدف المحتوى',
                'audience' => 'الشريحة المستهدفة',
                'channel' => 'القناة الأساسية',
                'voice' => 'صوت العلامة',
                'next_asset' => 'المخرج أو الموضوع الأقرب',
            ],
            default => [
                'goal' => 'الهدف',
                'audience' => 'الجمهور',
                'offer' => 'العرض',
            ],
        };
    }

    /**
     * @param  array<string, string>  $signals
     * @return array<string, string>
     */
    private function fieldSuggestions(string $toolCode, array $signals): array
    {
        return match ($toolCode) {
            'diagnosis' => [
                'main_goal' => $signals['goal'],
                'main_bottleneck' => $signals['priority'] ?: $signals['current_state'],
                'needed_outcome' => $signals['next_asset'] ?: $signals['upstream'],
                'biggest_gap' => $signals['priority'],
                'priority_week' => $signals['next_asset'],
                'priority_month' => $signals['goal'],
            ],
            'ideal-customer' => [
                'customer_type' => $signals['audience'],
                'customer_problem' => $signals['pain'],
                'customer_goal' => $signals['promise'] ?: $signals['goal'],
                'buying_trigger' => $signals['priority'] ?: $signals['current_state'],
                'best_channel' => $signals['channel'],
                'language_clue' => $signals['voice'],
            ],
            'positioning' => [
                'category' => $signals['offer'],
                'main_difference' => $signals['positioning'],
                'buyer_reason' => $signals['promise'] ?: $signals['goal'],
                'market_gap' => $signals['market_gap'],
                'positioning_statement' => $signals['executive'],
                'competitive_edge' => $signals['positioning'],
            ],
            'offer-builder' => [
                'offer_name' => $signals['offer'],
                'offer_audience' => $signals['audience'],
                'offer_result' => $signals['promise'] ?: $signals['goal'],
                'offer_deliverables' => $signals['next_asset'],
                'offer_difference' => $signals['positioning'],
                'offer_sales_copy' => $signals['executive'],
            ],
            'marketing-plan' => [
                'plan_goal' => $signals['goal'],
                'plan_segment' => $signals['audience'],
                'channel_primary' => $signals['channel'],
                'core_angle' => $signals['positioning'] ?: $signals['offer'],
                'two_week_actions' => $signals['next_asset'] ?: $signals['priority'],
                'north_metric' => $signals['metric'],
                'plan_risks' => $signals['current_state'],
                'plan_review' => $signals['timeframe'],
            ],
            'content-plan' => [
                'content_goal' => $signals['goal'],
                'content_audience' => $signals['audience'],
                'content_topics' => $signals['next_asset'] ?: $signals['offer'],
                'content_formats' => $signals['channel'],
                'content_cta' => $signals['promise'] ?: $signals['goal'],
                'content_review' => $signals['metric'],
            ],
            default => [
                'goal' => $signals['goal'],
                'audience' => $signals['audience'],
            ],
        };
    }

    /**
     * @param  list<string>  $missingSignals
     * @return array<string, string|null>
     */
    private function nextActionForTool(string $toolCode, array $missingSignals): array
    {
        if ($missingSignals === []) {
            return [
                'action_type' => 'current_tool',
                'recommended_tool_code' => null,
                'recommended_tool_label' => null,
                'reason' => 'يمكنك تشغيل هذه الأداة الآن والاعتماد على الملف الحالي بدل إعادة جمع نفس المعلومات يدوياً.',
            ];
        }

        if (in_array('صورة العميل', $missingSignals, true) || in_array('الجمهور', $missingSignals, true) || in_array('الجمهور المرجعي', $missingSignals, true)) {
            return [
                'action_type' => 'tool',
                'recommended_tool_code' => 'ideal-customer',
                'recommended_tool_label' => 'العميل المثالي',
                'reason' => 'الجمهور ما زال غير ثابت بما يكفي، والأفضل سد هذه الفجوة قبل البناء فوقها.',
            ];
        }

        if (in_array('ميزة التمركز', $missingSignals, true) || in_array('فجوة السوق', $missingSignals, true)) {
            return [
                'action_type' => 'tool',
                'recommended_tool_code' => $toolCode === 'positioning' ? null : 'positioning',
                'recommended_tool_label' => $toolCode === 'positioning' ? 'تحرير brief المشروع' : 'التمركز',
                'reason' => 'الرسالة أو العرض سيبقيان عامين حتى يثبت الفرق الحقيقي الذي يجب أن يراه السوق.',
            ];
        }

        if (in_array('القناة الحالية', $missingSignals, true) || in_array('القنوات الحالية', $missingSignals, true) || in_array('القناة الأساسية', $missingSignals, true)) {
            return [
                'action_type' => 'brief',
                'recommended_tool_code' => null,
                'recommended_tool_label' => 'تحرير brief المشروع',
                'reason' => 'هذه الأداة تحتاج معرفة القناة أو المسار الحالي كي لا تنتهي بتوصيات عامة أو غير قابلة للتنفيذ.',
            ];
        }

        return [
            'action_type' => 'brief',
            'recommended_tool_code' => null,
            'recommended_tool_label' => 'تحرير brief المشروع',
            'reason' => 'إغلاق الفجوات المتبقية في ملف المشروع سيرفع جودة هذه الأداة مباشرة ويقلل التكرار على المستخدم.',
        ];
    }
}
