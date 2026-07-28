<?php

namespace Tests\Feature;

use App\Console\Commands\ResetPlatformData;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Modules\Brain\BrainWriter;
use App\Modules\Shared\Evidence\EvidenceLevel;
use App\Services\Projects\ProjectService;
use Database\Seeders\PaymentSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * أمر التحوّل: يمسح التجريبي ويُبقي ما يُشغّل المنصة.
 *
 * أخطر أمر في المستودع، ولذلك كل بوابة فيه لها اختبار. الخطأ هنا لا يُكتشف
 * إلا بعد فوات الأوان.
 */
class PlatformResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ToolCatalogSeeder::class, PlanSeeder::class, PaymentSeeder::class]);
    }

    #[Test]
    public function it_refuses_to_run_without_a_verified_backup(): void
    {
        $this->admin();

        // نسخة لم يُجرَّب استرجاعها ليست نسخة.
        $this->artisan('platform:reset', ['--force' => true])
            ->expectsOutputToContain('backup-verified')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_refuses_a_confirmation_that_is_not_typed_exactly(): void
    {
        $this->admin();
        $project = $this->trialProject();

        $this->artisan('platform:reset', ['--backup-verified' => true])
            ->expectsQuestion('اكتب «امسح» للمتابعة', 'نعم')
            ->assertExitCode(1);

        // الضغط بالعادة لا يمسح شيئًا.
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    #[Test]
    public function it_refuses_when_no_admin_would_survive(): void
    {
        User::factory()->create(['is_admin' => false]);

        // مسح بلا حساب إدارة يقفل الباب على الجميع.
        $this->artisan('platform:reset', ['--backup-verified' => true, '--force' => true])
            ->expectsOutputToContain('لا يوجد حساب إدارة')
            ->assertExitCode(1);

        $this->assertSame(1, DB::table('users')->count());
    }

    #[Test]
    public function a_dry_run_reports_without_touching_anything(): void
    {
        $this->admin();
        $project = $this->trialProject();

        $this->artisan('platform:reset', ['--dry-run' => true])->assertExitCode(0);

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        $this->assertGreaterThan(0, DB::table('brain_facts')->count());
    }

    #[Test]
    public function it_wipes_trial_data_and_keeps_the_operating_shell(): void
    {
        $admin = $this->admin();
        $this->trialProject();
        Setting::updateOrCreate(['key' => 'ai.provider'], ['value' => 'deepseek']);

        $this->artisan('platform:reset', ['--backup-verified' => true, '--force' => true])
            ->assertExitCode(0);

        // مُسح
        $this->assertSame(0, DB::table('projects')->count());
        $this->assertSame(0, DB::table('brain_facts')->count());
        $this->assertSame(0, DB::table('project_profiles')->count());

        // محفوظ: الغلاف الذي يُشغّل المنصة
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertDatabaseHas('settings', ['key' => 'ai.provider']);
        $this->assertGreaterThan(0, DB::table('plans')->count());
        $this->assertGreaterThan(0, DB::table('payment_gateways')->count());
        $this->assertGreaterThan(0, DB::table('tools')->count());
    }

    #[Test]
    public function the_admin_keeps_a_workspace_so_the_door_stays_open(): void
    {
        $admin = $this->admin();
        app(ProjectService::class)->create($admin, ['name' => 'مشروع الإدارة']);
        $this->trialProject();

        $this->artisan('platform:reset', ['--backup-verified' => true, '--force' => true])
            ->assertExitCode(0);

        $this->assertSame(1, DB::table('users')->count());
        $this->assertGreaterThan(0, DB::table('workspaces')->where('owner_id', $admin->id)->count());
    }

    #[Test]
    public function the_schema_and_migration_history_survive(): void
    {
        $this->admin();
        $this->trialProject();
        $migrations = DB::table('migrations')->count();

        $this->artisan('platform:reset', ['--backup-verified' => true, '--force' => true])
            ->assertExitCode(0);

        /*
         * مسح سجل الهجرات يجعلها تُعاد فتنهار القاعدة. والجداول تبقى موجودة:
         * الأمر يفرّغ صفوفًا ولا يُسقط بنية.
         */
        $this->assertSame($migrations, DB::table('migrations')->count());
        $this->assertTrue(Schema::hasTable('projects'));
        $this->assertTrue(Schema::hasTable('brain_facts'));
    }

    #[Test]
    public function every_wiped_table_actually_exists_in_the_schema(): void
    {
        /*
         * اسم جدول مكتوب خطأً يُتجاوَز بصمت، فيبقى ممتلئًا بعد «مسح ناجح».
         * هذا الاختبار يجعل الخطأ المطبعي مرئيًّا.
         */
        $reflection = new \ReflectionClass(ResetPlatformData::class);
        $wipe = $reflection->getConstant('WIPE');
        $keep = $reflection->getConstant('KEEP');

        foreach ([...$wipe, ...$keep] as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "الجدول {$table} مذكور في الأمر ولا وجود له.",
            );
        }
    }

    #[Test]
    public function no_table_is_both_kept_and_wiped(): void
    {
        $reflection = new \ReflectionClass(ResetPlatformData::class);

        $overlap = array_intersect(
            $reflection->getConstant('WIPE'),
            $reflection->getConstant('KEEP'),
        );

        $this->assertSame([], $overlap, 'جدول في القائمتين: النية فيه ملتبسة.');
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function trialProject(): Project
    {
        $user = User::factory()->create(['is_admin' => false]);
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع تجريبي']);

        app(BrainWriter::class)->record(
            $project, 'value_proposition', 'قيمة', EvidenceLevel::Inferred, 'Intake',
        );

        return $project;
    }
}
