<?php

namespace Tests\Feature\App;

use App\Domain\Account\Models\Account;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Client\Models\Client;
use App\Domain\Intelligence\Models\AuditRun;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Domain\WorkspaceData\Models\WorkspaceData;
use App\Models\User;
use App\Support\Projects\ProjectMarketingBriefStore;
use App\Support\Workspaces\OnboardingState;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToolRunApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function api_tool_run_request_persists_inputs_in_their_matching_fields(): void
    {
        [$owner, $workspace, $project, $tool] = $this->makeWorkspaceToolScenario();

        $response = $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->postJson(route('api.tools.run', $tool), [
                'project_id' => $project->id,
                'mode' => 'guided',
                'brief' => 'هذه ملاحظة إضافية مرتبطة بالاقتراحات.',
                'inputs' => [
                    'offer_name' => 'باقة الانطلاقة',
                    'offer_audience' => 'المطاعم المحلية الصغيرة',
                    'offer_result' => 'طلبات أكثر خلال 30 يوماً',
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.inputs.offer_name', 'باقة الانطلاقة')
            ->assertJsonPath('data.inputs.offer_audience', 'المطاعم المحلية الصغيرة')
            ->assertJsonPath('data.inputs.offer_result', 'طلبات أكثر خلال 30 يوماً')
            ->assertJsonPath('data.inputs.brief', 'هذه ملاحظة إضافية مرتبطة بالاقتراحات.');

        $run = ToolRun::query()->sole();

        $this->assertSame('guided', $run->mode);
        $this->assertSame('باقة الانطلاقة', data_get($run->inputs_json, 'offer_name'));
        $this->assertSame('المطاعم المحلية الصغيرة', data_get($run->inputs_json, 'offer_audience'));
        $this->assertSame('طلبات أكثر خلال 30 يوماً', data_get($run->inputs_json, 'offer_result'));
        $this->assertSame('هذه ملاحظة إضافية مرتبطة بالاقتراحات.', data_get($run->inputs_json, 'brief'));

        $this->assertDatabaseHas('workspace_data', [
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'key' => 'tools.offer-builder',
        ]);

        $this->assertDatabaseHas('workspace_data', [
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'key' => 'tool.summary.offer-builder',
        ]);
    }

    #[Test]
    public function api_tool_load_returns_adaptive_input_experience_for_the_selected_project(): void
    {
        [$owner, $workspace, $project, $tool] = $this->makeWorkspaceToolScenario();

        $response = $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->getJson(route('api.tools.load', $tool).'?project_id='.$project->id);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null)
            ->assertJsonPath('experience.summary.project_label', 'API Project')
            ->assertJsonPath('experience.modes.guided.fields.offer_audience.priority', 'critical')
            ->assertJsonPath('experience.modes.guided.fields.offer_audience.suggested_value', 'أصحاب مشاريع قائمة يحتاجون وضوحاً أعلى')
            ->assertJsonPath('project_brief_assessment.completeness_score', 57)
            ->assertJsonPath('tool_briefing.readiness_score', 100)
            ->assertJsonPath('upstream_context.0.headline', 'تشخيص أولي للمشروع');

        $this->assertSame(
            'API Client',
            $response->json('experience.summary.client_label')
        );
        $this->assertSame('current_tool', $response->json('tool_briefing.next_action.action_type'));
        $this->assertNotEmpty($response->json('experience.summary.bullets'));
    }

    #[Test]
    public function tool_page_renders_dynamic_project_context_targets(): void
    {
        [$owner, $workspace, $project, $tool] = $this->makeWorkspaceToolScenario();

        $response = $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('tools.show', $tool));

        $response
            ->assertOk()
            ->assertSee('لمحة تحليل مشروعك')
            ->assertSee('تحليل مبني على مصادر فعلية')
            ->assertSee('حسّن زر الإجراء الرئيسي قبل توسيع العرض')
            ->assertSee('data-upstream-context-root', false)
            ->assertSee('data-project-brief-root', false)
            ->assertSee('data-tool-briefing-root', false)
            ->assertSee('كيف تستفيد هذه الأداة من ملف المشروع؟');
    }

    #[Test]
    public function agency_audit_returns_a_clear_operational_verdict(): void
    {
        [$owner, $workspace, $project] = $this->makeWorkspaceToolScenario();
        $tool = Tool::query()->where('code', 'agency-audit')->firstOrFail();

        $response = $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->postJson(route('api.tools.run', $tool), [
                'project_id' => $project->id,
                'mode' => 'guided',
                'inputs' => [
                    'agency_scope' => 'إدارة إعلانات ميتا مع محتوى أسبوعي',
                    'agency_promise' => 'زيادة المبيعات خلال شهر',
                    'agency_reported_results' => 'صرفنا 3000 ريال وجبنا 9000 ظهور و120 نقرة',
                    'agency_budget' => '3000 ريال',
                    'agency_tracking' => 'لا يوجد Pixel أو UTM واضح',
                    'agency_concern' => 'يرسلون أرقام تفاعل فقط بدون مبيعات',
                    'agency_questions' => 'ما تكلفة العميل المحتمل المؤهل؟',
                    'agency_decision' => 'لا أريد التجديد قبل وضوح النتائج',
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.headline', 'تقييم الوكالة — API Project')
            ->assertJsonPath('data.summary.agency_verdict.risk_level', 'مرتفع')
            ->assertJsonPath('data.summary.agency_verdict.decision', 'لا توسّع أو تجدّد قبل تصحيح القياس')
            ->assertJsonPath('data.summary.agency_verdict.meeting_brief', "مرحباً، راجعنا أداء الحملات ونحتاج قبل أي توسعة أو تجديد إلى تصحيح القياس وربط النتائج بأهداف العمل.\nقرارنا الحالي: لا توسّع أو تجدّد قبل تصحيح القياس.\nالطلب الأول: تقرير CAC أو تكلفة العميل المحتمل المؤهل.\nالطلب الثاني: توضيح مصدر كل نتيجة عبر Pixel أو UTM أو CRM.\nسؤال الاجتماع: ما تكلفة العميل المحتمل المؤهل؟\nنحتاج فترة قياس قصيرة بمؤشرات مكتوبة قبل رفع الميزانية أو تثبيت الخطة القادمة.")
            ->assertJsonPath('data.output.agency_verdict.score', 38);

        $bullets = $response->json('data.summary.bullets');
        $this->assertContains('الحكم: لا توسّع أو تجدّد قبل تصحيح القياس', $bullets);
        $this->assertContains('اطلب من الوكالة: تقرير CAC أو تكلفة العميل المحتمل المؤهل', $bullets);
        $this->assertContains('سؤال الاجتماع القادم: ما تكلفة العميل المحتمل المؤهل؟', $bullets);

        $nextActions = $response->json('data.next_actions');
        $this->assertContains('لا توسّع أو تجدّد قبل تصحيح القياس', $nextActions);
        $this->assertContains('اطلب من الوكالة: تقرير CAC أو تكلفة العميل المحتمل المؤهل', $nextActions);
        $this->assertContains('اسأل في الاجتماع القادم: ما تكلفة العميل المحتمل المؤهل؟', $nextActions);
    }

    #[Test]
    public function agency_audit_result_panel_renders_the_operational_verdict(): void
    {
        [$owner, $workspace, $project] = $this->makeWorkspaceToolScenario();
        $tool = Tool::query()->where('code', 'agency-audit')->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->postJson(route('api.tools.run', $tool), [
                'project_id' => $project->id,
                'mode' => 'guided',
                'inputs' => [
                    'agency_scope' => 'إدارة حملات ميتا',
                    'agency_promise' => 'زيادة المبيعات خلال شهر',
                    'agency_reported_results' => '120 نقرة وظهور كثير بدون مبيعات',
                    'agency_tracking' => 'لا يوجد UTM واضح',
                    'agency_concern' => 'أرقام تفاعل فقط',
                    'agency_questions' => 'ما تكلفة العميل المحتمل المؤهل؟',
                ],
            ])
            ->assertOk();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('tools.show', $tool))
            ->assertOk()
            ->assertSee('حكم تشغيل الوكالة', false)
            ->assertSee('لا توسّع أو تجدّد قبل تصحيح القياس')
            ->assertSee('مستوى المخاطرة')
            ->assertSee('مطالب من الوكالة')
            ->assertSee('أسئلة الاجتماع القادم')
            ->assertSee('تقرير CAC أو تكلفة العميل المحتمل المؤهل')
            ->assertSee('رسالة الاجتماع مع الوكالة')
            ->assertSee('راجعنا أداء الحملات ونحتاج قبل أي توسعة أو تجديد')
            ->assertSee('خطوات المتابعة')
            ->assertSee('اتفق على فترة قياس قصيرة قبل أي زيادة ميزانية أو تجديد.');
    }

    #[Test]
    public function project_page_shows_the_latest_tool_run_next_action(): void
    {
        [$owner, $workspace, $project] = $this->makeWorkspaceToolScenario();
        $tool = Tool::query()->where('code', 'agency-audit')->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->postJson(route('api.tools.run', $tool), [
                'project_id' => $project->id,
                'mode' => 'guided',
                'inputs' => [
                    'agency_scope' => 'إدارة حملات ميتا',
                    'agency_promise' => 'زيادة المبيعات خلال شهر',
                    'agency_reported_results' => '120 نقرة وظهور كثير بدون مبيعات',
                    'agency_tracking' => 'لا يوجد UTM واضح',
                    'agency_concern' => 'أرقام تفاعل فقط',
                    'agency_questions' => 'ما تكلفة العميل المحتمل المؤهل؟',
                ],
            ])
            ->assertOk();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('آخر تشغيلات الأدوات')
            ->assertSee('خطوة هذا التشغيل')
            ->assertSee('لا توسّع أو تجدّد قبل تصحيح القياس')
            ->assertSee('فتح النتيجة')
            ->assertSee(route('tools.show', $tool), false);
    }

    #[Test]
    public function project_page_surfaces_the_latest_agency_audit_verdict(): void
    {
        [$owner, $workspace, $project] = $this->makeWorkspaceToolScenario();
        $tool = Tool::query()->where('code', 'agency-audit')->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->postJson(route('api.tools.run', $tool), [
                'project_id' => $project->id,
                'mode' => 'guided',
                'inputs' => [
                    'agency_scope' => 'إدارة حملات ميتا',
                    'agency_promise' => 'زيادة المبيعات خلال شهر',
                    'agency_reported_results' => '120 نقرة وظهور كثير بدون مبيعات',
                    'agency_tracking' => 'لا يوجد UTM واضح',
                    'agency_concern' => 'أرقام تفاعل فقط',
                    'agency_questions' => 'ما تكلفة العميل المحتمل المؤهل؟',
                ],
            ])
            ->assertOk();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('حكم الوكالة الحالي')
            ->assertSee('لا توسّع أو تجدّد قبل تصحيح القياس')
            ->assertSee('مرتفع')
            ->assertSee('تقرير CAC أو تكلفة العميل المحتمل المؤهل')
            ->assertSee('رسالة الاجتماع مع الوكالة')
            ->assertSee('نحتاج فترة قياس قصيرة بمؤشرات مكتوبة قبل رفع الميزانية')
            ->assertSee('طلب اعتماد مطالب الوكالة')
            ->assertSee('فتح تقييم الوكالة');
    }

    #[Test]
    public function agency_audit_meeting_brief_can_be_requested_for_approval(): void
    {
        [$owner, $workspace, $project] = $this->makeWorkspaceToolScenario();
        $tool = Tool::query()->where('code', 'agency-audit')->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->postJson(route('api.tools.run', $tool), [
                'project_id' => $project->id,
                'mode' => 'guided',
                'inputs' => [
                    'agency_scope' => 'إدارة حملات ميتا',
                    'agency_promise' => 'زيادة المبيعات خلال شهر',
                    'agency_reported_results' => '120 نقرة وظهور كثير بدون مبيعات',
                    'agency_tracking' => 'لا يوجد UTM واضح',
                    'agency_concern' => 'أرقام تفاعل فقط',
                    'agency_questions' => 'ما تكلفة العميل المحتمل المؤهل؟',
                ],
            ])
            ->assertOk();

        $run = ToolRun::query()->where('tool_code', 'agency-audit')->firstOrFail();
        $meetingBrief = $run->summary_json['agency_verdict']['meeting_brief'];

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('projects.approvals.store', $project), [
                'item_type' => 'tool_run',
                'item_id' => $run->id,
                'note' => $meetingBrief,
            ])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('approvals', [
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'item_type' => 'tool_run',
            'item_id' => $run->id,
            'status' => 'pending',
            'note' => $meetingBrief,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('approvals.index'))
            ->assertOk()
            ->assertSee('تقييم الوكالة — API Project')
            ->assertSee('تشغيل أداة')
            ->assertSee('نحتاج فترة قياس قصيرة بمؤشرات مكتوبة قبل رفع الميزانية');
    }

    /**
     * @return array{0: User, 1: Workspace, 2: Project, 3: Tool}
     */
    private function makeWorkspaceToolScenario(): array
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $owner = User::factory()->create();
        $plan = Plan::query()->where('code', 'agency')->firstOrFail();

        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'API Account',
            'billing_email' => $owner->email,
            'status' => 'active',
        ]);

        Subscription::query()->create([
            'account_id' => $account->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'API Workspace',
            'type' => 'agency',
            'status' => 'active',
        ]);

        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'invited_at' => now(),
        ]);

        app(OnboardingState::class)->markCompleted($workspace);

        $client = Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'API Client',
            'status' => 'active',
        ]);

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'name' => 'API Project',
            'stage' => 4,
            'status' => 'active',
        ]);

        $tool = Tool::query()->updateOrCreate(
            ['code' => 'offer-builder'],
            [
                'name' => 'Offer Builder',
                'description' => 'Builds structured offers.',
                'stage' => 4,
                'sort_order' => 1,
                'status' => 'published',
                'has_guided_mode' => true,
                'has_structured_mode' => true,
                'has_expert_mode' => true,
                'depends_on_json' => ['diagnosis'],
            ],
        );

        WorkspaceData::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'key' => 'tool.summary.diagnosis',
            'value_json' => [
                'headline' => 'تشخيص أولي للمشروع',
                'text' => 'الخلل الحالي أقرب إلى غموض العرض والرسالة.',
                'completeness_score' => 82,
                'stage_label' => 'التشخيص',
            ],
        ]);

        app(ProjectMarketingBriefStore::class)->put($workspace, $project, [
            'business' => [
                'summary' => 'نقدم خدمات تسويق تشغيلي للمشاريع القائمة.',
                'offer' => 'عرض تشخيص وخطة ورسائل',
                'market' => 'السعودية',
            ],
            'audience' => [
                'ideal_customer' => 'أصحاب مشاريع قائمة يحتاجون وضوحاً أعلى',
                'pain_points' => 'تسويق متعب بلا نظام واضح',
            ],
            'goals' => [
                'primary_goal' => 'رفع جودة العملاء المحتملين',
                'success_metric' => 'عدد الاستفسارات المؤهلة',
            ],
            'positioning' => [
                'edge' => 'تنفيذ مرتبط بالقرار والبيع لا بالنشر فقط',
                'promise' => 'وضوح أسرع ومخرجات قابلة للتنفيذ',
            ],
            'execution' => [
                'next_asset' => 'خطة تسويق ثم محتوى منظم',
            ],
        ]);

        AuditRun::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'status' => 'completed',
            'trigger_source' => 'manual',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'summary_json' => [
                'headline' => 'تقرير intelligence موثّق وجاهز',
                'executive_score' => 72,
            ],
            'report_json' => [
                'executive_scores' => [
                    'executive' => 72,
                    'website' => 74,
                    'social' => 61,
                ],
                'analysis_integrity' => [
                    'status' => 'verified',
                    'label' => 'تحليل مبني على مصادر فعلية',
                    'summary' => 'المشروع يملك تغطية فعلية كافية ليستخدمها Action Workspace قبل التوليد.',
                ],
                'honest_diagnosis' => [
                    'الرسالة الحالية تحتاج وضوحاً أكبر في أول شاشة قرار.',
                ],
                'priority_actions' => [
                    'quick_wins_7_days' => [
                        'حسّن زر الإجراء الرئيسي قبل توسيع العرض.',
                    ],
                ],
            ],
            'payload_json' => [],
        ]);

        return [$owner, $workspace, $project, $tool];
    }
}
