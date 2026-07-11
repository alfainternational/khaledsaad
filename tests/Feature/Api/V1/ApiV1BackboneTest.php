<?php

namespace Tests\Feature\Api\V1;

use App\Application\Execution\BuildExecutionPackageAction;
use App\Domain\Account\Models\Account;
use App\Domain\Approval\Models\Approval;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Execution\Models\Recommendation;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use App\Support\Workspaces\OnboardingState;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * اختبارات العمود الفقري (B1): التسجيل، الخروج، ودورة المشروع الكاملة CRUD عبر الـ API.
 */
class ApiV1BackboneTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function register_creates_user_and_returns_bearer_token(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $response = $this->postJson('/api/v1/register', [
            'name' => 'مستخدم جديد',
            'email' => 'newbie@example.com',
            'password' => 'Password!2345',
            'password_confirmation' => 'Password!2345',
            'device_name' => 'PHPUnit',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'newbie@example.com');

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNotEmpty($response->json('data.default_workspace_public_id'));
        $this->assertDatabaseHas('users', ['email' => 'newbie@example.com']);
    }

    #[Test]
    public function register_rejects_duplicate_email_with_unified_error(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/register', [
            'name' => 'x',
            'email' => 'taken@example.com',
            'password' => 'Password!2345',
            'password_confirmation' => 'Password!2345',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function logout_revokes_current_token(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/logout')
            ->assertNoContent();

        // التوكن أُبطل فعلاً في قاعدة البيانات.
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function project_full_crud_cycle_over_api(): void
    {
        [$owner, $workspace] = $this->makeWorkspace();
        $token = $owner->createToken('test')->plainTextToken;
        $base = '/api/v1/workspaces/'.$workspace->public_id;

        // إنشاء بكل الحقول
        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson($base.'/projects', [
                'name' => 'مشروع الاختبار',
                'stage' => 2,
                'status' => 'active',
                'sector' => 'ecommerce',
                'market_country' => 'SA',
                'monitoring_enabled' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'مشروع الاختبار')
            ->assertJsonPath('data.sector', 'ecommerce');

        $publicId = $create->json('data.public_id');
        $this->assertNotEmpty($publicId);

        $project = Project::query()->where('public_id', $publicId)->firstOrFail();
        $recommendation = Recommendation::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'area' => 'conversion',
            'title' => 'أضف إثبات ثقة قبل نموذج التواصل',
            'priority' => 10,
            'severity' => 'high',
            'evidence' => 'الدليل ناقص.',
            'rationale' => 'أضف شهادة عميل واضحة.',
            'estimated_impact' => 'high',
            'confidence' => 0.9,
            'status' => 'proposed',
            'created_by' => $owner->id,
        ]);
        $package = app(BuildExecutionPackageAction::class)->handle($recommendation, $owner);
        $package->update(['status' => 'measuring']);
        $package->tasks()->take(2)->get()->each->update(['status' => 'done']);
        $package->reports()->create([
            'phase' => 'validation',
            'progress' => 70,
            'metrics_json' => [[
                'name' => 'العملاء المحتملون',
                'value' => '34 خلال أسبوع',
            ]],
        ]);

        // عرض تفصيلي
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson($base.'/projects/'.$publicId)
            ->assertOk()
            ->assertJsonPath('data.public_id', $publicId)
            ->assertJsonPath('data.market_country', 'SA')
            ->assertJsonPath('data.execution_summary.packages_count', 1)
            ->assertJsonPath('data.execution_summary.active_packages_count', 1)
            ->assertJsonPath('data.execution_summary.total_tasks', 4)
            ->assertJsonPath('data.execution_summary.done_tasks', 2)
            ->assertJsonPath('data.execution_summary.task_progress_percent', 50)
            ->assertJsonPath('data.execution_summary.latest_measurement.phase', 'validation')
            ->assertJsonPath('data.execution_summary.latest_measurement.phase_label', 'تحقق')
            ->assertJsonPath('data.execution_summary.latest_measurement.progress', 70)
            ->assertJsonPath('data.execution_summary.latest_measurement.metric.name', 'العملاء المحتملون')
            ->assertJsonPath('data.recent_execution_packages.0.public_id', $package->public_id)
            ->assertJsonPath('data.recent_execution_packages.0.status_label', 'تحت القياس')
            ->assertJsonPath('data.recent_execution_packages.0.owner.public_id', $owner->public_id);

        // تعديل
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson($base.'/projects/'.$publicId, [
                'name' => 'اسم معدّل',
                'stage' => 3,
                'status' => 'paused',
                'sector' => 'ecommerce',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'اسم معدّل')
            ->assertJsonPath('data.stage', 3);

        // حذف
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson($base.'/projects/'.$publicId)
            ->assertNoContent();

        $this->assertSoftDeleted('projects', ['public_id' => $publicId]);
    }

    #[Test]
    public function tools_index_and_dynamic_form_payload(): void
    {
        [$owner, $workspace] = $this->makeWorkspace();
        $token = $owner->createToken('test')->plainTextToken;
        $base = '/api/v1/workspaces/'.$workspace->public_id;

        \App\Domain\Tool\Models\Tool::query()
            ->where('code', 'ideal-customer')
            ->update(['name' => 'Ideal Customer']);

        // فهرس الأدوات متاح.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson($base.'/tools')
            ->assertOk()
            ->assertJsonStructure(['data' => [['code', 'name', 'stage']]])
            ->assertJsonFragment([
                'code' => 'ideal-customer',
                'name' => 'العميل المثالي',
            ]);

        // مشروع لتحميل أداة عليه.
        $project = \App\Domain\Project\Models\Project::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'مشروع الأداة',
            'stage' => 4,
            'status' => 'active',
            'sector' => 'ecommerce',
        ]);
        ToolRun::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'tool_code' => 'diagnosis',
            'mode' => 'guided',
            'input_json' => [],
            'output_json' => ['headline' => 'تم التشخيص'],
            'summary_json' => ['headline' => 'تم التشخيص'],
            'status' => 'completed',
            'completeness_score' => 80,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson($base.'/tools?project_public_id='.$project->public_id)
            ->assertOk()
            ->assertJsonFragment([
                'code' => 'diagnosis',
                'completed_in_current_project' => true,
                'recommended_now' => false,
            ])
            ->assertJsonFragment([
                'code' => 'marketing-plan',
                'completed_in_current_project' => false,
                'recommended_now' => true,
            ]);

        // تحميل أداة يعيد مخطط النموذج الديناميكي (form.modes) للموبايل.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson($base.'/projects/'.$project->public_id.'/tools/diagnosis')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'form' => [
                    'default_mode',
                    'modes' => [
                        ['key', 'label', 'fields' => [['key', 'label', 'type', 'quality']]],
                    ],
                ],
            ]);
    }

    #[Test]
    public function studio_templates_and_generations_list(): void
    {
        [$owner, $workspace] = $this->makeWorkspace();
        $token = $owner->createToken('test')->plainTextToken;
        $base = '/api/v1/workspaces/'.$workspace->public_id;

        // كتالوج القوالب متاح (قد يكون فارغاً حسب البذور، لكن البنية ثابتة).
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson($base.'/templates')
            ->assertOk()
            ->assertJsonStructure(['data']);

        // قائمة توليدات الاستوديو (فارغة لمساحة جديدة).
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson($base.'/studio/generations')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);

        // توليد غير موجود → خطأ NOT_FOUND الموحّد.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson($base.'/studio/generations/nonexistent-id')
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    #[Test]
    public function project_lifecycle_brief_audit_recommendations_dossier(): void
    {
        [$owner, $workspace] = $this->makeWorkspace();
        $token = $owner->createToken('test')->plainTextToken;
        $base = '/api/v1/workspaces/'.$workspace->public_id;

        $project = \App\Domain\Project\Models\Project::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'مشروع الدورة',
            'stage' => 2,
            'status' => 'active',
            'sector' => 'ecommerce',
        ]);
        $pBase = $base.'/projects/'.$project->public_id;

        // brief: قراءة فارغة ثم كتابة ثم قراءة القيمة نفسها.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson($pBase.'/brief')
            ->assertOk()
            ->assertJsonStructure(['data' => ['brief', 'assessment']]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson($pBase.'/brief', [
                'business' => ['summary' => 'متجر إلكتروني للعطور في السعودية.'],
                'goals' => ['primary_goal' => 'مضاعفة الطلبات خلال ٦ أشهر'],
            ])
            ->assertOk()
            ->assertJsonPath('data.brief.business.summary', 'متجر إلكتروني للعطور في السعودية.');

        // حالة التدقيق (لا تدقيق بعد).
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson($pBase.'/audit/status')
            ->assertOk()
            ->assertJsonPath('data.in_progress', false);

        // التوصيات (فارغة لمشروع جديد).
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson($pBase.'/recommendations')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $recommendation = Recommendation::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'area' => 'conversion',
            'title' => 'أضف إثبات ثقة قبل نموذج التواصل',
            'priority' => 10,
            'severity' => 'high',
            'evidence' => 'الدليل ناقص.',
            'rationale' => 'أضف شهادة عميل واضحة.',
            'estimated_impact' => 'high',
            'confidence' => 0.9,
            'status' => 'proposed',
            'created_by' => $owner->id,
        ]);
        $package = app(BuildExecutionPackageAction::class)->handle($recommendation, $owner);
        $package->tasks()->firstOrFail()->update(['status' => 'done']);
        $package->reports()->create([
            'phase' => 'validation',
            'progress' => 70,
            'metrics_json' => [[
                'name' => 'العملاء المحتملون',
                'value' => '34 خلال أسبوع',
            ]],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson($pBase.'/recommendations')
            ->assertOk()
            ->assertJsonPath('data.0.execution_packages.0.public_id', $package->public_id)
            ->assertJsonPath('data.0.execution_packages.0.progress.total_tasks', 4)
            ->assertJsonPath('data.0.execution_packages.0.progress.done_tasks', 1)
            ->assertJsonPath('data.0.execution_packages.0.owner.public_id', $owner->public_id)
            ->assertJsonPath('data.0.execution_packages.0.measurement_summary.latest_phase', 'validation')
            ->assertJsonPath('data.0.execution_packages.0.measurement_summary.latest_metric.name', 'العملاء المحتملون');

        $newRecommendation = Recommendation::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'area' => 'trust',
            'title' => 'أضف ضماناً واضحاً',
            'priority' => 20,
            'severity' => 'medium',
            'evidence' => 'لا يوجد ضمان ظاهر.',
            'rationale' => 'أضف ضمان استرداد واضح.',
            'estimated_impact' => 'medium',
            'confidence' => 0.8,
            'status' => 'proposed',
            'created_by' => $owner->id,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson($pBase.'/recommendations/'.$newRecommendation->public_id.'/package')
            ->assertCreated()
            ->assertJsonPath('data.owner.public_id', $owner->public_id)
            ->assertJsonPath('data.progress.total_tasks', 4)
            ->assertJsonPath('data.measurement_summary.reports_count', 0)
            ->assertJsonPath('data.reports', []);

        // دليل المشروع JSON.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson($pBase.'/dossier')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    #[Test]
    public function collaboration_account_and_agency_surface(): void
    {
        [$owner, $workspace] = $this->makeWorkspace();
        $token = $owner->createToken('test')->plainTextToken;
        $base = '/api/v1/workspaces/'.$workspace->public_id;
        $auth = fn () => $this->withHeader('Authorization', 'Bearer '.$token);

        // الداشبورد
        $auth()->getJson($base.'/dashboard')
            ->assertOk()
            ->assertJsonStructure(['data' => ['onboarding_completed', 'dashboard']]);

        // onboarding (مكتمل في makeWorkspace)
        $auth()->getJson($base.'/onboarding')
            ->assertOk()
            ->assertJsonPath('data.completed', true)
            ->assertJsonStructure(['data' => ['options' => ['personas', 'goals']]]);

        // الحساب: قراءة
        $auth()->getJson($base.'/account')
            ->assertOk()
            ->assertJsonStructure(['data' => ['user', 'account', 'workspace', 'profile', 'entitlements']]);

        // الفريق: قائمة + دعوة + حذف الدعوة
        $auth()->getJson($base.'/team')
            ->assertOk()
            ->assertJsonStructure(['data' => ['members', 'invitations']]);

        $auth()->postJson($base.'/team/invitations', [
            'email' => 'invitee@example.com',
            'role' => 'editor',
        ])->assertStatus(201);

        $invitationId = $auth()->getJson($base.'/team')
            ->json('data.invitations.0.id');
        $this->assertNotNull($invitationId);

        $auth()->deleteJson($base.'/team/invitations/'.$invitationId)
            ->assertNoContent();

        // الموافقات: قائمة بعدّادات
        $auth()->getJson($base.'/approvals')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['pending_count']]);

        // عملاء الوكالة: دورة CRUD كاملة
        $created = $auth()->postJson($base.'/clients', [
            'name' => 'عميل الوكالة',
            'email' => 'client@example.com',
            'status' => 'active',
        ])->assertStatus(201);

        $clientId = $created->json('data.public_id');

        $auth()->putJson($base.'/clients/'.$clientId, [
            'name' => 'عميل معدّل',
            'status' => 'lead',
        ])->assertOk()->assertJsonPath('data.name', 'عميل معدّل');

        $auth()->getJson($base.'/clients')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'عميل معدّل');

        $auth()->deleteJson($base.'/clients/'.$clientId)->assertNoContent();

        // علامة الوكالة (باقة agency تملك white_label)
        $auth()->getJson($base.'/agency/branding')
            ->assertOk()
            ->assertJsonStructure(['data' => ['branding', 'brand']]);

        $auth()->patchJson($base.'/agency/branding', [
            'enabled' => true,
            'name' => 'وكالتي',
            'color' => '#112233',
        ])->assertOk()->assertJsonPath('data.branding.name', 'وكالتي');
    }

    #[Test]
    public function api_approval_requests_are_idempotent_for_pending_items_and_keep_notes_on_review(): void
    {
        [$owner, $workspace] = $this->makeWorkspace();
        $token = $owner->createToken('test')->plainTextToken;
        $base = '/api/v1/workspaces/'.$workspace->public_id;
        $auth = fn () => $this->withHeader('Authorization', 'Bearer '.$token);

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'مشروع الاعتماد',
            'stage' => 5,
            'status' => 'active',
            'sector' => 'services',
        ]);

        $run = ToolRun::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'tool_code' => 'agency-audit',
            'mode' => 'guided',
            'inputs_json' => ['agency_scope' => 'إعلانات Meta'],
            'summary_json' => ['headline' => 'تقييم الوكالة يحتاج قياساً أوضح'],
            'output_json' => ['meeting_brief' => 'ناقشوا قياس تكلفة العميل.'],
            'created_by' => $owner->id,
        ]);
        $recommendation = Recommendation::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'area' => 'conversion',
            'title' => 'أضف إثبات ثقة قبل نموذج التواصل',
            'priority' => 10,
            'severity' => 'high',
            'evidence' => 'الدليل ناقص.',
            'rationale' => 'أضف شهادة عميل واضحة.',
            'estimated_impact' => 'high',
            'confidence' => 0.9,
            'status' => 'proposed',
            'created_by' => $owner->id,
        ]);
        $package = app(BuildExecutionPackageAction::class)->handle($recommendation, $owner);

        $payload = [
            'item_type' => 'tool_run',
            'item_public_id' => $run->public_id,
            'note' => 'ملاحظة مهمة قبل الاعتماد.',
        ];

        $created = $auth()
            ->postJson($base.'/projects/'.$project->public_id.'/approvals', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.status_label', 'قيد المراجعة')
            ->assertJsonPath('data.available_actions.0', 'approve')
            ->assertJsonPath('data.available_actions.1', 'reject')
            ->assertJsonPath('data.note', 'ملاحظة مهمة قبل الاعتماد.')
            ->assertJsonPath('data.item.kind', 'tool_run')
            ->assertJsonPath('data.item.kind_label', 'تشغيل أداة')
            ->assertJsonPath('data.item.public_id', $run->public_id)
            ->assertJsonPath('data.item.title', 'تقييم الوكالة يحتاج قياساً أوضح')
            ->assertJsonPath('data.item.tool_code', 'agency-audit');

        $packageApproval = $auth()
            ->postJson($base.'/projects/'.$project->public_id.'/approvals', [
                'item_type' => 'execution_package',
                'item_public_id' => $package->public_id,
                'note' => 'اعتماد حزمة التنفيذ قبل التسليم.',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.item.kind', 'execution_package')
            ->assertJsonPath('data.item.kind_label', 'حزمة تنفيذ')
            ->assertJsonPath('data.item.public_id', $package->public_id)
            ->assertJsonPath('data.item.title', $package->title)
            ->assertJsonPath('data.item.status', 'in_review');

        $this->assertSame('in_review', $package->fresh()->status);

        $auth()
            ->patchJson($base.'/approvals/'.$packageApproval->json('data.id'), [
                'status' => 'approved',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.item.kind', 'execution_package')
            ->assertJsonPath('data.item.status', 'approved');

        $this->assertSame('approved', $package->fresh()->status);

        $auth()
            ->postJson($base.'/projects/'.$project->public_id.'/approvals', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.id', $created->json('data.id'));

        $this->assertSame(
            1,
            Approval::query()
                ->where('workspace_id', $workspace->id)
                ->where('project_id', $project->id)
                ->where('item_type', 'tool_run')
                ->where('item_id', $run->id)
                ->where('status', 'pending')
                ->count()
        );

        $approval = Approval::query()
            ->where('item_type', 'tool_run')
            ->where('item_id', $run->id)
            ->firstOrFail();

        $auth()
            ->patchJson($base.'/approvals/'.$approval->id, [
                'status' => 'approved',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.status_label', 'معتمد')
            ->assertJsonPath('data.available_actions', [])
            ->assertJsonPath('data.note', 'ملاحظة مهمة قبل الاعتماد.')
            ->assertJsonPath('data.item.title', 'تقييم الوكالة يحتاج قياساً أوضح');

        $auth()
            ->getJson($base.'/approvals')
            ->assertOk()
            ->assertJsonPath('data.0.item.public_id', $run->public_id)
            ->assertJsonPath('data.0.item.title', 'تقييم الوكالة يحتاج قياساً أوضح')
            ->assertJsonPath('data.0.project.name', 'مشروع الاعتماد')
            ->assertJsonPath('data.0.status_label', 'معتمد')
            ->assertJsonPath('data.0.available_actions', []);

        $this->assertDatabaseHas('approvals', [
            'id' => $approval->id,
            'status' => 'approved',
            'note' => 'ملاحظة مهمة قبل الاعتماد.',
        ]);
    }

    #[Test]
    public function api_execution_package_exposes_progress_labels_and_next_actions(): void
    {
        [$owner, $workspace] = $this->makeWorkspace();
        $token = $owner->createToken('test')->plainTextToken;
        $base = '/api/v1/workspaces/'.$workspace->public_id;
        $auth = fn () => $this->withHeader('Authorization', 'Bearer '.$token);

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'مشروع التنفيذ',
            'stage' => 5,
            'status' => 'active',
            'sector' => 'services',
        ]);

        $recommendation = Recommendation::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'area' => 'conversion',
            'title' => 'أضف إثبات ثقة قبل نموذج التواصل',
            'priority' => 10,
            'severity' => 'high',
            'evidence' => 'الصفحة تطلب التواصل قبل عرض أي دليل ثقة.',
            'rationale' => 'ضع شهادة عميل مختصرة وضماناً واضحاً بجانب نموذج التواصل.',
            'estimated_impact' => 'high',
            'confidence' => 0.9,
            'status' => 'proposed',
            'created_by' => $owner->id,
        ]);

        $package = app(BuildExecutionPackageAction::class)->handle($recommendation, $owner);
        $package->tasks()->take(2)->get()->each->update(['status' => 'done']);
        $firstTask = $package->tasks()->orderBy('order_index')->firstOrFail();
        $dueDate = now()->addDays(7)->toDateString();
        $firstTask->update([
            'assigned_to' => $owner->id,
            'due_date' => $dueDate,
        ]);
        $firstAsset = $package->assets()->firstOrFail();
        $firstAsset->update([
            'meta_json' => ['channel' => 'landing_page'],
        ]);
        $firstReport = $package->reports()->create([
            'phase' => 'validation',
            'progress' => 70,
            'notes_json' => ['summary' => 'تحسن عدد الطلبات بعد تعديل صفحة الثقة.'],
            'metrics_json' => [[
                'name' => 'العملاء المحتملون',
                'value' => '34 خلال أسبوع',
            ]],
        ]);

        $auth()
            ->getJson($base.'/execution-packages/'.$package->public_id)
            ->assertOk()
            ->assertJsonPath('data.status', 'proposed')
            ->assertJsonPath('data.status_label', 'مقترحة')
            ->assertJsonPath('data.owner.public_id', $owner->public_id)
            ->assertJsonPath('data.owner.name', $owner->name)
            ->assertJsonPath('data.available_actions.0', 'request_approval')
            ->assertJsonPath('data.progress.total_tasks', 4)
            ->assertJsonPath('data.progress.done_tasks', 2)
            ->assertJsonPath('data.progress.percent', 50)
            ->assertJsonPath('data.tasks.0.public_id', $firstTask->public_id)
            ->assertJsonPath('data.tasks.0.status', 'done')
            ->assertJsonPath('data.tasks.0.status_label', 'منجزة')
            ->assertJsonPath('data.tasks.0.available_actions', [])
            ->assertJsonPath('data.tasks.0.assigned_to', $owner->id)
            ->assertJsonPath('data.tasks.0.due_date', $dueDate)
            ->assertJsonPath('data.assets.0.public_id', $firstAsset->public_id)
            ->assertJsonPath('data.assets.0.type', 'dev_brief')
            ->assertJsonPath('data.assets.0.type_label', 'موجز تطوير')
            ->assertJsonPath('data.assets.0.meta.channel', 'landing_page')
            ->assertJsonPath('data.reports.0.public_id', $firstReport->public_id)
            ->assertJsonPath('data.reports.0.phase', 'validation')
            ->assertJsonPath('data.reports.0.phase_label', 'تحقق')
            ->assertJsonPath('data.reports.0.progress', 70)
            ->assertJsonPath('data.reports.0.notes.summary', 'تحسن عدد الطلبات بعد تعديل صفحة الثقة.')
            ->assertJsonPath('data.reports.0.metrics.0.name', 'العملاء المحتملون')
            ->assertJsonPath('data.reports.0.metrics.0.value', '34 خلال أسبوع');

        $auth()
            ->patchJson($base.'/execution-tasks/'.$firstTask->public_id.'/status', [
                'status' => 'in_progress',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->assertSame('done', $firstTask->fresh()->status);

        $prepDueDate = now()->addDays(9)->toDateString();

        $auth()
            ->patchJson($base.'/execution-tasks/'.$firstTask->public_id, [
                'assignee_public_id' => $owner->public_id,
                'due_date' => $prepDueDate,
            ])
            ->assertOk()
            ->assertJsonPath('data.tasks.0.assignee.public_id', $owner->public_id)
            ->assertJsonPath('data.tasks.0.due_date', $prepDueDate);

        $auth()
            ->patchJson($base.'/execution-packages/'.$package->public_id.'/status', [
                'status' => 'approved',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->assertSame('proposed', $package->fresh()->status);

        $auth()
            ->postJson($base.'/execution-packages/'.$package->public_id.'/reports', [
                'phase' => 'execution',
                'progress' => 10,
                'note' => 'محاولة تقرير قبل بدء التنفيذ.',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phase']);

        $this->assertSame(1, $package->reports()->count());

        $package->update(['status' => 'approved']);
        $packageDeadline = now()->addDays(12)->toDateString();

        $auth()
            ->patchJson($base.'/execution-packages/'.$package->public_id, [
                'owner_public_id' => $owner->public_id,
                'deadline' => $packageDeadline,
            ])
            ->assertOk()
            ->assertJsonPath('data.owner.public_id', $owner->public_id)
            ->assertJsonPath('data.deadline', $packageDeadline);

        $auth()
            ->getJson($base.'/execution-packages/'.$package->public_id)
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.status_label', 'معتمدة')
            ->assertJsonPath('data.available_actions.0', 'start_execution')
            ->assertJsonPath('data.progress.percent', 50);

        $auth()
            ->patchJson($base.'/execution-packages/'.$package->public_id.'/status', [
                'status' => 'in_progress',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.status_label', 'قيد التنفيذ')
            ->assertJsonPath('data.available_actions', [])
            ->assertJsonPath('data.progress.percent', 50);

        $auth()
            ->patchJson($base.'/execution-packages/'.$package->public_id.'/status', [
                'status' => 'executed',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $auth()
            ->postJson($base.'/execution-packages/'.$package->public_id.'/reports', [
                'phase' => 'execution',
                'progress' => 85,
                'note' => 'تمت مراجعة أثر التنفيذ من التطبيق.',
                'metric_name' => 'الطلبات المؤهلة',
                'metric_value' => '19 طلب',
            ])
            ->assertOk()
            ->assertJsonPath('data.reports.0.phase', 'execution')
            ->assertJsonPath('data.reports.0.phase_label', 'تنفيذ')
            ->assertJsonPath('data.reports.0.progress', 85)
            ->assertJsonPath('data.reports.0.notes.summary', 'تمت مراجعة أثر التنفيذ من التطبيق.')
            ->assertJsonPath('data.reports.0.metrics.0.name', 'الطلبات المؤهلة')
            ->assertJsonPath('data.reports.0.metrics.0.value', '19 طلب')
            ->assertJsonPath('data.measurement_summary.reports_count', 2)
            ->assertJsonPath('data.measurement_summary.latest_phase', 'execution')
            ->assertJsonPath('data.measurement_summary.latest_phase_label', 'تنفيذ')
            ->assertJsonPath('data.measurement_summary.latest_progress', 85)
            ->assertJsonPath('data.measurement_summary.latest_metric.name', 'الطلبات المؤهلة')
            ->assertJsonPath('data.measurement_summary.latest_metric.value', '19 طلب')
            ->assertJsonPath('data.measurement_summary.latest_note', 'تمت مراجعة أثر التنفيذ من التطبيق.');

        $auth()
            ->patchJson($base.'/execution-tasks/'.$firstTask->public_id.'/status', [
                'status' => 'in_progress',
            ])
            ->assertOk()
            ->assertJsonPath('data.tasks.0.public_id', $firstTask->public_id)
            ->assertJsonPath('data.tasks.0.status', 'in_progress')
            ->assertJsonPath('data.tasks.0.status_label', 'قيد التنفيذ')
            ->assertJsonPath('data.tasks.0.available_actions.0', 'complete')
            ->assertJsonPath('data.tasks.0.available_actions.1', 'reopen')
            ->assertJsonPath('data.progress.done_tasks', 1)
            ->assertJsonPath('data.progress.percent', 25);

        $newDueDate = now()->addDays(10)->toDateString();

        $auth()
            ->patchJson($base.'/execution-tasks/'.$firstTask->public_id, [
                'status' => 'done',
                'assignee_public_id' => $owner->public_id,
                'due_date' => $newDueDate,
            ])
            ->assertOk()
            ->assertJsonPath('data.tasks.0.public_id', $firstTask->public_id)
            ->assertJsonPath('data.tasks.0.status', 'done')
            ->assertJsonPath('data.tasks.0.status_label', 'منجزة')
            ->assertJsonPath('data.tasks.0.available_actions.0', 'reopen')
            ->assertJsonPath('data.tasks.0.assigned_to', $owner->id)
            ->assertJsonPath('data.tasks.0.assignee.public_id', $owner->public_id)
            ->assertJsonPath('data.tasks.0.assignee.name', $owner->name)
            ->assertJsonPath('data.tasks.0.assignee.email', $owner->email)
            ->assertJsonPath('data.tasks.0.due_date', $newDueDate)
            ->assertJsonPath('data.progress.done_tasks', 2)
            ->assertJsonPath('data.progress.percent', 50);

        $outsider = User::factory()->create();

        $auth()
            ->patchJson($base.'/execution-tasks/'.$firstTask->public_id, [
                'assignee_public_id' => $outsider->public_id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['assignee_public_id']);

        $auth()
            ->patchJson($base.'/execution-tasks/'.$firstTask->public_id, [
                'assignee_public_id' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.tasks.0.assigned_to', null)
            ->assertJsonPath('data.tasks.0.assignee', null);

        $package->tasks()->update(['status' => 'done']);

        $auth()
            ->patchJson($base.'/execution-packages/'.$package->public_id.'/status', [
                'status' => 'executed',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.status_label', 'منفّذة')
            ->assertJsonPath('data.available_actions.0', 'start_measuring')
            ->assertJsonPath('data.tasks.0.available_actions', [])
            ->assertJsonPath('data.progress.percent', 100);

        $auth()
            ->patchJson($base.'/execution-packages/'.$package->public_id, [
                'owner_public_id' => $owner->public_id,
                'deadline' => now()->addDays(20)->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['package']);

        $auth()
            ->patchJson($base.'/execution-tasks/'.$firstTask->public_id.'/status', [
                'status' => 'pending',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->assertSame('done', $firstTask->fresh()->status);

        $auth()
            ->patchJson($base.'/execution-tasks/'.$firstTask->public_id, [
                'assignee_public_id' => $owner->public_id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['task']);

        $auth()
            ->patchJson($base.'/execution-tasks/'.$firstTask->public_id, [
                'due_date' => now()->addDays(14)->toDateString(),
                'assignee_public_id' => null,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['task']);
    }

    #[Test]
    public function billing_overview_and_device_tokens(): void
    {
        [$owner, $workspace] = $this->makeWorkspace();
        $token = $owner->createToken('test')->plainTextToken;
        $base = '/api/v1/workspaces/'.$workspace->public_id;
        $auth = fn () => $this->withHeader('Authorization', 'Bearer '.$token);

        // نظرة الفوترة
        $auth()->getJson($base.'/billing')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['plans', 'current_plan_code', 'is_owner', 'ai_credits_balance', 'paypal_ready'],
            ])
            ->assertJsonPath('data.is_owner', true)
            ->assertJsonPath('data.current_plan_code', 'agency');

        // إلغاء بدون اشتراك PayPal → NOT_FOUND موحّد
        $auth()->postJson($base.'/billing/cancel')
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');

        // تسجيل جهاز للإشعارات ثم حذفه
        $auth()->postJson('/api/v1/devices', [
            'token' => 'fcm-token-123',
            'platform' => 'android',
            'device_name' => 'PHPUnit',
        ])->assertStatus(201);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $owner->id,
            'token' => 'fcm-token-123',
        ]);

        $auth()->deleteJson('/api/v1/devices', ['token' => 'fcm-token-123'])
            ->assertNoContent();

        $this->assertDatabaseMissing('device_tokens', ['token' => 'fcm-token-123']);
    }

    /**
     * @return array{0: User, 1: Workspace}
     */
    private function makeWorkspace(): array
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $owner = User::factory()->create();
        $plan = Plan::query()->where('code', 'agency')->firstOrFail();

        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Acct',
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
            'name' => 'WS',
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

        return [$owner, $workspace];
    }
}
