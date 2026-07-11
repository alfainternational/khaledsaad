<?php

namespace Tests\Feature\Execution;

use App\Application\Execution\BuildExecutionPackageAction;
use App\Domain\Account\Models\Account;
use App\Domain\AI\Models\AITemplate;
use App\Domain\Approval\Models\Approval;
use App\Domain\Entitlement\Models\Entitlement;
use App\Domain\Execution\Models\ExecutionPackage;
use App\Domain\Execution\Models\ExecutionTask;
use App\Domain\Execution\Models\Recommendation;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExecutionUiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function owner_can_list_recommendations_build_a_package_and_view_it(): void
    {
        [$owner, $workspace, $project, $recommendation] = $this->scenario();

        // 1) Recommendations page lists the recommendation with a convert action.
        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('projects.recommendations.index', $project))
            ->assertOk()
            ->assertSee('الموقع غير آمن (HTTP)')
            ->assertSee('حوّل لحزمة تنفيذ');

        // 2) Convert to an execution package.
        $response = $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('projects.recommendations.package', [$project, $recommendation]));

        $package = ExecutionPackage::query()->where('recommendation_id', $recommendation->id)->firstOrFail();
        $response->assertRedirectToRoute('execution-packages.show', $package);
        $this->assertCount(4, $package->tasks);

        // 3) Package page renders its tasks and measurement plan.
        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('execution-packages.show', $package))
            ->assertOk()
            ->assertSee('صياغة المخرج النهائي')
            ->assertSee('خطة القياس');
    }

    #[Test]
    public function recommendations_page_highlights_only_the_top_three_priorities(): void
    {
        [$owner, $workspace, $project] = $this->scenario();

        foreach ([2, 3, 4] as $index) {
            Recommendation::query()->create([
                'public_id' => (string) Str::ulid(),
                'workspace_id' => $workspace->id,
                'project_id' => $project->id,
                'area' => 'growth',
                'title' => "أولوية إضافية {$index}",
                'priority' => $index * 10,
                'severity' => $index === 4 ? 'low' : 'medium',
                'evidence' => "دليل الأولوية {$index}",
                'rationale' => "نفّذ الإجراء {$index}",
                'estimated_impact' => $index === 4 ? 'low' : 'medium',
                'confidence' => 0.75,
                'status' => 'proposed',
            ]);
        }

        $response = $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('projects.recommendations.index', $project))
            ->assertOk()
            ->assertSee('ابدأ بهذه الأولويات الثلاث', false)
            ->assertSee('المشكلة', false)
            ->assertSee('الإجراء العملي', false)
            ->assertSee('أولوية إضافية 3');

        $this->assertSame(3, substr_count($response->getContent(), 'class="exec-priority-card"'));
        $this->assertStringNotContainsString('data-priority-summary-title="أولوية إضافية 4"', $response->getContent());
    }

    #[Test]
    public function project_page_surfaces_top_execution_priorities(): void
    {
        [$owner, $workspace, $project, $recommendation] = $this->scenario();

        foreach ([2, 3, 4] as $index) {
            Recommendation::query()->create([
                'public_id' => (string) Str::ulid(),
                'workspace_id' => $workspace->id,
                'project_id' => $project->id,
                'area' => 'conversion',
                'title' => "أولوية مشروع {$index}",
                'priority' => $index * 10,
                'severity' => 'medium',
                'evidence' => "دليل مشروع {$index}",
                'rationale' => "إجراء مشروع {$index}",
                'estimated_impact' => 'medium',
                'confidence' => 0.8,
                'status' => 'proposed',
            ]);
        }

        $package = app(BuildExecutionPackageAction::class)->handle($recommendation, $owner);
        $package->update(['status' => 'measuring']);
        $package->update(['deadline' => now()->addDays(10)->toDateString()]);
        $package->tasks()->take(2)->get()->each->update(['status' => 'done']);
        $package->reports()->create([
            'phase' => 'validation',
            'progress' => 70,
            'metrics_json' => [[
                'name' => 'العملاء المحتملون',
                'value' => '34 خلال أسبوع',
            ]],
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('أولويات التنفيذ الآن', false)
            ->assertSee('حالة التنفيذ والقياس', false)
            ->assertSee('الموقع غير آمن (HTTP)')
            ->assertSee('تحت القياس', false)
            ->assertSee('آخر قياس تحقق 70%', false)
            ->assertSee('مالك الحزمة: '.$owner->name, false)
            ->assertSee('الموعد النهائي: '.$package->fresh()->deadline->format('Y-m-d'), false)
            ->assertSee('العملاء المحتملون: 34 خلال أسبوع', false)
            ->assertSee('أولوية مشروع 3')
            ->assertSee('فتح التوصيات والتنفيذ', false);

        $this->assertSame(3, substr_count($response->getContent(), 'class="project-priority-item"'));
        $this->assertStringNotContainsString('data-project-priority-title="أولوية مشروع 4"', $response->getContent());
    }

    #[Test]
    public function owner_can_update_execution_task_status_from_package_page(): void
    {
        [$owner, $workspace, $project, $recommendation] = $this->scenario();
        $package = app(BuildExecutionPackageAction::class)->handle($recommendation, $owner);
        $task = $package->tasks()->orderBy('order_index')->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('execution-packages.show', $package))
            ->assertOk()
            ->assertSee('بانتظار بدء التنفيذ', false)
            ->assertDontSee('تم التنفيذ', false);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->patch(route('execution-packages.tasks.status', [$package, $task]), [
                'status' => 'in_progress',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('pending', $task->fresh()->status);

        $package->update(['status' => 'in_progress']);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('execution-packages.show', $package))
            ->assertOk()
            ->assertSee('بدء', false)
            ->assertSee('تم التنفيذ', false);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->patch(route('execution-packages.tasks.status', [$package, $task]), [
                'status' => 'in_progress',
            ])
            ->assertRedirectToRoute('execution-packages.show', $package);

        $this->assertSame('in_progress', $task->fresh()->status);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->patch(route('execution-packages.tasks.status', [$package, $task]), [
                'status' => 'done',
            ])
            ->assertRedirectToRoute('execution-packages.show', $package);

        $this->assertSame('done', $task->fresh()->status);

        $otherPackage = ExecutionPackage::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'recommendation_id' => $recommendation->id,
            'title' => 'حزمة أخرى',
            'status' => 'proposed',
        ]);
        $otherTask = ExecutionTask::query()->create([
            'execution_package_id' => $otherPackage->id,
            'title' => 'مهمة أخرى',
            'status' => 'pending',
            'order_index' => 1,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->patch(route('execution-packages.tasks.status', [$package, $otherTask]), [
                'status' => 'done',
            ])
            ->assertNotFound();
    }

    #[Test]
    public function package_page_shows_task_assignee_and_due_date(): void
    {
        [$owner, $workspace, , $recommendation] = $this->scenario();
        $package = app(BuildExecutionPackageAction::class)->handle($recommendation, $owner);
        $task = $package->tasks()->orderBy('order_index')->firstOrFail();
        $task->update([
            'assigned_to' => $owner->id,
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('execution-packages.show', $package))
            ->assertOk()
            ->assertSee('المسؤول: '.$owner->name, false)
            ->assertSee('الاستحقاق: '.$task->fresh()->due_date->format('Y-m-d'), false);
    }

    #[Test]
    public function package_page_suggests_studio_templates_with_a_package_brief(): void
    {
        [$owner, $workspace, $project, $recommendation] = $this->scenario();
        $package = app(BuildExecutionPackageAction::class)->handle($recommendation, $owner);
        Entitlement::query()->create([
            'scope_type' => 'workspace',
            'scope_id' => $workspace->id,
            'key' => 'modules.ai_studio',
            'value_type' => 'boolean',
            'value' => ['value' => true],
            'source' => 'manual_override',
        ]);

        $landingTemplate = AITemplate::query()->create([
            'code' => 'landing-headlines',
            'name' => 'عناوين صفحة هبوط',
            'description' => 'نص صفحة هبوط جاهز.',
            'prompt_template' => 'Draft {{project_name}}',
            'model' => 'gpt-5',
            'credit_cost' => 1,
            'status' => 'published',
            'module' => 'modules.ai_studio',
        ]);
        AITemplate::query()->create([
            'code' => 'social-ad',
            'name' => 'إعلان سوشيال',
            'description' => 'إعلان جاهز.',
            'prompt_template' => 'Ad {{project_name}}',
            'model' => 'gpt-5',
            'credit_cost' => 1,
            'status' => 'published',
            'module' => 'modules.ai_studio',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('execution-packages.show', $package))
            ->assertOk()
            ->assertSee('تسليم Studio من هذه الحزمة', false)
            ->assertSee($landingTemplate->name, false)
            ->assertSee('name="template_id" value="'.$landingTemplate->id.'"', false)
            ->assertSee('name="project_id" value="'.$project->id.'"', false)
            ->assertSee('المشكلة: الموقع غير آمن (HTTP)', false)
            ->assertSee('القرار المطلوب: فعّل HTTPS.', false)
            ->assertSee('توليد مخرج Studio', false);
    }

    #[Test]
    public function owner_can_update_execution_package_owner_and_deadline_from_package_page(): void
    {
        [$owner, $workspace, , $recommendation] = $this->scenario();
        $member = User::factory()->create();
        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => 'member',
            'status' => 'active',
            'invited_at' => now(),
        ]);
        $package = app(BuildExecutionPackageAction::class)->handle($recommendation, $owner);
        $package->update(['status' => 'approved']);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('execution-packages.show', $package))
            ->assertOk()
            ->assertSee('مالك الحزمة', false)
            ->assertSee('الموعد النهائي', false)
            ->assertSee($member->name, false);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->patch(route('execution-packages.details', $package), [
                'owner_user_id' => $member->id,
                'deadline' => now()->addDays(10)->toDateString(),
            ])
            ->assertRedirectToRoute('execution-packages.show', $package);

        $package->refresh();
        $this->assertSame($member->id, $package->owner_user_id);
        $this->assertSame(now()->addDays(10)->toDateString(), $package->deadline->format('Y-m-d'));
    }

    #[Test]
    public function owner_cannot_update_execution_package_details_after_package_is_executed(): void
    {
        [$owner, $workspace, , $recommendation] = $this->scenario();
        $package = app(BuildExecutionPackageAction::class)->handle($recommendation, $owner);
        $package->update(['status' => 'executed']);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->patch(route('execution-packages.details', $package), [
                'owner_user_id' => $owner->id,
                'deadline' => now()->addDays(10)->toDateString(),
            ])
            ->assertSessionHasErrors('package');

        $this->assertNull($package->fresh()->deadline);
    }

    #[Test]
    public function owner_can_update_execution_task_assignee_and_due_date_from_package_page(): void
    {
        [$owner, $workspace, , $recommendation] = $this->scenario();
        $member = User::factory()->create();
        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => 'member',
            'status' => 'active',
            'invited_at' => now(),
        ]);
        $package = app(BuildExecutionPackageAction::class)->handle($recommendation, $owner);
        $package->update(['status' => 'in_progress']);
        $task = $package->tasks()->orderBy('order_index')->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('execution-packages.show', $package))
            ->assertOk()
            ->assertSee('تحديث تفاصيل المهمة', false)
            ->assertSee($member->name, false);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->patch(route('execution-packages.tasks.details', [$package, $task]), [
                'assigned_to' => $member->id,
                'due_date' => now()->addDays(7)->toDateString(),
            ])
            ->assertRedirectToRoute('execution-packages.show', $package);

        $task->refresh();
        $this->assertSame($member->id, $task->assigned_to);
        $this->assertSame(now()->addDays(7)->toDateString(), $task->due_date->format('Y-m-d'));
    }

    #[Test]
    public function owner_cannot_update_execution_task_details_after_package_is_executed(): void
    {
        [$owner, $workspace, , $recommendation] = $this->scenario();
        $package = app(BuildExecutionPackageAction::class)->handle($recommendation, $owner);
        $package->update(['status' => 'executed']);
        $task = $package->tasks()->orderBy('order_index')->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->patch(route('execution-packages.tasks.details', [$package, $task]), [
                'assigned_to' => $owner->id,
                'due_date' => now()->addDays(7)->toDateString(),
            ])
            ->assertSessionHasErrors('task');

        $this->assertNull($task->fresh()->assigned_to);
        $this->assertNull($task->fresh()->due_date);
    }

    #[Test]
    public function owner_can_advance_execution_package_status_from_package_page(): void
    {
        [$owner, $workspace, $project, $recommendation] = $this->scenario();
        $package = app(BuildExecutionPackageAction::class)->handle($recommendation, $owner);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('execution-packages.show', $package))
            ->assertOk()
            ->assertSee('طلب اعتماد الحزمة', false)
            ->assertDontSee('value="approved"', false)
            ->assertDontSee('value="in_progress"', false);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->patch(route('execution-packages.status', $package), [
                'status' => 'approved',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('proposed', $package->fresh()->status);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('projects.approvals.store', $project), [
                'item_type' => 'execution_package',
                'item_id' => $package->id,
                'note' => 'مراجعة واعتماد حزمة التنفيذ: '.$package->title,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('approvals', [
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'item_type' => 'execution_package',
            'item_id' => $package->id,
            'status' => 'pending',
        ]);
        $this->assertSame('in_review', $package->fresh()->status);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('approvals.index'))
            ->assertOk()
            ->assertSee('حزمة تنفيذ', false)
            ->assertSee($package->title, false);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('execution-packages.show', $package))
            ->assertOk()
            ->assertDontSee('طلب اعتماد الحزمة', false)
            ->assertDontSee('value="in_progress"', false);

        $approval = Approval::query()
            ->where('item_type', 'execution_package')
            ->where('item_id', $package->id)
            ->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->patch(route('approvals.update', $approval), [
                'status' => 'approved',
            ])
            ->assertRedirect();

        $this->assertSame('approved', $package->fresh()->status);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('execution-packages.show', $package))
            ->assertOk()
            ->assertSee('بدء التنفيذ', false);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->patch(route('execution-packages.status', $package), [
                'status' => 'in_progress',
            ])
            ->assertRedirectToRoute('execution-packages.show', $package);

        $this->assertSame('in_progress', $package->fresh()->status);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->patch(route('execution-packages.status', $package), [
                'status' => 'executed',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('in_progress', $package->fresh()->status);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('execution-packages.show', $package))
            ->assertOk()
            ->assertSee('أكمل كل المهام قبل تأكيد التنفيذ', false)
            ->assertDontSee('value="executed"', false);

        $package->tasks()->update(['status' => 'done']);

        foreach ([
            'executed' => 'بدء القياس',
            'measuring' => null,
        ] as $status => $nextButton) {
            $this->actingAs($owner)
                ->withSession(['current_workspace_id' => $workspace->id])
                ->patch(route('execution-packages.status', $package), [
                    'status' => $status,
                ])
                ->assertRedirectToRoute('execution-packages.show', $package);

            $this->assertSame($status, $package->fresh()->status);

            $response = $this->actingAs($owner)
                ->withSession(['current_workspace_id' => $workspace->id])
                ->get(route('execution-packages.show', $package))
                ->assertOk();

            if ($nextButton) {
                $response->assertSee($nextButton, false);
            } else {
                $response->assertDontSee('بدء التنفيذ', false)
                    ->assertDontSee('value="executed"', false)
                    ->assertDontSee('بدء القياس', false);
            }
        }

        $task = $package->tasks()->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->patch(route('execution-packages.tasks.status', [$package, $task]), [
                'status' => 'pending',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('done', $task->fresh()->status);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('execution-packages.show', $package))
            ->assertOk()
            ->assertSee('مغلقة بعد تأكيد التنفيذ', false)
            ->assertDontSee('إعادة فتح', false);
    }

    #[Test]
    public function owner_can_add_measurement_report_to_execution_package(): void
    {
        [$owner, $workspace, $project, $recommendation] = $this->scenario();
        $package = app(BuildExecutionPackageAction::class)->handle($recommendation, $owner);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('execution-packages.show', $package))
            ->assertOk()
            ->assertSee('تقارير القياس', false)
            ->assertSee('يظهر نموذج القياس بعد بدء التنفيذ.', false)
            ->assertDontSee('حفظ تقرير القياس', false)
            ->assertSee('لا توجد تقارير قياس بعد.', false);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('execution-packages.reports.store', $package), [
                'phase' => 'validation',
                'progress' => 70,
                'metric_name' => 'العملاء المحتملون',
                'metric_value' => '34 خلال أسبوع',
                'note' => 'محاولة مبكرة قبل التنفيذ.',
            ])
            ->assertSessionHasErrors('phase');

        $this->assertSame(0, $package->reports()->count());

        $package->update(['status' => 'measuring']);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('execution-packages.show', $package))
            ->assertOk()
            ->assertSee('حفظ تقرير القياس', false);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('execution-packages.reports.store', $package), [
                'phase' => 'validation',
                'progress' => 70,
                'metric_name' => 'العملاء المحتملون',
                'metric_value' => '34 خلال أسبوع',
                'note' => 'تحسن عدد الطلبات بعد تعديل صفحة الثقة.',
            ])
            ->assertRedirectToRoute('execution-packages.show', $package);

        $this->assertDatabaseHas('execution_reports', [
            'execution_package_id' => $package->id,
            'phase' => 'validation',
            'progress' => 70,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('execution-packages.show', $package))
            ->assertOk()
            ->assertSee('70%', false)
            ->assertSee('تحقق', false)
            ->assertSee('العملاء المحتملون: 34 خلال أسبوع', false)
            ->assertSee('تحسن عدد الطلبات بعد تعديل صفحة الثقة.', false);
    }

    #[Test]
    public function a_member_of_another_workspace_cannot_view_the_package(): void
    {
        [, , , $recommendation] = $this->scenario();
        $package = ExecutionPackage::query()->create([
            'public_id' => (string) Str::ulid(),
            'workspace_id' => $recommendation->workspace_id,
            'project_id' => $recommendation->project_id,
            'recommendation_id' => $recommendation->id,
            'title' => 'حزمة',
            'status' => 'proposed',
        ]);

        $outsider = User::factory()->create();
        $otherAccount = Account::query()->create([
            'owner_user_id' => $outsider->id, 'name' => 'Other', 'billing_email' => $outsider->email, 'status' => 'active',
        ]);
        $otherWorkspace = Workspace::query()->create([
            'account_id' => $otherAccount->id, 'name' => 'Other WS', 'type' => 'personal', 'status' => 'active',
        ]);
        WorkspaceMember::query()->create([
            'workspace_id' => $otherWorkspace->id, 'user_id' => $outsider->id, 'role' => 'owner', 'status' => 'active', 'invited_at' => now(),
        ]);

        $this->actingAs($outsider)
            ->withSession(['current_workspace_id' => $otherWorkspace->id])
            ->get(route('execution-packages.show', $package))
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Workspace, 2: Project, 3: Recommendation}
     */
    private function scenario(): array
    {
        $owner = User::factory()->create();

        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Exec Account',
            'billing_email' => $owner->email,
            'status' => 'active',
        ]);

        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Exec Workspace',
            'type' => 'personal',
            'status' => 'active',
        ]);

        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'invited_at' => now(),
        ]);

        $project = Project::query()->create([
            'public_id' => (string) Str::ulid(),
            'workspace_id' => $workspace->id,
            'name' => 'Exec Project',
            'stage' => 1,
            'status' => 'active',
            'sector' => 'general',
        ]);

        $recommendation = Recommendation::query()->create([
            'public_id' => (string) Str::ulid(),
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'area' => 'website',
            'title' => 'الموقع غير آمن (HTTP)',
            'priority' => 10,
            'severity' => 'high',
            'evidence' => 'الصفحة عبر HTTP.',
            'rationale' => 'فعّل HTTPS.',
            'estimated_impact' => 'high',
            'confidence' => 0.95,
            'status' => 'proposed',
        ]);

        return [$owner, $workspace, $project, $recommendation];
    }
}
