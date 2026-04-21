<?php

namespace Database\Seeders;

use App\Application\AI\GenerateTemplateDraftAction;
use App\Application\Tooling\RunToolAction;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountMember;
use App\Domain\AI\Models\AICreditsLedger;
use App\Domain\AI\Models\AIGeneration;
use App\Domain\AI\Models\AITemplate;
use App\Domain\Approval\Models\Approval;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Client\Models\Client;
use App\Domain\Comment\Models\Comment;
use App\Domain\FeatureFlag\Models\FeatureFlag;
use App\Domain\FeatureFlag\Models\FeatureFlagAudience;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceInvitation;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use App\Support\Workspaces\OnboardingState;
use App\Support\Workspaces\WorkspaceProfileStore;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoPlatformSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'Demo@123456';

    private RunToolAction $runToolAction;

    private GenerateTemplateDraftAction $generateTemplateDraftAction;

    private WorkspaceProfileStore $profileStore;

    private OnboardingState $onboardingState;

    public function run(): void
    {
        $this->runToolAction = app(RunToolAction::class);
        $this->generateTemplateDraftAction = app(GenerateTemplateDraftAction::class);
        $this->profileStore = app(WorkspaceProfileStore::class);
        $this->onboardingState = app(OnboardingState::class);

        $ideaOwner = $this->makeUser('idea.owner@khaledsaad.local', 'سارة الفكرة');
        $freelancerOwner = $this->makeUser('freelancer.owner@khaledsaad.local', 'أحمد الخدمات');
        $businessOwner = $this->makeUser('business.owner@khaledsaad.local', 'ريم المشروع');
        $teamOwner = $this->makeUser('team.owner@khaledsaad.local', 'محمد الفريق');
        $teamAdmin = $this->makeUser('team.admin@khaledsaad.local', 'ليان المشرفة');
        $teamEditor = $this->makeUser('team.editor@khaledsaad.local', 'نادر المحرر');
        $teamContributor = $this->makeUser('team.contributor@khaledsaad.local', 'هالة المساهمة');
        $teamViewer = $this->makeUser('team.viewer@khaledsaad.local', 'عمر المتابع');
        $agencyOwner = $this->makeUser('agency.owner@khaledsaad.local', 'خالد الوكالة');
        $agencyAdmin = $this->makeUser('agency.admin@khaledsaad.local', 'ديما مديرة الحسابات');
        $agencyEditor = $this->makeUser('agency.editor@khaledsaad.local', 'رائد التنفيذي');
        $agencyClient = $this->makeUser('agency.client@khaledsaad.local', 'منى العميل');
        $billingAdmin = $this->makeUser('billing.admin@khaledsaad.local', 'سجى الفوترة');

        $this->seedIdeaWorkspace($ideaOwner);
        $this->seedFreelancerWorkspace($freelancerOwner);
        $this->seedBusinessWorkspace($businessOwner, $billingAdmin);
        $this->seedTeamWorkspace($teamOwner, $teamAdmin, $teamEditor, $teamContributor, $teamViewer);
        $this->seedAgencyWorkspace($agencyOwner, $agencyAdmin, $agencyEditor, $agencyClient);
    }

    private function seedIdeaWorkspace(User $owner): void
    {
        $account = $this->createAccount($owner, 'starter', 'حساب الانطلاقة');
        $workspace = $this->createWorkspace($account, 'مساحة فكرة جديدة', 'personal');
        $this->attachMember($workspace, $owner, 'owner');

        $this->profileStore->put($workspace, [
            'persona' => 'idea',
            'primary_goal' => 'تحويل الفكرة إلى عرض واضح خلال هذا الشهر',
            'audience' => 'أصحاب المشاريع الصغيرة الباحثون عن حلول تسويق عملية',
            'country' => 'السعودية',
            'content_locale' => 'ar_gulf',
            'current_challenge' => 'غياب وضوح الرسالة والقيمة المميزة',
        ]);

        $client = $this->createClient($workspace, 'براند الفكرة', 'active', 'idea@brand.local');

        $projectA = $this->createProject($workspace, $client, 'إطلاق الهوية الأولى', 2, 'active');
        $projectB = $this->createProject($workspace, $client, 'صياغة العرض الأول', 3, 'paused');

        $this->seedToolRuns($workspace, $projectA, $owner, ['diagnosis', 'idea-clarity', 'tagline-builder']);
        $this->seedToolRuns($workspace, $projectB, $owner, ['offer-builder', 'pricing-strategy']);

        $this->seedGenerations($workspace, $projectA, $owner, ['landing-headlines', 'social-ad']);
        $this->seedComment($workspace, $owner, 'project', $projectA->id, 'هذه المساحة جاهزة لتجربة رحلة صاحب الفكرة كاملة.');

        $this->onboardingState->markCompleted($workspace, [
            'client_name' => $client->name,
            'project_name' => $projectA->name,
        ]);

        $this->seedAudit($owner, $workspace, 'demo.workspace_seeded', Workspace::class, $workspace->id, [
            'persona' => 'idea',
        ]);
    }

    private function seedFreelancerWorkspace(User $owner): void
    {
        $account = $this->createAccount($owner, 'pro', 'حساب مقدم الخدمة');
        $workspace = $this->createWorkspace($account, 'مساحة مقدم الخدمة', 'personal');
        $this->attachMember($workspace, $owner, 'owner');

        $this->profileStore->put($workspace, [
            'persona' => 'freelancer',
            'primary_goal' => 'بناء عروض أسرع وتحويل المتابعات إلى صفقات',
            'audience' => 'الخبراء المستقلون ومقدمو الخدمات المتخصصون',
            'country' => 'مصر',
            'content_locale' => 'ar_egypt',
            'current_challenge' => 'تكرار كتابة العروض والرسائل من الصفر',
        ]);

        $clientA = $this->createClient($workspace, 'عيادة العلامة', 'active', 'clinic@demo.local');
        $clientB = $this->createClient($workspace, 'استوديو المحتوى', 'lead', 'studio@demo.local');
        $clientC = $this->createClient($workspace, 'متجر الأداء', 'active', 'store@demo.local');

        $projectA = $this->createProject($workspace, $clientA, 'عرض إعادة التموضع', 3, 'active');
        $projectB = $this->createProject($workspace, $clientB, 'خطة متابعة العملاء', 4, 'active');
        $projectC = $this->createProject($workspace, $clientC, 'قمع الإعلان والتحويل', 4, 'completed');

        $this->seedToolRuns($workspace, $projectA, $owner, ['ideal-customer', 'offer-builder', 'pricing-strategy']);
        $this->seedToolRuns($workspace, $projectB, $owner, ['follow-up-sequence', 'marketing-plan', 'content-plan']);
        $this->seedToolRuns($workspace, $projectC, $owner, ['funnel-builder', 'customer-journey', 'campaign-builder']);

        $this->seedGenerations($workspace, $projectA, $owner, ['sales-script', 'whatsapp-followup']);
        $this->seedGenerations($workspace, $projectB, $owner, ['content-calendar', 'email-sequence']);

        $this->seedAudit($owner, $workspace, 'demo.workspace_seeded', Workspace::class, $workspace->id, [
            'persona' => 'freelancer',
        ]);

        $this->onboardingState->markCompleted($workspace, [
            'client_name' => $clientA->name,
            'project_name' => $projectA->name,
        ]);
    }

    private function seedBusinessWorkspace(User $owner, User $billingAdmin): void
    {
        $account = $this->createAccount($owner, 'pro', 'حساب المشروع القائم');
        $workspace = $this->createWorkspace($account, 'مساحة مشروع قائم', 'team');
        $sandbox = $this->createWorkspace($account, 'مساحة اختبار الإدارة', 'team');

        $this->attachMember($workspace, $owner, 'owner');
        $this->attachMember($sandbox, $owner, 'owner');
        $this->attachAccountMember($account, $billingAdmin, 'billing_admin');

        $this->profileStore->put($workspace, [
            'persona' => 'business',
            'primary_goal' => 'رفع التحويل وتحسين وضوح القمع والعرض',
            'audience' => 'المدراء التنفيذيون وأصحاب الشركات المتوسطة',
            'country' => 'السعودية',
            'content_locale' => 'ar_gulf',
            'current_challenge' => 'وجود حملات متفرقة دون رابط استراتيجي واضح',
        ]);

        $client = $this->createClient($workspace, 'العلامة الرئيسية', 'active', 'brand@business.local');
        $projectA = $this->createProject($workspace, $client, 'إعادة بناء السوق والمنافسة', 2, 'completed');
        $projectB = $this->createProject($workspace, $client, 'قمع النمو الجديد', 4, 'active');
        $projectC = $this->createProject($workspace, $client, 'لوحة المؤشرات التنفيذية', 5, 'active');

        $this->seedToolRuns($workspace, $projectA, $owner, ['market-analysis', 'competitor-analysis', 'positioning']);
        $this->seedToolRuns($workspace, $projectB, $owner, ['funnel-builder', 'marketing-plan', 'campaign-builder']);
        $this->seedToolRuns($workspace, $projectC, $owner, ['kpi-tracker', 'performance-review', 'smart-recommendations']);
        $this->seedGenerations($workspace, $projectB, $owner, ['social-ad', 'landing-headlines']);
        $this->seedCredits($account, 250, 'demo_seed');

        $this->onboardingState->markCompleted($workspace, [
            'client_name' => $client->name,
            'project_name' => $projectB->name,
        ]);
        $this->onboardingState->markCompleted($sandbox, [
            'client_name' => 'Sandbox',
            'project_name' => 'Sandbox Project',
        ]);

        $this->seedAudit($owner, $workspace, 'demo.workspace_seeded', Workspace::class, $workspace->id, [
            'persona' => 'business',
        ]);
    }

    private function seedTeamWorkspace(
        User $owner,
        User $admin,
        User $editor,
        User $contributor,
        User $viewer,
    ): void {
        $account = $this->createAccount($owner, 'team', 'حساب الفريق');
        $workspace = $this->createWorkspace($account, 'مساحة تشغيل الفريق', 'team');
        $this->attachMember($workspace, $owner, 'owner');
        $this->attachMember($workspace, $admin, 'admin');
        $this->attachMember($workspace, $editor, 'editor');
        $this->attachMember($workspace, $contributor, 'contributor');
        $this->attachMember($workspace, $viewer, 'viewer');

        $this->profileStore->put($workspace, [
            'persona' => 'team',
            'primary_goal' => 'تنظيم التشغيل والمخرجات والتقارير للفريق التجاري',
            'audience' => 'فرق التسويق والمبيعات داخل الشركات',
            'country' => 'الإمارات العربية المتحدة',
            'content_locale' => 'ar_gulf',
            'current_challenge' => 'غياب لوحة موحدة تقود الجميع إلى الخطوة التالية',
        ]);

        $client = $this->createClient($workspace, 'ملف الحساب الداخلي', 'active', 'team@account.local');
        $projectA = $this->createProject($workspace, $client, 'خطة الربع القادم', 5, 'active');
        $projectB = $this->createProject($workspace, $client, 'إعادة صياغة الرسائل', 3, 'active');
        $projectC = $this->createProject($workspace, $client, 'حملة اكتساب جديدة', 4, 'paused');

        $this->seedToolRuns($workspace, $projectA, $admin, ['execution-plan', 'kpi-tracker', 'growth-priorities']);
        $this->seedToolRuns($workspace, $projectB, $editor, ['tagline-builder', 'offer-builder', 'promise-builder']);
        $this->seedToolRuns($workspace, $projectC, $contributor, ['content-plan', 'campaign-builder']);
        $this->seedGenerations($workspace, $projectB, $editor, ['email-sequence', 'social-ad']);

        $pendingInvitation = WorkspaceInvitation::query()->create([
            'workspace_id' => $workspace->id,
            'email' => 'new.member@khaledsaad.local',
            'role' => 'editor',
            'token' => Str::uuid()->toString(),
            'status' => 'pending',
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(5),
        ]);

        $this->seedComment($workspace, $admin, 'project', $projectA->id, 'هذه الخطة تحتوي على مخرجات جاهزة لمراجعة المدير.');
        $this->seedAudit($owner, $workspace, 'demo.invitation_created', WorkspaceInvitation::class, $pendingInvitation->id, [
            'email' => $pendingInvitation->email,
        ]);

        $this->onboardingState->markCompleted($workspace, [
            'client_name' => $client->name,
            'project_name' => $projectA->name,
        ]);
    }

    private function seedAgencyWorkspace(
        User $owner,
        User $admin,
        User $editor,
        User $clientUser,
    ): void {
        $account = $this->createAccount($owner, 'agency', 'حساب الوكالة');
        $workspace = $this->createWorkspace($account, 'مساحة الوكالة الرئيسية', 'agency');

        $this->attachMember($workspace, $owner, 'owner');
        $this->attachMember($workspace, $admin, 'admin');
        $this->attachMember($workspace, $editor, 'editor');
        $this->attachMember($workspace, $clientUser, 'client');

        $this->profileStore->put($workspace, [
            'persona' => 'agency',
            'primary_goal' => 'إدارة عدة عملاء ومخرجات واعتمادات من لوحة موحدة',
            'audience' => 'وكالات التسويق والاستشارات التشغيلية',
            'country' => 'السعودية والخليج',
            'content_locale' => 'ar_modern_fusha',
            'current_challenge' => 'الحفاظ على عزل العملاء مع وضوح حمل العمل والموافقات',
        ]);

        $clientA = $this->createClient($workspace, 'عميل النمو', 'active', 'growth@agency-client.local');
        $clientB = $this->createClient($workspace, 'عميل المحتوى', 'active', 'content@agency-client.local');
        $clientC = $this->createClient($workspace, 'عميل الإطلاق', 'lead', 'launch@agency-client.local');

        $projectA = $this->createProject($workspace, $clientA, 'إعادة بناء العرض والقمع', 4, 'active');
        $projectB = $this->createProject($workspace, $clientA, 'لوحة KPIs الشهرية', 5, 'active');
        $projectC = $this->createProject($workspace, $clientB, 'مكتبة المحتوى والرسائل', 4, 'active');
        $projectD = $this->createProject($workspace, $clientC, 'تأسيس الهوية والإطلاق', 2, 'paused');

        $runA = $this->seedToolRuns($workspace, $projectA, $editor, ['offer-builder', 'funnel-builder', 'follow-up-sequence']);
        $runB = $this->seedToolRuns($workspace, $projectB, $admin, ['kpi-tracker', 'performance-review', 'smart-recommendations']);
        $runC = $this->seedToolRuns($workspace, $projectC, $editor, ['content-plan', 'campaign-builder', 'customer-journey']);
        $this->seedToolRuns($workspace, $projectD, $admin, ['diagnosis', 'ideal-customer', 'positioning']);

        $generationsA = $this->seedGenerations($workspace, $projectA, $editor, ['sales-script', 'landing-headlines']);
        $generationsB = $this->seedGenerations($workspace, $projectC, $editor, ['content-calendar', 'whatsapp-followup']);

        $approvalA = $this->createApproval($workspace, $projectA, 'tool_run', $runA[0]->id, 'pending', $owner, 'يرجى مراجعة المخرج قبل اعتماده للعميل.');
        $approvalB = $this->createApproval($workspace, $projectB, 'tool_run', $runB[1]->id, 'approved', $admin, 'تمت مراجعة التقرير التنفيذي واعتماده.');
        $approvalC = $this->createApproval($workspace, $projectC, 'ai_generation', $generationsB[0]->id, 'pending', $clientUser, 'بانتظار موافقة العميل على خطة المحتوى.');
        $approvalD = $this->createApproval($workspace, $projectA, 'ai_generation', $generationsA[1]->id, 'rejected', $clientUser, 'العنوان يحتاج نبرة أكثر مباشرة.');

        $this->seedComment($workspace, $clientUser, 'approval', $approvalC->id, 'أفضل تقليل طول الخطة وجعلها أكثر مباشرة.');
        $this->seedComment($workspace, $owner, 'approval', $approvalD->id, 'تم استلام الملاحظة وسيعاد توليد نسخة جديدة.');

        $this->seedFeatureAudience($workspace);
        $this->seedCredits($account, 600, 'agency_demo_seed');
        $this->seedAudit($owner, $workspace, 'demo.workspace_seeded', Workspace::class, $workspace->id, [
            'persona' => 'agency',
        ]);

        $this->onboardingState->markCompleted($workspace, [
            'client_name' => $clientA->name,
            'project_name' => $projectA->name,
        ]);
    }

    private function makeUser(string $email, string $name): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(self::DEMO_PASSWORD),
                'locale' => 'ar',
                'status' => 'active',
                'is_super_admin' => false,
                'email_verified_at' => now(),
            ],
        );
    }

    private function createAccount(User $owner, string $planCode, string $name): Account
    {
        $plan = Plan::query()->where('code', $planCode)->firstOrFail();

        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'name' => $name,
            'billing_email' => $owner->email,
            'status' => 'active',
        ]);

        Subscription::query()->create([
            'account_id' => $account->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'current_period_end' => now()->addMonth(),
        ]);

        return $account;
    }

    private function createWorkspace(Account $account, string $name, string $type): Workspace
    {
        return Workspace::query()->create([
            'account_id' => $account->id,
            'name' => $name,
            'type' => $type,
            'status' => 'active',
        ]);
    }

    private function attachMember(Workspace $workspace, User $user, string $role): void
    {
        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
            'invited_at' => now()->subDays(rand(1, 20)),
        ]);
    }

    private function attachAccountMember(Account $account, User $user, string $role): void
    {
        AccountMember::query()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
            'invited_at' => now()->subDays(3),
        ]);
    }

    private function createClient(Workspace $workspace, string $name, string $status, ?string $email = null): Client
    {
        return Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => $name,
            'contact_info' => [
                'email' => $email,
                'phone' => '+966500000000',
                'company' => $name,
            ],
            'status' => $status,
        ]);
    }

    private function createProject(
        Workspace $workspace,
        ?Client $client,
        string $name,
        int $stage,
        string $status,
    ): Project {
        return Project::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client?->id,
            'name' => $name,
            'stage' => $stage,
            'status' => $status,
        ]);
    }

    /**
     * @param  array<int, string>  $toolCodes
     * @return array<int, ToolRun>
     */
    private function seedToolRuns(Workspace $workspace, Project $project, User $actor, array $toolCodes): array
    {
        return collect($toolCodes)
            ->map(function (string $code, int $index) use ($workspace, $project, $actor): ToolRun {
                $tool = Tool::query()->where('code', $code)->firstOrFail();

                $run = $this->runToolAction->handle(
                    $workspace,
                    $project->fresh('client'),
                    $tool,
                    $actor,
                    $index % 2 === 0 ? 'quick' : 'advanced',
                    ['brief' => 'Seeded demo context for '.$tool->name]
                );

                $run->forceFill([
                    'created_at' => Carbon::now()->subDays(8 - $index)->subHours($index + 1),
                    'updated_at' => Carbon::now()->subDays(8 - $index)->subHours($index + 1),
                ])->save();

                return $run;
            })
            ->all();
    }

    /**
     * @param  array<int, string>  $templateCodes
     * @return array<int, AIGeneration>
     */
    private function seedGenerations(Workspace $workspace, Project $project, User $actor, array $templateCodes): array
    {
        return collect($templateCodes)
            ->map(function (string $code, int $index) use ($workspace, $project, $actor): AIGeneration {
                $template = AITemplate::query()->where('code', $code)->firstOrFail();

                $generation = $this->generateTemplateDraftAction->handle(
                    $workspace,
                    $template,
                    $project->fresh('client'),
                    $actor,
                    'هذا brief تجريبي لتمثيل استخدام الاستوديو داخل المشروع.'
                );

                $generation->forceFill([
                    'created_at' => Carbon::now()->subDays(5 - $index)->subMinutes(($index + 1) * 15),
                    'updated_at' => Carbon::now()->subDays(5 - $index)->subMinutes(($index + 1) * 15),
                ])->save();

                return $generation;
            })
            ->all();
    }

    private function createApproval(
        Workspace $workspace,
        Project $project,
        string $itemType,
        int $itemId,
        string $status,
        User $reviewer,
        string $note,
    ): Approval {
        return Approval::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'item_type' => $itemType,
            'item_id' => $itemId,
            'status' => $status,
            'reviewer_id' => $reviewer->id,
            'note' => $note,
        ]);
    }

    private function seedComment(Workspace $workspace, User $author, string $entityType, int $entityId, string $body): void
    {
        Comment::query()->create([
            'workspace_id' => $workspace->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'author_id' => $author->id,
            'body' => $body,
        ]);
    }

    private function seedCredits(Account $account, int $delta, string $reason): void
    {
        AICreditsLedger::query()->create([
            'account_id' => $account->id,
            'delta' => $delta,
            'reason' => $reason,
            'created_at' => now()->subDays(2),
        ]);
    }

    private function seedAudit(User $actor, Workspace $workspace, string $action, string $targetType, int $targetId, array $meta): void
    {
        AuditLog::query()->create([
            'actor_user_id' => $actor->id,
            'workspace_id' => $workspace->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'meta' => $meta,
            'created_at' => now()->subDay(),
        ]);
    }

    private function seedFeatureAudience(Workspace $workspace): void
    {
        $flag = FeatureFlag::query()->where('key', 'agency.beta_workspace')->first();

        if (! $flag) {
            return;
        }

        FeatureFlagAudience::query()->create([
            'feature_flag_id' => $flag->id,
            'audience_type' => 'workspace',
            'audience_id' => $workspace->id,
        ]);
    }
}
