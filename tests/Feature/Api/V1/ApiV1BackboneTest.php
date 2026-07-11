<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Account\Models\Account;
use App\Domain\Approval\Models\Approval;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
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

        // عرض تفصيلي
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson($base.'/projects/'.$publicId)
            ->assertOk()
            ->assertJsonPath('data.public_id', $publicId)
            ->assertJsonPath('data.market_country', 'SA');

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

        // فهرس الأدوات متاح.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson($base.'/tools')
            ->assertOk()
            ->assertJsonStructure(['data' => [['code', 'name', 'stage']]]);

        // مشروع لتحميل أداة عليه.
        $project = \App\Domain\Project\Models\Project::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'مشروع الأداة',
            'stage' => 4,
            'status' => 'active',
            'sector' => 'ecommerce',
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
            'output_json' => ['meeting_brief' => 'ناقشوا قياس تكلفة العميل.'],
            'created_by' => $owner->id,
        ]);

        $payload = [
            'item_type' => 'tool_run',
            'item_public_id' => $run->public_id,
            'note' => 'ملاحظة مهمة قبل الاعتماد.',
        ];

        $created = $auth()
            ->postJson($base.'/projects/'.$project->public_id.'/approvals', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.note', 'ملاحظة مهمة قبل الاعتماد.');

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

        $approval = Approval::query()->firstOrFail();

        $auth()
            ->patchJson($base.'/approvals/'.$approval->id, [
                'status' => 'approved',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.note', 'ملاحظة مهمة قبل الاعتماد.');

        $this->assertDatabaseHas('approvals', [
            'id' => $approval->id,
            'status' => 'approved',
            'note' => 'ملاحظة مهمة قبل الاعتماد.',
        ]);
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
