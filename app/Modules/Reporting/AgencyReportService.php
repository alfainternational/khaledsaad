<?php

namespace App\Modules\Reporting;

use App\Models\AgencyReport;
use App\Models\ConsultationSession;
use App\Models\Project;
use App\Models\Report;
use App\Models\User;
use App\Modules\Intake\ConsultationContextBuilder;
use App\Modules\Intake\ConsultationReportGate;
use App\Services\Marketing\BudgetPlanner;
use App\Support\Marketing\BriefQuestions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgencyReportService
{
    /**
     * الحد الأدنى الذي يجعل موجز الوكالة قابلًا للاستخدام لا مجرد تقرير واحد.
     *
     * @var array<string, string>
     */
    private const CORE_TOOLS = [
        'marketing-score' => 'درجة الجاهزية التسويقية',
        'brand-clarity' => 'وضوح العلامة',
        'audience-map' => 'خريطة الجمهور',
    ];

    private const VISIBILITY_LEVELS = ['full', 'summary', 'private'];

    public function __construct(
        private readonly AgencyStateLedger $ledger,
        private readonly BudgetPlanner $budget,
        private readonly AgencyOperationalFile $operational,
        private readonly ConsultationReportGate $reportGate,
        private readonly ConsultationContextBuilder $consultations,
        private readonly CrossToolSynthesis $crossTool,
    ) {}

    /**
     * حفظ موجز التكليف على ملف المشروع.
     *
     * حقلان يخرجان من الـJSON إلى أعمدة حقيقية لأن بقية النظام يقرأهما:
     * الخدمات المطلوبة (تحدد مستوى الأتعاب) وهل المبلغ شامل للأتعاب
     * (يحدد ما يتبقى للإعلان، وعليه تُحسب كل الأرقام المتوقعة).
     *
     * @param  array<string, mixed>  $brief
     */
    public function saveBrief(Project $project, array $brief): void
    {
        $profile = $project->profile()->firstOrCreate([]);

        $services = array_values(array_filter(
            (array) ($brief['services'] ?? []),
            fn ($key) => is_string($key) && config("agency_costs.services.{$key}") !== null,
        ));

        $inclusive = match ($brief['budget_includes_agency_fee'] ?? null) {
            'yes' => true,
            'no' => false,
            default => null,
        };

        $primaryGoal = $brief['primary_goal'] ?? null;

        unset($brief['services'], $brief['primary_goal']);

        $profile->forceFill([
            'agency_services' => $services,
            'budget_includes_agency_fee' => $inclusive,
            'primary_goal' => filled($primaryGoal) ? $primaryGoal : $profile->primary_goal,
            'brief' => array_filter(
                $brief,
                fn ($value) => $value !== null && $value !== '' && $value !== [],
            ),
        ])->save();

        $project->setRelation('profile', $profile->fresh());
    }

    /**
     * نسبة اكتمال موجز التكليف، مع تسمية الحرِج الناقص صراحة.
     *
     * @return array<string, mixed>
     */
    public function briefCompleteness(Project $project): array
    {
        $profile = $project->profile()->first();
        $brief = $profile?->brief ?? [];
        $fields = BriefQuestions::fields();

        $answered = collect($fields)->filter(function (array $field) use ($brief, $profile) {
            return match ($field['key']) {
                'services' => ! empty($profile?->agency_services),
                'primary_goal' => filled($profile?->primary_goal),
                'budget_includes_agency_fee' => $profile?->budget_includes_agency_fee !== null,
                default => filled($brief[$field['key']] ?? null),
            };
        });

        $requirements = collect([
            [
                'key' => 'success_metric',
                'label' => 'تعريف النجاح برقم ومدة واضحين',
                'complete' => filled($brief['success_metric'] ?? null),
            ],
            [
                'key' => 'primary_goal',
                'label' => 'هدف واحد يقود العمل',
                'complete' => filled($profile?->primary_goal),
            ],
            [
                'key' => 'budget_terms',
                'label' => 'عملة الميزانية وهل تشمل أتعاب الوكالة',
                'complete' => filled($brief['budget_currency'] ?? null)
                    && $profile?->budget_includes_agency_fee !== null,
            ],
            [
                'key' => 'services',
                'label' => 'الخدمات المطلوبة وحدود النطاق',
                'complete' => ! empty($profile?->agency_services),
            ],
            [
                'key' => 'account_ownership',
                'label' => 'ملكية الحسابات والصلاحيات',
                'complete' => filled($brief['account_ownership'] ?? null),
            ],
            [
                'key' => 'proposal_deadline',
                'label' => 'آخر موعد لاستقبال العروض',
                'complete' => filled($brief['proposal_deadline'] ?? null),
            ],
        ])->values();

        $missingCritical = $requirements
            ->where('complete', false)
            ->pluck('label')
            ->values()
            ->all();
        $missingCount = count($missingCritical);

        return [
            'answered' => $answered->count(),
            'total' => count($fields),
            'percent' => count($fields) > 0 ? (int) round($answered->count() * 100 / count($fields)) : 0,
            'requirements' => $requirements->all(),
            'missing_count' => $missingCount,
            'missing_critical' => $missingCritical,
            'is_ready' => $missingCount === 0,
            'is_quotable' => $missingCritical === [],
            'message' => $missingCount === 0
                ? 'موجز التكليف مكتمل ويمكن للوكالة تسعيره على أساس واضح.'
                : "موجزك ناقص {$missingCount} ".($missingCount === 1 ? 'بند' : 'بنود').' — الوكالة لا تستطيع التسعير بدونها.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function readiness(Project $project): array
    {
        $reports = $this->latestReports($project);
        $completed = $reports->map(fn (Report $report) => [
            'key' => $report->toolRun->toolVersion->tool->key,
            'title' => $report->toolRun->toolVersion->tool->title,
            'score' => $report->score,
            'scored' => $report->score !== null,
            'report_id' => $report->id,
        ])->values();
        $keys = $completed->pluck('key')->all();

        $missing = collect(self::CORE_TOOLS)
            ->reject(fn (string $title, string $key) => in_array($key, $keys, true))
            ->map(fn (string $title, string $key) => ['key' => $key, 'title' => $title])
            ->values()
            ->all();

        return [
            'can_generate' => $missing === [],
            'required_count' => count(self::CORE_TOOLS),
            'completed_count' => count(array_intersect(array_keys(self::CORE_TOOLS), $keys)),
            'included_count' => $completed->count(),
            'missing_core' => $missing,
            'included_tools' => $completed->all(),
            'latest' => $project->agencyReports()->latest('version')->first()?->only([
                'uuid', 'version', 'title', 'generated_at',
            ]),
        ];
    }

    /**
     * @param  array<string, string>  $visibility
     */
    public function generate(Project $project, User $user, array $visibility = [], ?ConsultationSession $consultation = null): AgencyReport
    {
        $readiness = $this->readiness($project);

        if (! $readiness['can_generate']) {
            throw ValidationException::withMessages([
                'tools' => 'أكمل أولًا: '.collect($readiness['missing_core'])->pluck('title')->implode('، '),
            ]);
        }

        $visibility = $this->visibility($visibility);
        $reports = $this->latestReports($project);
        $consultation ??= $project->consultationSessions()
            ->where('status', ConsultationSession::STATUS_COMPLETED)
            ->latest('completed_at')->first();
        $snapshot = $this->snapshot($project, $reports, $visibility, $consultation);
        $this->reportGate->validate($snapshot);

        return DB::transaction(function () use ($project, $user, $reports, $visibility, $snapshot, $consultation): AgencyReport {
            $version = ((int) $project->agencyReports()->lockForUpdate()->max('version')) + 1;

            return AgencyReport::create([
                'project_id' => $project->id,
                'consultation_session_id' => $consultation?->id,
                'created_by' => $user->id,
                'version' => $version,
                'title' => "أين يقف مشروعك — {$project->name} — الإصدار {$version}",
                'status' => 'published',
                'source_report_ids' => $reports->pluck('id')->values()->all(),
                'visibility' => $visibility,
                'snapshot' => $snapshot,
                'generated_at' => now(),
            ]);
        });
    }

    /**
     * @return Collection<int, Report>
     */
    private function latestReports(Project $project): Collection
    {
        /*
         * لا نشترط وجود درجة رقمية: أداة تصف الحالة دون أن تُقيّمها تظل
         * معرفة تحتاجها الوكالة. الإقصاء الصامت كان يحذف قسمًا كاملًا من
         * المستند دون أن يعلم صاحبه، والصواب أن يُضمّن ويُوسم بأنه وصفي.
         */
        return $project->reports()
            ->where('status', 'published')
            ->with([
                'toolRun.toolVersion.tool',
                'sections',
                'findings.recommendations',
            ])
            ->latest('created_at')
            ->latest('id')
            ->get()
            ->filter(fn (Report $report) => $report->toolRun?->toolVersion?->tool !== null)
            ->unique(fn (Report $report) => $report->toolRun->toolVersion->tool->key)
            ->values();
    }

    /**
     * @param  array<string, string>  $visibility
     * @return array<string, string>
     */
    private function visibility(array $visibility): array
    {
        return collect(['budget', 'competitors', 'evidence'])
            ->mapWithKeys(function (string $key) use ($visibility) {
                $value = $visibility[$key] ?? 'full';

                return [$key => in_array($value, self::VISIBILITY_LEVELS, true) ? $value : 'full'];
            })
            ->all();
    }

    /**
     * @param  Collection<int, Report>  $reports
     * @param  array<string, string>  $visibility
     * @return array<string, mixed>
     */
    private function snapshot(Project $project, Collection $reports, array $visibility, ?ConsultationSession $consultation): array
    {
        // التقرير لقطة للحالة الحالية؛ لا نعتمد علاقة قديمة محمّلة قبل آخر تعديل.
        $project->load(['profile', 'competitors', 'kpis.entries', 'audiences']);
        $marketing = $reports->first(
            fn (Report $report) => $report->toolRun->toolVersion->tool->key === 'marketing-score'
        );
        // تقرير المالك خاص به، لذلك يحتفظ دائمًا بكل معرفته. الخصوصية
        // تُطبَّق بقائمة سماح عند بناء موجز الوكالة، لا بحذف معرفة المالك.
        $ownerVisibility = ['budget' => 'full', 'competitors' => 'full', 'evidence' => 'full'];
        $priorities = $this->priorities($reports, $ownerVisibility['evidence']);
        $budget = $project->profile?->monthly_budget;
        $ledger = $this->ledger->build($project);
        $dataGaps = $this->dataGaps($project);
        $executive = $this->executive($project, $reports, $marketing, $priorities, $ledger);
        $mandate = $this->mandate($project);
        $commercials = $this->commercials($project, $ownerVisibility['budget']);
        $numbers = $this->operational->numbers($project);
        $assets = $this->operational->assets($project);
        $behaviour = $this->operational->behaviour($project, $reports);
        $plan = $this->plan($priorities, $dataGaps, $project);
        $ownerGuide = $this->ownerGuide($project);
        $briefReadiness = $this->briefCompleteness($project);
        $consultationSnapshot = $this->consultationSnapshot($consultation, $ownerVisibility['evidence']);
        $crossToolSnapshot = $this->crossTool->build($reports, $ownerVisibility['evidence']);
        $methodology = $this->methodology($reports, $ledger, $ownerVisibility);
        $audiences = $project->audiences->map(fn ($audience) => [
            'name' => $audience->name,
            'pains' => $audience->pains,
            'gains' => $audience->gains,
            'behaviors' => $audience->behaviors,
        ])->values()->all();
        $tools = $reports->map(fn (Report $report) => $this->toolSnapshot(
            $report,
            $ownerVisibility['evidence'],
            $ownerVisibility['competitors'],
        ))->all();
        $competitors = $this->competitors($project, $ownerVisibility['competitors']);
        $evidence = $this->evidence($reports, $ownerVisibility['evidence']);
        $assumptions = $reports->flatMap(fn (Report $report) => $report->assumptions ?? [])
            ->filter()->unique()->values()->all();
        $kpis = $project->kpis->map(fn ($kpi) => [
            'name' => $kpi->name,
            'unit' => $kpi->unit,
            'baseline' => $kpi->baseline,
            'target' => $kpi->target,
            'latest' => $kpi->latestValue(),
        ])->values()->all();
        $appendix = $this->operational->appendix($project, $ownerVisibility['evidence']);
        $agencyBrief = $this->agencyBrief(
            $project,
            $mandate,
            $commercials,
            $numbers,
            $assets,
            $briefReadiness,
            $ledger,
            $competitors,
            $kpis,
        );
        $ownerReport = $this->ownerReport(
            $project,
            $executive,
            $numbers,
            $priorities,
            $dataGaps,
            $consultationSnapshot,
            $crossToolSnapshot,
            $ownerGuide,
            $briefReadiness,
            $behaviour,
            $plan,
            $methodology,
            $assets,
            $audiences,
            $tools,
            $competitors,
            $evidence,
            $kpis,
            $consultationSnapshot,
            $assumptions,
            $appendix,
        );

        return [
            'meta' => [
                'snapshot_at' => now()->toIso8601String(),
                'source_report_ids' => $reports->pluck('id')->values()->all(),
                'visibility' => $visibility,
                'method' => 'أحدث تقرير منشور وصالح لكل أداة مكتملة؛ لا تُجمع درجات الأدوات في متوسط واحد.',
            ],
            'project' => [
                'name' => $project->name,
                'industry' => $project->industry,
                'stage' => $project->stage,
                'stage_label' => $this->stageLabel($project->stage),
                'description' => $project->profile?->description,
                'geography' => $project->profile?->geography,
                'website' => $project->profile?->website,
                'primary_goal' => $project->profile?->primary_goal,
                // القيمة الخام تبقى للمنطق، والتسمية العربية للعرض.
                'primary_goal_label' => $this->ledger->optionLabel('primary_goal', $project->profile?->primary_goal),
                'business_model_label' => $this->ledger->optionLabel('business_model', $project->profile?->business_model),
                'value_proposition' => $project->profile?->value_proposition,
                'monthly_budget' => $budget,
                'budget_summary' => null,
            ],
            'readiness' => [
                'score' => $marketing?->score,
                'band' => $marketing?->score_band,
                'summary' => $marketing?->summary,
            ],
            /*
             * بطاقة القرار: الصفحة الأولى التي يقرأها من يقرر قبول العميل
             * من عدمه. لا معلومة جديدة فيها — اشتقاق مما يليها كي لا تتناقض
             * صفحة الملخص مع تفاصيل المستند أبدًا.
             */
            'decision_card' => $this->decisionCard(
                $project, $executive, $commercials, $mandate, $numbers, $assets, $behaviour, $dataGaps, $ledger,
            ),
            'executive' => $executive,
            // التكليف والمال: ما يحوّل المستند من وصف حالة إلى شيء تُسعّره وكالة.
            'mandate' => $mandate,
            'commercials' => $commercials,
            /*
             * الأقسام التشغيلية: أرقام بمستوى ثقة، وجرد أصول ووصول، وسجل
             * تنفيذ، وملحقا الأدلة والأصول الجاهزة. بدونها يبقى المستند
             * مفهومًا لكنه غير قابل للتسعير ولا للبدء في اليوم الأول.
             */
            'numbers' => $numbers,
            'assets' => $assets,
            'behaviour' => $behaviour,
            'appendix' => $appendix,
            'consultation' => $consultationSnapshot,
            'cross_tool_synthesis' => $crossToolSnapshot,
            'ledger' => $ledger,
            'audiences' => $audiences,
            'tools' => $tools,
            'competitors' => $competitors,
            'evidence' => $evidence,
            'assumptions' => $assumptions,
            'priorities' => $priorities,
            'plan' => $plan,
            'kpis' => $kpis,
            'scope' => [
                'in_scope' => $reports->map(
                    fn (Report $report) => $report->toolRun->toolVersion->tool->title
                )->values()->all(),
                'out_of_scope' => [
                    'أي تنفيذ أو إنفاق أو شراء وسائط لم يُذكر صراحة في عرض الوكالة.',
                    'نقل ملكية الحسابات أو الأصول الرقمية إلى الوكالة.',
                    'ضمان نتائج رقمية لم تُربط بخط أساس ومدة وميزانية.',
                ],
                'account_ownership' => 'تبقى الحسابات الإعلانية والتحليلية والنطاقات والبيانات باسم المشروع، وتُمنح الوكالة صلاحيات عمل قابلة للإلغاء.',
                'review_cadence' => 'مراجعة تشغيلية أسبوعية، وتقرير أداء شهري يربط الإنفاق بالنتائج وخطة الشهر التالي.',
            ],
            /*
             * موجَّه إلى الوكالة: ما يجب أن يتضمنه عرضها. صياغته كمتطلبات
             * لا كأسئلة يطرحها المالك، لأن هذا المستند يُسلَّم للوكالة.
             */
            'proposal_requirements' => [
                'النتائج المحددة التي تلتزمون بها خلال أول 30 و60 و90 يومًا، وما الذي يُعدّ إخفاقًا.',
                'خط الأساس الذي ستقيسون التحسن مقارنة به، ومن أين تأخذون قراءته الأولى.',
                'فصل مكتوب بين أتعاب الإدارة وميزانية الوسائط والإنتاج والأدوات — أربعة سطور لا رقم واحد.',
                'ما يدخل السعر وما يُحاسب منفصلًا، وحدود المراجعات المشمولة.',
                'من ينفذ يوميًا ومن يراجع الجودة، وهل يُسند أي جزء لطرف ثالث.',
                'طريقة الفصل بين أثر الإعلان وأثر العرض والصفحة والمبيعات.',
                'وتيرة التقارير، والبيانات الخام التي سنصل إليها مباشرة.',
                'ترتيب ملكية الحسابات والبيانات والمواد أثناء التعاقد وبعده.',
                'شروط الإيقاف أو تعديل الخطة إذا لم تتحقق المؤشرات المبكرة.',
            ],
            // خاص بصاحب المشروع: لا يظهر في النسخة المشتركة ولا في PDF الوكالة.
            'owner_guide' => $ownerGuide,
            'owner_report' => $ownerReport,
            'agency_brief' => $agencyBrief,
            'data_gaps' => $dataGaps,
            'methodology' => $methodology,
        ];
    }

    /**
     * تقرير صاحب المشروع: يشرح الحالة قبل أن يطلب منه اتخاذ قرار.
     *
     * @param  array<string, mixed>  $executive
     * @param  array<string, mixed>  $numbers
     * @param  array<int, array<string, mixed>>  $priorities
     * @param  array<int, string>  $dataGaps
     * @param  array<string, mixed>|null  $consultation
     * @param  array<string, mixed>  $crossTool
     * @param  array<string, mixed>  $ownerGuide
     * @param  array<string, mixed>  $readiness
     * @param  array<string, mixed>  $behaviour
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $methodology
     * @param  array<string, mixed>  $assets
     * @param  array<int, array<string, mixed>>  $audiences
     * @param  array<int, array<string, mixed>>  $tools
     * @param  array<string, mixed>  $competitors
     * @param  array<string, mixed>  $evidence
     * @param  array<int, array<string, mixed>>  $kpis
     * @param  array<string, mixed>|null  $consultationDetails
     * @param  array<int, string>  $assumptions
     * @param  array<string, mixed>  $appendix
     * @return array<string, mixed>
     */
    private function ownerReport(
        Project $project,
        array $executive,
        array $numbers,
        array $priorities,
        array $dataGaps,
        ?array $consultation,
        array $crossTool,
        array $ownerGuide,
        array $readiness,
        array $behaviour,
        array $plan,
        array $methodology,
        array $assets,
        array $audiences,
        array $tools,
        array $competitors,
        array $evidence,
        array $kpis,
        ?array $consultationDetails,
        array $assumptions,
        array $appendix,
    ): array {
        $problems = collect($executive['problems'] ?? [])
            ->unique(fn (array $item) => mb_strtolower(trim((string) ($item['title'] ?? ''))))
            ->take(3)
            ->values()
            ->all();

        $conflicts = collect($consultation['conflicts'] ?? [])
            ->map(fn (array $item) => [
                'question' => $item['question'] ?? $item['statement'] ?? $item['message'] ?? $item['explanation'] ?? 'توجد إجابتان تحتاجان إلى حسم.',
                'why' => data_get($item, 'resolution.statement')
                    ?? $item['explanation']
                    ?? $item['reason']
                    ?? 'حسم هذه النقطة يمنع بناء قرارين متعارضين.',
            ])
            ->concat(collect($crossTool['divergences'] ?? [])->map(fn (array $item) => [
                'question' => 'أي قراءة تصف وضعك بصورة أدق في جانب '.($item['category'] ?? 'هذا الموضوع').'؟',
                'why' => 'نتائجك السابقة قرأت هذا الجانب بأكثر من طريقة، وإجابتك تحسم ما نعتمد عليه.',
            ]))
            ->unique('question')
            ->values()
            ->all();

        $unknowns = collect($dataGaps)->take(7)->map(function (string $item): array {
            $resolution = str_contains($item, 'رقم') || str_contains($item, 'قياس') || str_contains($item, 'تتب')
                ? 'يُحسم بقياس بسيط'
                : (str_contains($item, 'مدة') || str_contains($item, 'وقت')
                    ? 'يُحسم بعد فترة متابعة'
                    : 'يُحسم بإجابة واضحة');

            return ['text' => $item, 'resolution' => $resolution];
        })->values()->all();

        $thisWeek = collect($priorities)->take(5)->map(function (array $item): array {
            $item['estimated_time'] = match ($item['effort'] ?? null) {
                'low' => 'من 30 إلى 60 دقيقة',
                'medium' => 'من ساعتين إلى أربع ساعات',
                'high' => 'يوم عمل أو أكثر',
                default => 'حدّد له موعدًا قصيرًا هذا الأسبوع',
            };

            return $item;
        })->values()->all();
        $thisWeekTitles = collect($thisWeek)->pluck('title')->filter();
        $laterPlan = collect($plan)->map(
            fn (array $items) => collect($items)
                ->reject(fn (array $item) => $thisWeekTitles->contains($item['title'] ?? null))
                ->values()
                ->all()
        )->all();

        return [
            'overview' => [
                'title' => 'أين يقف مشروعك الآن؟',
                'description' => $executive['position'] ?? $project->profile?->description ?? 'هذه صورة مشروعك كما تظهر من إجاباتك ونتائج تشخيصاتك.',
                'main_issue' => $problems[0]['title'] ?? 'لا توجد مشكلة واحدة مؤكدة تتقدم على بقية النقاط بعد.',
            ],
            'numbers' => $numbers,
            'journey' => [
                'title' => 'أين يتوقف الناس؟',
                'description' => 'نتتبع الطريق من أول اهتمام إلى النتيجة التي تريدها، ونوضح أين نملك رقمًا وأين نحتاج القياس أولًا.',
                'stages' => $numbers['rows'] ?? [],
            ],
            'problems' => $problems,
            'conflicts' => $conflicts,
            'unknowns' => $unknowns,
            'this_week' => $thisWeek,
            'before_agency' => $ownerGuide,
            'readiness' => $readiness,
            'private_details' => [
                'project' => [
                    'name' => $project->name,
                    'description' => $project->profile?->description,
                    'industry' => $project->industry,
                    'stage' => $this->stageLabel($project->stage),
                    'geography' => $project->profile?->geography,
                    'website' => $project->profile?->website,
                    'business_model' => $this->ledger->optionLabel('business_model', $project->profile?->business_model),
                    'primary_goal' => $this->ledger->optionLabel('primary_goal', $project->profile?->primary_goal),
                    'value_proposition' => $project->profile?->value_proposition,
                ],
                'audiences' => $audiences,
                'assets' => $assets,
                'tools' => $tools,
                'competitors' => $competitors,
                'evidence' => $evidence,
                'kpis' => $kpis,
                'consultation' => $consultationDetails,
                'assumptions' => $assumptions,
                'appendix' => $appendix,
                'behaviour' => $behaviour,
                'plan' => $laterPlan,
                'methodology' => $methodology,
                'different_readings' => $crossTool['divergences'] ?? [],
            ],
        ];
    }

    /**
     * موجز الوكالة: حقائق وقرارات محسومة فقط، بلا حيرة المالك الداخلية.
     *
     * @param  array<string, mixed>  $mandate
     * @param  array<string, mixed>  $commercials
     * @param  array<string, mixed>  $numbers
     * @param  array<string, mixed>  $assets
     * @param  array<string, mixed>  $readiness
     * @param  array<string, mixed>  $ledger
     * @param  array<string, mixed>  $competitors
     * @param  array<int, array<string, mixed>>  $kpis
     * @return array<string, mixed>
     */
    private function agencyBrief(
        Project $project,
        array $mandate,
        array $commercials,
        array $numbers,
        array $assets,
        array $readiness,
        array $ledger,
        array $competitors,
        array $kpis,
    ): array {
        $answers = collect($mandate['answered'] ?? [])->keyBy('key');
        $answer = fn (string $key): ?string => $answers->get($key)['value'] ?? null;

        $baseline = collect($numbers['rows'] ?? [])->map(fn (array $row) => [
            'label' => $row['label'] ?? '',
            'value' => $row['value'] === null
                ? 'غير معروف حتى الآن'
                : trim($row['value'].' '.($row['unit'] ?? '')),
            'known' => ($row['confidence'] ?? 'unknown') !== 'unknown',
        ])->values()->all();

        return [
            'project' => [
                'name' => $project->name,
                'description' => $project->profile?->description,
                'industry' => $project->industry,
                'business_model' => $this->ledger->optionLabel('business_model', $project->profile?->business_model),
                'geography' => $project->profile?->geography,
                'stage' => $this->stageLabel($project->stage),
                'value_proposition' => $project->profile?->value_proposition,
                'website' => $project->profile?->website,
                'audiences' => $project->audiences->pluck('name')->filter()->values()->all(),
                'audience_details' => $project->audiences->map(fn ($audience) => [
                    'name' => $audience->name,
                    'needs' => $audience->pains,
                    'desired_result' => $audience->gains,
                    'behaviour' => $audience->behaviors,
                ])->values()->all(),
                'competitors' => $competitors['items'] ?? [],
                'known_context' => collect($ledger['themes'] ?? [])
                    ->flatMap(fn (array $theme) => $theme['answered'] ?? [])
                    ->map(fn (array $item) => [
                        'label' => $item['label'] ?? '',
                        'value' => $item['value'] ?? '',
                    ])
                    ->filter(fn (array $item) => $item['label'] !== '' && $item['value'] !== '')
                    ->unique(fn (array $item) => $item['label'].'|'.$item['value'])
                    ->values()
                    ->all(),
            ],
            'baseline' => [
                'rows' => $baseline,
                'tracking' => $numbers['tracking_label'] ?? 'حالة القياس غير معروفة حتى الآن',
                'previous_attempts' => $answer('previous_attempts'),
                'previous_provider' => $answer('previous_agency'),
                'current_customer_source' => $answer('what_works_now'),
                'kpis' => $kpis,
            ],
            'goal' => [
                'primary' => $this->ledger->optionLabel('primary_goal', $project->profile?->primary_goal),
                'success_metric' => $mandate['success_metric'] ?? null,
                'period' => $answer('ninety_day_outcome'),
            ],
            'scope' => [
                'services' => $mandate['services'] ?? [],
                'start_window' => $answer('start_window'),
                'constraints' => $answer('constraints'),
                'out_of_scope' => [
                    'أي خدمة أو إنتاج أو شراء لم يرد صراحة في العرض المقبول.',
                    'نقل ملكية الحسابات أو البيانات إلى الوكالة.',
                    'ضمان رقم لا يرتبط بخط أساس ومدة وميزانية مكتوبة.',
                ],
            ],
            'assets' => $assets,
            'workflow' => [
                'decision_maker' => $answer('decision_maker'),
                'approval_time' => $answer('approval_time'),
                'lead_response_owner' => $answer('lead_response_owner'),
                'internal_capacity' => $answer('internal_capacity'),
                'payment_constraints' => $answer('payment_constraints'),
                'review_cadence' => 'مراجعة تشغيلية أسبوعية، وملخص شهري يربط الإنفاق بالنتيجة والخطوة التالية.',
            ],
            'terms' => [
                'account_ownership' => 'الحسابات والبيانات والملفات المصدرية باسم المشروع، وتُمنح الوكالة صلاحيات قابلة للإلغاء.',
                'declared_ownership' => $answer('account_ownership'),
                'engagement_model' => $answer('engagement_model'),
                'contract_duration' => $answer('contract_duration'),
                'budget_flexibility' => $answer('budget_flexibility'),
                'exit_condition' => 'عند انتهاء التعاقد تُسلّم الملفات والصلاحيات والبيانات الحديثة دون تعطيل الوصول.',
            ],
            'proposal' => [
                'requirements' => [
                    'النتائج التي ستُسلَّم خلال أول 30 و60 و90 يومًا، وكيف سنعرف أن الاتجاه صحيح.',
                    'خط الأساس الذي سيبدأ منه القياس ومصدر كل رقم.',
                    'ما يدخل السعر وما يُحاسب منفصلًا، وحدود المراجعات.',
                    'من يعمل يوميًا، ومن يراجع الجودة، وأي عمل يُسند لطرف آخر.',
                    'طريقة فصل أثر الإعلان عن أثر العرض والصفحة وسرعة الرد على العملاء.',
                    'وتيرة التقارير وطريقة وصول المشروع إلى بياناته الخام.',
                    'شروط الإيقاف أو تعديل الخطة إذا لم تظهر المؤشرات المبكرة.',
                ],
                'pricing_rows' => [
                    ['key' => 'management', 'label' => 'أتعاب الإدارة الشهرية'],
                    ['key' => 'media', 'label' => 'ميزانية الإعلان المدفوعة للمنصات'],
                    ['key' => 'production', 'label' => 'الإنتاج والتصوير والتصميم'],
                    ['key' => 'tools', 'label' => 'الأدوات والاشتراكات والرسوم الأخرى'],
                ],
                'budget' => $commercials,
                'evaluation_criteria' => $answer('evaluation_criteria'),
            ],
            'submission' => [
                'deadline' => $answer('proposal_deadline'),
                'method' => $answer('proposal_submission'),
            ],
            'readiness' => $readiness,
        ];
    }

    /** @return array<string,mixed>|null */
    private function consultationSnapshot(?ConsultationSession $consultation, string $visibility): ?array
    {
        if ($consultation === null) {
            return null;
        }

        $context = $this->consultations->build($consultation);
        if ($visibility === 'private') {
            $context['evidence'] = [
                'hidden' => true,
                'count' => count($context['evidence']),
                'message' => 'الأدلة داخلية ولم تُدرج في النسخة المشتركة.',
            ];
        } elseif ($visibility === 'summary') {
            $context['evidence'] = collect($context['evidence'])->map(function (array $item): array {
                unset($item['text']);

                return $item;
            })->values()->all();
        }

        return $context;
    }

    /**
     * دليل صاحب المشروع — لا يُسلَّم للوكالة.
     *
     * سبب الفصل: خلط «ما تحتاجه الوكالة لتبني» بـ«كيف تساوم الوكالة» يُنتج
     * مستندًا لا يصلح لأحد: الوكالة تقرأ نصائح موجّهة ضدها، والمالك لا يجد
     * طلب عمل يسلّمه. لكل قارئ مستنده.
     *
     * @return array<string, mixed>
     */
    private function ownerGuide(Project $project): array
    {
        $plan = $this->budget->planForProject($project);

        return [
            'budget' => $plan,
            'comparison_questions' => [
                'اطلب من كل وكالة تسعيرًا بنفس النطاق المكتوب هنا، وإلا فأنت تقارن أشياء مختلفة.',
                'كم أتعاب الإدارة الشهرية منفصلة عن ميزانية الإعلان؟ ارفض الرقم المدمج.',
                'هل تُصرف ميزانية الإعلان من حسابي أنا أم من حساب الوكالة؟',
                'ما النتيجة التي تلتزمون بها في أول 60 يومًا، وما تعريفها الرقمي؟',
                'من ينفّذ فعليًا: فريقكم أم مستقلون؟ ومن أراجع معه أسبوعيًا؟',
                'ماذا يبقى لي إن انتهى التعاقد بعد ثلاثة أشهر: الحسابات، المواد، البيانات؟',
                'ما الذي يُحاسب خارج الأتعاب: إنتاج، تصوير، أدوات، رسوم منصات؟',
                'ما شرط الخروج ومدة الإشعار؟',
            ],
            'red_flags' => [
                'ضمان رقمي بلا خط أساس ولا شرط: «نضاعف مبيعاتك» بلا مقياس وعد بيعي لا خطة.',
                'رفض فصل الأتعاب عن ميزانية الوسائط في العرض المكتوب.',
                'إنشاء الحسابات الإعلانية باسم الوكالة بدل اسمك.',
                'عرض أرخص كثيرًا من البقية بنفس النطاق: غالبًا نطاق مختلف لم يُكتب، أو تنفيذ مُعاد إسناده.',
                'تقارير بلا وصول للبيانات الخام.',
                'التزام طويل بلا شرط خروج مبكر عند عدم تحقق المؤشرات.',
            ],
            'non_negotiables' => [
                'الحسابات الإعلانية والتحليلية باسمك، والوكالة تُمنح صلاحية قابلة للإلغاء.',
                'ميزانية الوسائط تُصرف من وسيلة دفعك مباشرة كلما أمكن.',
                'ملفات التصميم والمحتوى المصدرية تُسلَّم لك دوريًا لا عند الخلاف.',
                'تقرير شهري يربط الإنفاق بالنتيجة، لا لقطات شاشة من المنصات.',
            ],
        ];
    }

    /**
     * التكليف: ما المطلوب من الوكالة، بلغة يمكن التسعير عليها.
     *
     * الأدوات التشخيصية تصف الحالة ولا تقول ما المطلوب. بدون هذا القسم يبقى
     * المستند تقريرًا عن المشروع لا طلب عمل — تقرأه الوكالة ثم تسأل من الصفر.
     *
     * @return array<string, mixed>
     */
    private function mandate(Project $project): array
    {
        $profile = $project->profile;
        $brief = $profile?->brief ?? [];
        $services = $profile?->agency_services ?? [];

        $answers = collect(BriefQuestions::fields())
            ->reject(fn (array $field) => in_array($field['key'], ['services', 'budget_includes_agency_fee'], true))
            ->map(function (array $field) use ($brief) {
                $value = $brief[$field['key']] ?? null;

                return [
                    'key' => $field['key'],
                    'label' => $field['label'],
                    'value' => $this->readableAnswer($field, $value),
                    'answered' => filled($value),
                ];
            })
            ->values();

        return [
            'services' => collect($services)
                ->map(fn (string $key) => config("agency_costs.services.{$key}.label"))
                ->filter()->values()->all(),
            'scope_declared' => $services !== [],
            'success_metric' => $brief['success_metric'] ?? null,
            'answered' => $answers->where('answered', true)->values()->all(),
            'unanswered' => $answers->where('answered', false)->pluck('label')->values()->all(),
            'completeness' => $this->briefCompleteness($project),
        ];
    }

    /**
     * البند التجاري: ما يصل إلى الإعلان فعلًا بعد الأتعاب.
     *
     * يُحجب مع الميزانية إن اختار صاحب المشروع ذلك، لكن حتى في وضع الحجب
     * يبقى إعلان «هل الرقم يشمل الأتعاب» ظاهرًا، لأن الوكالة تحتاج معرفة
     * ما إذا كان عرضها يُقارن بأتعاب داخل المبلغ أم فوقه.
     *
     * @return array<string, mixed>
     */
    private function commercials(Project $project, string $budgetVisibility): array
    {
        $plan = $this->budget->planForProject($project);

        if ($budgetVisibility !== 'full') {
            return [
                'mode' => $budgetVisibility,
                'includes_agency_fee' => $plan['includes_agency_fee'],
                'tier' => $plan['tier'],
                'market' => $plan['market'],
                'reference' => $plan['reference'],
                'verdict' => null,
                'breakdown' => null,
                'stated_budget' => null,
                'effective_media' => null,
                'disclaimer' => $plan['disclaimer'],
            ];
        }

        return ['mode' => 'full'] + $plan;
    }

    /**
     * صياغة إجابة مقروءة: القوائم تُوصل، والخيارات تُترجم إلى نصها المعروض.
     */
    private function readableAnswer(array $field, mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item) => $field['options'][$item] ?? $item)
                ->implode('، ');
        }

        return $field['options'][$value] ?? (string) $value;
    }

    /**
     * الملخص التنفيذي: ما تحتاج الوكالة قراءته في دقيقة قبل فتح بقية المستند.
     *
     * يُبنى حتميًا من نتائج الأدوات نفسها — لا نص إنشائي جديد — كي يبقى
     * مطابقًا لما تثبته الأقسام التالية ولا يضيف ادعاءً بلا مصدر.
     *
     * @param  Collection<int, Report>  $reports
     * @param  array<int, array<string, mixed>>  $priorities
     * @param  array<string, mixed>  $ledger
     * @return array<string, mixed>
     */
    private function executive(
        Project $project,
        Collection $reports,
        ?Report $marketing,
        array $priorities,
        array $ledger,
    ): array {
        $rank = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];

        $problems = $reports
            ->flatMap(fn (Report $report) => $report->findings->map(fn ($finding) => [
                'title' => $finding->title,
                'description' => $finding->description,
                'severity' => $finding->severity,
                'severity_label' => $finding->severityLabel(),
                'source_tool' => $report->toolRun->toolVersion->tool->title,
                'basis' => $finding->is_assumption ? 'افتراض يحتاج تحققًا' : 'مدعوم بإجابة موثقة',
            ]))
            ->sortByDesc(fn (array $item) => [
                $rank[$item['severity']] ?? 0,
                $item['basis'] === 'افتراض يحتاج تحققًا' ? 0 : 1,
            ])
            ->take(3)
            ->values()
            ->all();

        // الفرص = مكاسب سريعة: أثر مرتفع بجهد منخفض. تُميّز عن قائمة
        // الأولويات الكاملة لأن الوكالة تحتاج نقطة بدء لا قائمة كاملة.
        $opportunities = collect($priorities)
            ->sortByDesc(fn (array $item) => [
                $item['effort'] === 'low' ? 2 : ($item['effort'] === 'medium' ? 1 : 0),
                $item['impact'] === 'high' ? 2 : ($item['impact'] === 'medium' ? 1 : 0),
                (int) $item['priority'],
            ])
            ->take(3)
            ->map(fn (array $item) => [
                'title' => $item['title'],
                'description' => $item['description'],
                'impact' => $item['impact'],
                'impact_label' => $item['impact_label'],
                'effort' => $item['effort'],
                'effort_label' => $item['effort_label'],
                'source_tool' => $item['source_tool'],
            ])
            ->values()
            ->all();

        return [
            'position' => $marketing?->score !== null
                ? "درجة الجاهزية التسويقية {$marketing->score}/100 ({$marketing->score_band})، مقاسة على {$reports->count()} من أدوات التشخيص."
                : "حالة موصوفة عبر {$reports->count()} من أدوات التشخيص دون درجة جاهزية رقمية بعد.",
            'context' => trim(implode(' · ', array_filter([
                $project->industry,
                $project->profile?->geography,
                $this->stageLabel($project->stage),
            ]))),
            'knowledge_coverage' => $ledger['coverage'],
            'problems' => $problems,
            'opportunities' => $opportunities,
            'reading_note' => 'هذا المستند يصف الحالة القائمة كما وثّقها صاحب المشروع بنفسه. كل بند منسوب إلى أداته وتاريخه، وما لم يُجب عنه مُعلن صراحة في قسم حدود المعرفة.',
        ];
    }

    /**
     * خطة الأفق الثلاثي. التوزيع نسبي على ما هو موجود فعلًا، ولا يُترك أفق
     * فارغًا: عناصر إغلاق الفجوات وتثبيت خط الأساس والمراجعة بنود حقيقية
     * قابلة للتنفيذ، لا حشو يملأ الجدول.
     *
     * @param  array<int, array<string, mixed>>  $priorities
     * @param  array<int, string>  $dataGaps
     * @return array<string, array<int, array<string, string>>>
     */
    private function plan(array $priorities, array $dataGaps, Project $project): array
    {
        $items = array_map(fn (array $priority) => [
            'title' => $priority['title'],
            'source' => $priority['source_tool'],
            'kind' => 'priority',
        ], $priorities);

        foreach ($dataGaps as $gap) {
            $items[] = [
                'title' => "استكمال «{$gap}» وتثبيته في ملف المشروع قبل بناء أي خطة عليه",
                'source' => 'فجوة بيانات',
                'kind' => 'gap',
            ];
        }

        $items[] = $project->kpis->isEmpty()
            ? [
                'title' => 'تثبيت خط أساس رقمي لمؤشر واحد على الأقل قبل أي إنفاق إعلاني',
                'source' => 'القياس',
                'kind' => 'baseline',
            ]
            : [
                'title' => 'تحديث قراءات المؤشرات ومقارنتها بخط الأساس المسجّل',
                'source' => 'القياس',
                'kind' => 'baseline',
            ];

        $items[] = [
            'title' => 'مراجعة الأثر مقابل خط الأساس وإعادة ترتيب الأولويات بناءً على النتيجة',
            'source' => 'إيقاع المراجعة',
            'kind' => 'review',
        ];

        $items[] = [
            'title' => 'تحديث هذا المستند بإصدار جديد يوثّق ما نُفِّذ وما تغيّر',
            'source' => 'التوثيق',
            'kind' => 'documentation',
        ];

        $count = count($items);
        $buckets = ['30_days' => [], '60_days' => [], '90_days' => []];
        $labels = array_keys($buckets);

        foreach ($items as $index => $item) {
            $buckets[$labels[intdiv($index * 3, $count)]][] = $item;
        }

        return $buckets;
    }

    /**
     * ملحق المنهجية: كيف بُني المستند ومتى، وما حدود ما يثبته.
     *
     * @param  Collection<int, Report>  $reports
     * @param  array<string, mixed>  $ledger
     * @param  array<string, string>  $visibility
     * @return array<string, mixed>
     */
    private function methodology(Collection $reports, array $ledger, array $visibility): array
    {
        return [
            'sources' => $reports->map(fn (Report $report) => [
                'tool' => $report->toolRun->toolVersion->tool->title,
                'report_id' => $report->id,
                'produced_at' => $report->created_at?->toDateString(),
                'scored' => $report->score !== null,
                'review' => $report->review_mode === 'manual' ? 'مراجعة بشرية' : 'تحليل آلي',
                'reviewed_at' => $report->reviewed_at?->toDateString(),
            ])->values()->all(),
            'knowledge_coverage' => $ledger['coverage'],
            'visibility' => [
                'budget' => $this->visibilityLabel($visibility['budget']),
                'competitors' => $this->visibilityLabel($visibility['competitors']),
                'evidence' => $this->visibilityLabel($visibility['evidence']),
            ],
            'limits' => [
                'الإجابات مصدرها صاحب المشروع، ولم تُدقَّق مقابل حسابات إعلانية أو تحليلات خارجية.',
                'الدرجات تُقاس داخل كل أداة على أسئلتها المنطبقة، ولا تُجمع في متوسط واحد بين الأدوات.',
                'ما وُسم افتراضًا لم يُسند إلى دليل، ويحتاج تحققًا قبل البناء عليه.',
                'اللقطة ثابتة بتاريخها؛ أي تغيير لاحق في المشروع يستدعي إصدارًا جديدًا.',
            ],
        ];
    }

    /**
     * بطاقة القرار — تُقرأ في تسعين ثانية وتحسم: نقبل هذا العميل أم لا.
     *
     * ثلاث إشارات فقط: أعلى فرصة، أكبر خطر، وأكبر مجهول. المجهول إشارة
     * بحد ذاته لأن الوكالة تسعّر المخاطرة لا العمل وحده.
     *
     * @param  array<string, mixed>  $executive
     * @param  array<string, mixed>  $commercials
     * @param  array<string, mixed>  $mandate
     * @param  array<string, mixed>  $numbers
     * @param  array<string, mixed>  $assets
     * @param  array<string, mixed>  $behaviour
     * @param  array<int, string>  $dataGaps
     * @param  array<string, mixed>  $ledger
     * @return array<string, mixed>
     */
    private function decisionCard(
        Project $project,
        array $executive,
        array $commercials,
        array $mandate,
        array $numbers,
        array $assets,
        array $behaviour,
        array $dataGaps,
        array $ledger,
    ): array {
        $opportunity = $executive['opportunities'][0] ?? null;
        $risk = $executive['problems'][0] ?? null;

        // أكبر مجهول: أضعف محور معرفة، وإلا أول فجوة بيانات، وإلا أصل غير مصرّح.
        $weakest = collect($ledger['themes'] ?? [])
            ->filter(fn (array $theme) => $theme['unanswered'] !== [])
            ->sortBy('coverage_percent')
            ->first();

        $unknown = match (true) {
            $weakest !== null => "{$weakest['title']} — تغطية {$weakest['coverage_percent']}٪",
            $dataGaps !== [] => $dataGaps[0],
            $assets['unknown'] !== [] => 'وصول غير محسوم: '.$assets['unknown'][0],
            default => null,
        };

        return [
            'identity' => [
                'project' => $project->name,
                'industry' => $project->industry,
                'geography' => $project->profile?->geography,
                'business_model' => $this->ledger->optionLabel('business_model', $project->profile?->business_model),
                'stage' => $this->stageLabel($project->stage),
            ],
            'readiness' => [
                'score' => $executive['position'],
                'trend' => $behaviour['trend'],
            ],
            'money' => [
                'mode' => $commercials['mode'] ?? 'private',
                'effective_media' => $commercials['effective_media'] ?? null,
                'verdict' => $commercials['verdict'] ?? null,
            ],
            'success_metric' => $mandate['success_metric'] ?? null,
            'scope_declared' => $mandate['scope_declared'] ?? false,
            'coverage' => [
                'knowledge_percent' => $ledger['coverage']['percent'] ?? 0,
                'numbers_known' => $numbers['summary']['known'],
                'numbers_total' => $numbers['summary']['total'],
                'assets_percent' => $assets['readiness_percent'],
            ],
            'signals' => [
                'opportunity' => $opportunity === null ? null : $opportunity['title'],
                'risk' => $risk === null ? null : $risk['title'],
                'unknown' => $unknown,
            ],
        ];
    }

    /**
     * مرحلة المشروع سمة على الكيان لا حقل أداة، فتسميتها هنا لا في الكتالوج.
     */
    private function stageLabel(?string $stage): ?string
    {
        return match ($stage) {
            'idea' => 'مجرد فكرة',
            'launch' => 'بدأت للتو',
            'growth' => 'شغّال وأبيع',
            'scale' => 'أبيع وأريد التوسع',
            default => null,
        };
    }

    private function visibilityLabel(string $level): string
    {
        return match ($level) {
            'private' => 'محجوب بطلب صاحب المشروع',
            'summary' => 'ملخص دون تفاصيل',
            default => 'كامل',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function toolSnapshot(
        Report $report,
        string $evidenceVisibility,
        string $competitorVisibility,
    ): array {
        $tool = $report->toolRun->toolVersion->tool;

        return [
            'report_id' => $report->id,
            'key' => $tool->key,
            'title' => $tool->title,
            'score' => $report->score,
            'score_band' => $report->score_band,
            // أداة بلا درجة تُعرض موصوفة لا محذوفة، ويُعلن أنها كذلك.
            'scored' => $report->score !== null,
            'score_note' => $report->score === null ? 'أداة وصفية لا تُنتج درجة رقمية' : null,
            'review' => $report->review_mode === 'manual' ? 'مراجعة بشرية' : 'تحليل آلي',
            'produced_at' => $report->created_at?->toDateString(),
            'summary' => $report->summary,
            'assumptions' => $report->assumptions ?? [],
            'sections' => $report->sections
                ->reject(fn ($section) => $section->key === 'competitors' && $competitorVisibility === 'private')
                ->map(fn ($section) => [
                    'key' => $section->key,
                    'title' => $section->title,
                    'content' => $section->key === 'competitors' && $competitorVisibility === 'summary'
                        ? ['summary' => 'تفاصيل المنافسين متاحة كملخص عددي فقط في هذه النسخة.']
                        : $this->sanitizeEvidence($section->content_json, $evidenceVisibility),
                ])->values()->all(),
            'findings' => $report->findings->map(fn ($finding) => [
                'title' => $finding->title,
                'description' => $finding->description,
                'category' => $finding->category,
                'severity' => $finding->severity,
                'severity_label' => $finding->severityLabel(),
                'is_assumption' => $finding->is_assumption,
                'evidence' => $this->visibleEvidence($finding->evidence, $evidenceVisibility),
            ])->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, Report>  $reports
     * @return array<int, array<string, mixed>>
     */
    private function priorities(Collection $reports, string $evidenceVisibility): array
    {
        return $reports
            ->flatMap(function (Report $report) use ($evidenceVisibility) {
                $tool = $report->toolRun->toolVersion->tool;

                return $report->findings->flatMap(function ($finding) use ($report, $tool, $evidenceVisibility) {
                    return $finding->recommendations->map(fn ($recommendation) => [
                        'title' => $recommendation->title,
                        'description' => $recommendation->description,
                        'root_cause' => $recommendation->root_cause ?: $finding->description,
                        'commercial_impact' => $recommendation->commercial_impact ?: 'يؤثر في كفاءة النمو أو تكلفة الوصول إلى النتيجة المستهدفة.',
                        'action_steps' => $recommendation->action_steps ?: [$recommendation->description],
                        'owner_role' => $recommendation->owner_role ?: 'مسؤول التسويق بالتنسيق مع صاحب القرار',
                        'resources' => $recommendation->resources ?: ['وقت الفريق', 'بيانات القياس المتاحة'],
                        'timeframe' => $recommendation->timeframe ?: 'خلال 30 يومًا',
                        'dependencies' => $recommendation->dependencies ?? [],
                        'impact' => $recommendation->impact,
                        'impact_label' => $recommendation->impactLabel(),
                        'effort' => $recommendation->effort,
                        'effort_label' => $recommendation->effortLabel(),
                        'priority' => $recommendation->priority,
                        'kpi' => $recommendation->kpi_hint,
                        'kpi_definition' => $recommendation->kpi_definition ?: ($recommendation->kpi_hint ?: 'مؤشر النتيجة المرتبط بالتوصية'),
                        'kpi_source' => $recommendation->kpi_source ?: 'لوحة القياس المعتمدة للمشروع',
                        'baseline' => $recommendation->baseline,
                        'target' => $recommendation->target,
                        'missing_baseline_reason' => $recommendation->baseline ? null : ($recommendation->missing_baseline_reason ?: 'لم يُثبّت خط الأساس بعد؛ يُقاس قبل بدء التنفيذ.'),
                        'success_condition' => $recommendation->success_condition ?: 'تحسن المؤشر المتفق عليه مقارنة بخط الأساس خلال المدة المحددة.',
                        'stop_condition' => $recommendation->stop_condition ?: 'توقف المبادرة وتُراجع إذا لم تتحسن الإشارة المبكرة بعد دورة قياس كاملة.',
                        'risks' => $recommendation->risks ?: ['نقص البيانات أو تأخر التنفيذ قد يخفض الثقة في النتيجة.'],
                        'confidence' => $recommendation->confidence ?? $finding->confidence,
                        'evidence' => $this->visibleEvidence($finding->evidence, $evidenceVisibility),
                        'source_tool' => $tool->title,
                        'source_report_id' => $report->id,
                    ]);
                });
            })
            ->sortByDesc(fn (array $item) => [
                (int) $item['priority'],
                $item['impact'] === 'high' ? 2 : ($item['impact'] === 'medium' ? 1 : 0),
                $item['effort'] === 'low' ? 2 : ($item['effort'] === 'medium' ? 1 : 0),
            ])
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Report>  $reports
     * @return array<string, mixed>
     */
    private function evidence(Collection $reports, string $visibility): array
    {
        $items = $reports->flatMap(fn (Report $report) => $report->findings)
            ->pluck('evidence')
            ->filter()
            ->unique()
            ->values();

        return match ($visibility) {
            'private' => ['mode' => 'private', 'count' => $items->count(), 'items' => []],
            'summary' => ['mode' => 'summary', 'count' => $items->count(), 'items' => []],
            default => ['mode' => 'full', 'count' => $items->count(), 'items' => $items->all()],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function competitors(Project $project, string $visibility): array
    {
        $competitors = $project->competitors->where('status', 'confirmed')->values();

        return match ($visibility) {
            'private' => ['mode' => 'private', 'count' => $competitors->count(), 'items' => []],
            'summary' => ['mode' => 'summary', 'count' => $competitors->count(), 'items' => []],
            default => [
                'mode' => 'full',
                'count' => $competitors->count(),
                'items' => $competitors->map(fn ($competitor) => [
                    'name' => $competitor->name,
                    'url' => $competitor->url,
                    'tier' => $competitor->tier,
                    'tier_label' => match ($competitor->tier) {
                        'local' => 'محلي',
                        'regional' => 'إقليمي',
                        default => 'عالمي',
                    },
                ])->all(),
            ],
        };
    }

    private function visibleEvidence(?string $evidence, string $visibility): ?string
    {
        return match ($visibility) {
            'private' => null,
            'summary' => $evidence ? 'مدعوم ببيانات داخلية متاحة عند الطلب' : null,
            default => $evidence,
        };
    }

    private function sanitizeEvidence(mixed $value, string $visibility, ?string $key = null): mixed
    {
        if ($key === 'evidence') {
            return $this->visibleEvidence(is_scalar($value) ? (string) $value : null, $visibility);
        }

        if (! is_array($value)) {
            return $value;
        }

        $sanitized = [];

        foreach ($value as $childKey => $childValue) {
            $sanitized[$childKey] = $this->sanitizeEvidence(
                $childValue,
                $visibility,
                is_string($childKey) ? $childKey : null,
            );
        }

        return $sanitized;
    }

    /**
     * @return array<int, string>
     */
    private function dataGaps(Project $project): array
    {
        $profile = $project->profile;
        $fields = [
            'description' => 'وصف المشروع',
            'geography' => 'النطاق الجغرافي',
            'primary_goal' => 'الهدف الأساسي',
            'value_proposition' => 'عرض القيمة',
            'monthly_budget' => 'الميزانية',
        ];

        return collect($fields)
            ->filter(fn (string $label, string $key) => blank($profile?->{$key}))
            ->values()
            ->all();
    }
}
