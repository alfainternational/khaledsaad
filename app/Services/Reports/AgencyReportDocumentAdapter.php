<?php

namespace App\Services\Reports;

use App\Models\AgencyReport;

/**
 * Keeps reports generated before the two-document format readable.
 * Frozen snapshots are never rewritten; they are adapted only for presentation.
 */
class AgencyReportDocumentAdapter
{
    /** @return array<string, mixed> */
    public function ownerSnapshot(AgencyReport $report): array
    {
        $snapshot = $report->snapshot;

        if (isset($snapshot['owner_report'])) {
            return $snapshot;
        }

        $problems = collect($snapshot['executive']['problems'] ?? [])->take(3)->values()->all();
        $priorities = collect($snapshot['priorities'] ?? [])->take(5)->map(function (array $item): array {
            $item['estimated_time'] = match ($item['effort'] ?? null) {
                'low' => 'من 30 إلى 60 دقيقة',
                'medium' => 'من ساعتين إلى أربع ساعات',
                'high' => 'يوم عمل أو أكثر',
                default => 'حدّد لها موعدًا هذا الأسبوع',
            };

            return $item;
        })->values()->all();

        $snapshot['owner_report'] = [
            'overview' => [
                'title' => 'أين يقف مشروعك الآن؟',
                'description' => $snapshot['executive']['position']
                    ?? $snapshot['project']['description']
                    ?? 'هذه نسخة محفوظة من حالة مشروعك وقت إنشاء التقرير.',
                'main_issue' => $problems[0]['title'] ?? 'لا توجد مشكلة واحدة مؤكدة تتقدم على بقية النقاط.',
            ],
            'numbers' => $snapshot['numbers'] ?? ['rows' => [], 'tracking_label' => null],
            'journey' => [
                'title' => 'أين يتوقف الناس؟',
                'description' => 'توضح الأرقام المسجلة أين نعرف ما يحدث وأين نحتاج إلى القياس أولًا.',
                'stages' => $snapshot['numbers']['rows'] ?? [],
            ],
            'problems' => $problems,
            'conflicts' => [],
            'unknowns' => collect($snapshot['data_gaps'] ?? [])->map(fn (string $item) => [
                'text' => $item,
                'resolution' => 'يُحسم بإجابة أو قياس واضح',
            ])->values()->all(),
            'this_week' => $priorities,
            'before_agency' => $snapshot['owner_guide'] ?? [],
            'readiness' => [
                'is_ready' => false,
                'message' => 'هذا إصدار سابق. أنشئ إصدارًا محدثًا بعد إكمال بيانات موجز الوكالة.',
                'requirements' => [],
            ],
            'private_details' => [
                'project' => [
                    'name' => $snapshot['project']['name'] ?? $report->project->name,
                    'description' => $snapshot['project']['description'] ?? null,
                    'industry' => $snapshot['project']['industry'] ?? null,
                    'stage' => $snapshot['project']['stage_label'] ?? $snapshot['project']['stage'] ?? null,
                    'geography' => $snapshot['project']['geography'] ?? null,
                    'website' => $snapshot['project']['website'] ?? null,
                    'business_model' => $snapshot['project']['business_model_label'] ?? null,
                    'primary_goal' => $snapshot['project']['primary_goal_label'] ?? null,
                    'value_proposition' => $snapshot['project']['value_proposition'] ?? null,
                ],
                'audiences' => $snapshot['audiences'] ?? [],
                'assets' => $snapshot['assets'] ?? ['rows' => []],
                'tools' => $snapshot['tools'] ?? [],
                'competitors' => $snapshot['competitors'] ?? ['items' => [], 'count' => 0],
                'evidence' => $snapshot['evidence'] ?? ['items' => [], 'count' => 0],
                'kpis' => $snapshot['kpis'] ?? [],
                'consultation' => $snapshot['consultation'] ?? null,
                'assumptions' => $snapshot['assumptions'] ?? [],
                'appendix' => $snapshot['appendix'] ?? [],
                'behaviour' => $snapshot['behaviour'] ?? ['tasks' => ['done' => 0, 'total' => 0]],
                'plan' => $snapshot['plan'] ?? ['30_days' => [], '60_days' => [], '90_days' => []],
                'methodology' => $snapshot['methodology'] ?? [],
                'different_readings' => $snapshot['cross_tool_synthesis']['divergences'] ?? [],
            ],
        ];

        return $snapshot;
    }

    /** @return array{agency_brief: array<string, mixed>} */
    public function legacyAgencySnapshot(AgencyReport $report): array
    {
        $snapshot = $report->snapshot;
        $answers = collect($snapshot['mandate']['answered'] ?? [])->keyBy('key');
        $answer = fn (string $key): ?string => $answers->get($key)['value'] ?? null;

        return ['agency_brief' => [
            'project' => [
                'name' => $snapshot['project']['name'] ?? $report->project->name,
                'description' => $snapshot['project']['description'] ?? null,
                'industry' => $snapshot['project']['industry'] ?? null,
                'business_model' => $snapshot['project']['business_model_label'] ?? null,
                'geography' => $snapshot['project']['geography'] ?? null,
                'stage' => $snapshot['project']['stage_label'] ?? null,
                'value_proposition' => $snapshot['project']['value_proposition'] ?? null,
                'website' => $snapshot['project']['website'] ?? null,
                'audiences' => collect($snapshot['audiences'] ?? [])->pluck('name')->filter()->values()->all(),
                'audience_details' => collect($snapshot['audiences'] ?? [])->map(fn (array $item) => [
                    'name' => $item['name'] ?? '',
                    'needs' => $item['pains'] ?? null,
                    'desired_result' => $item['gains'] ?? null,
                    'behaviour' => $item['behaviors'] ?? null,
                ])->values()->all(),
                'competitors' => $snapshot['competitors']['items'] ?? [],
                'known_context' => [],
            ],
            'baseline' => [
                'rows' => collect($snapshot['numbers']['rows'] ?? [])->map(fn (array $row) => [
                    'label' => $row['label'] ?? '',
                    'value' => ($row['value'] ?? null) === null
                        ? 'غير معروف حتى الآن'
                        : trim($row['value'].' '.($row['unit'] ?? '')),
                    'known' => ($row['value'] ?? null) !== null,
                ])->values()->all(),
                'tracking' => $snapshot['numbers']['tracking_label'] ?? 'حالة القياس غير معروفة حتى الآن',
                'previous_attempts' => $answer('previous_attempts'),
                'previous_provider' => $answer('previous_agency'),
                'current_customer_source' => $answer('what_works_now'),
                'kpis' => $snapshot['kpis'] ?? [],
            ],
            'goal' => [
                'primary' => $snapshot['project']['primary_goal_label'] ?? $snapshot['project']['primary_goal'] ?? null,
                'success_metric' => $snapshot['mandate']['success_metric'] ?? null,
                'period' => $answer('ninety_day_outcome'),
            ],
            'scope' => [
                'services' => $snapshot['mandate']['services'] ?? [],
                'start_window' => $answer('start_window'),
                'constraints' => $answer('constraints'),
                'out_of_scope' => $snapshot['scope']['out_of_scope'] ?? [],
            ],
            'assets' => $snapshot['assets'] ?? ['rows' => []],
            'workflow' => [
                'decision_maker' => $answer('decision_maker'),
                'approval_time' => $answer('approval_time'),
                'lead_response_owner' => $answer('lead_response_owner'),
                'internal_capacity' => $answer('internal_capacity'),
                'payment_constraints' => $answer('payment_constraints'),
                'review_cadence' => $snapshot['scope']['review_cadence'] ?? 'مراجعة تشغيلية أسبوعية.',
            ],
            'terms' => [
                'account_ownership' => $snapshot['scope']['account_ownership'] ?? 'تبقى الحسابات والبيانات باسم المشروع.',
                'declared_ownership' => $answer('account_ownership'),
                'engagement_model' => $answer('engagement_model'),
                'contract_duration' => $answer('contract_duration'),
                'budget_flexibility' => $answer('budget_flexibility'),
                'exit_condition' => 'تُسلّم الملفات والصلاحيات والبيانات الحديثة عند انتهاء التعاقد.',
            ],
            'proposal' => [
                'requirements' => $snapshot['proposal_requirements'] ?? [],
                'pricing_rows' => [
                    ['key' => 'management', 'label' => 'أتعاب الإدارة الشهرية'],
                    ['key' => 'media', 'label' => 'ميزانية الإعلان المدفوعة للمنصات'],
                    ['key' => 'production', 'label' => 'الإنتاج والتصوير والتصميم'],
                    ['key' => 'tools', 'label' => 'الأدوات والاشتراكات والرسوم الأخرى'],
                ],
                'budget' => $snapshot['commercials'] ?? [
                    'stated_budget' => $snapshot['project']['monthly_budget'] ?? null,
                    'budget_currency' => null,
                    'includes_agency_fee' => null,
                    'effective_media' => null,
                    'verdict' => null,
                ],
                'evaluation_criteria' => $answer('evaluation_criteria'),
            ],
            'submission' => [
                'deadline' => $answer('proposal_deadline'),
                'method' => $answer('proposal_submission'),
            ],
            'readiness' => [
                'is_ready' => true,
                'legacy' => true,
                'missing_count' => 0,
                'message' => 'هذه نسخة قديمة محفوظة كما كانت وقت المشاركة.',
                'requirements' => [],
            ],
        ]];
    }
}
