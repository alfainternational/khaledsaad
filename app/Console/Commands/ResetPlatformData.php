<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * مسح البيانات التجريبية عند التحوّل إلى الإصدار الأول.
 *
 * المنصة لم تُطرح لمستخدمين حقيقيين، واعتُمد أن بيانات المشاريع والتشخيصات
 * والتقارير تجريبية. يُحفظ الغلاف التشغيلي وحده: الإدارة والإعدادات وكتالوج
 * الخطط وبوابات الدفع.
 *
 * القيود التي تجعله آمنًا:
 *   - لا `migrate:fresh` ولا حذف جداول. البنية تبقى، والصفوف وحدها تُمسح.
 *   - قائمة المسح صريحة لا استثنائية: جدول جديد لا يُمسح حتى يُضاف هنا عمدًا.
 *     العكس — «امسح كل شيء عدا…» — يمسح كل جدول ينساه أحدهم.
 *   - يرفض العمل بلا تأكيد مكتوب، وبلا إقرار أن نسخة احتياطية جُرّب استرجاعها.
 *   - يطبع عدّ الصفوف قبل وبعد لكل جدول، محفوظًا وممسوحًا.
 *
 * الأمر لا يلمس `migrations` ولا `users` الإداريين: الأول يجعل الهجرات تُعاد
 * تشغيلها فتُبنى القاعدة من الصفر، والثاني يقفل الباب على الجميع.
 */
class ResetPlatformData extends Command
{
    protected $signature = 'platform:reset
        {--backup-verified : إقرار أن نسخة احتياطية أُخذت وجُرّب استرجاعها}
        {--dry-run : يطبع ما سيُمسح بلا مسح}
        {--force : يتخطى سؤال التأكيد (للنصوص الآلية فقط)}';

    protected $description = 'مسح البيانات التجريبية مع الإبقاء على الإدارة والإعدادات وبوابات الدفع';

    /**
     * الجداول التي تُفرَّغ. الترتيب من الأبناء إلى الآباء لاحترام المفاتيح الأجنبية.
     *
     * @var array<int, string>
     */
    private const WIPE = [
        // مخرجات التشخيص والتقارير
        'agency_report_views', 'agency_reports',
        'recommendations', 'findings', 'report_sections', 'report_watchers', 'reports',
        'brain_events', 'brain_snapshots', 'brain_facts',

        // الاستشارة
        'consultation_conflicts', 'consultation_events', 'consultation_evidence',
        'consultation_inferences', 'consultation_module_states', 'consultation_answers',
        'consultation_sessions',

        // تشغيلات الأدوات
        'tool_run_files', 'tool_run_answers', 'tool_run_stages', 'tool_runs',
        'ai_usage_records',

        // التنفيذ والمتابعة
        'kpi_entries', 'kpis', 'tasks', 'pulse_digests', 'geo_packs',
        'persona_tests', 'persona_panels', 'content_feedback', 'notifications',

        // المشاريع وما يتبعها
        'project_answers', 'project_audiences', 'project_competitors',
        'project_profiles', 'projects',

        // الفوترة التشغيلية (لا الكتالوج)
        'payment_webhook_events', 'payment_refunds', 'payments',
        'billing_audits', 'credit_transactions', 'credit_wallets', 'subscriptions',

        // الجلسات والطوابير والكاش
        'guest_sessions', 'device_tokens', 'personal_access_tokens',
        'password_reset_tokens', 'sessions', 'jobs', 'job_batches', 'failed_jobs',
        'cache', 'cache_locks',

        // معايير مشتقة تُعاد بناؤها
        'benchmark_snapshots',
    ];

    /**
     * الجداول المحفوظة كاملةً — تُعدّ صفوفها للتقرير ولا تُمسّ.
     *
     * @var array<int, string>
     */
    private const KEEP = [
        'settings', 'payment_gateways',
        'plans', 'features', 'plan_features', 'credit_packs',
        'tools', 'tool_versions', 'tool_fields', 'prompt_versions',
        'question_definitions', 'question_versions', 'question_rules', 'module_questions',
        'diagnostic_modules', 'blueprint_modules',
        'consultation_blueprints', 'consultation_blueprint_versions',
        'migrations',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->guard()) {
            return self::FAILURE;
        }

        $before = $this->counts();
        $admins = $this->adminIds();

        if ($admins === []) {
            $this->error('لا يوجد حساب إدارة. المسح سيقفل الباب على الجميع — أنشئ حساب إدارة أولًا.');

            return self::FAILURE;
        }

        $this->line('حسابات الإدارة المحفوظة: '.count($admins));

        if ($dryRun) {
            $this->table(['الجدول', 'صفوف ستُمسح'], $this->wipeRows($before));
            $this->warn('تجربة جافة: لم يُمسح شيء.');

            return self::SUCCESS;
        }

        $this->wipe($admins);
        $after = $this->counts();

        $this->newLine();
        $this->table(
            ['الجدول', 'قبل', 'بعد', 'الحالة'],
            $this->report($before, $after),
        );

        Log::warning('نُفِّذ مسح بيانات المنصة.', [
            'admins_kept' => count($admins),
            'tables_wiped' => count(self::WIPE),
            'rows_before' => array_sum($before),
            'rows_after' => array_sum($after),
        ]);

        $this->info('اكتمل المسح. البنية والهجرات لم تُمسّ.');

        return self::SUCCESS;
    }

    /**
     * بوابة ما قبل المسح.
     */
    private function guard(): bool
    {
        if (! $this->option('backup-verified')) {
            $this->error('ممنوع بلا --backup-verified: نسخة لم يُجرَّب استرجاعها ليست نسخة.');

            return false;
        }

        if ($this->option('force')) {
            return true;
        }

        // كتابة الكلمة لا ضغط «نعم»: يمنع التنفيذ بالعادة أو بالخطأ.
        $typed = $this->ask('اكتب «امسح» للمتابعة');

        if ($typed !== 'امسح') {
            $this->warn('أُلغي المسح.');

            return false;
        }

        return true;
    }

    /**
     * @param  array<int, int>  $admins
     */
    private function wipe(array $admins): void
    {
        DB::transaction(function () use ($admins): void {
            foreach (self::WIPE as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }

            /*
             * المستخدمون ومساحاتهم يُمسحون انتقائيًّا لا كليًّا: الإدارة تبقى
             * ومعها مساحتها، فيبقى الدخول ممكنًا وتبقى اشتراكات الإدارة قابلة
             * لإعادة الإنشاء.
             */
            $keptWorkspaces = DB::table('workspaces')->whereIn('owner_id', $admins)->pluck('id');

            DB::table('workspace_members')->whereNotIn('workspace_id', $keptWorkspaces)->delete();
            DB::table('workspaces')->whereNotIn('id', $keptWorkspaces)->delete();
            DB::table('users')->whereNotIn('id', $admins)->delete();
        });
    }

    /**
     * @return array<int, int>
     */
    private function adminIds(): array
    {
        return User::query()->where('is_admin', true)->pluck('id')->all();
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        $counts = [];

        foreach ([...self::WIPE, ...self::KEEP, 'users', 'workspaces', 'workspace_members'] as $table) {
            if (Schema::hasTable($table)) {
                $counts[$table] = DB::table($table)->count();
            }
        }

        return $counts;
    }

    /**
     * @param  array<string, int>  $before
     * @return array<int, array<int, string|int>>
     */
    private function wipeRows(array $before): array
    {
        $rows = [];

        foreach (self::WIPE as $table) {
            if (($before[$table] ?? 0) > 0) {
                $rows[] = [$table, $before[$table]];
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, int>  $before
     * @param  array<string, int>  $after
     * @return array<int, array<int, string|int>>
     */
    private function report(array $before, array $after): array
    {
        $rows = [];

        foreach ($before as $table => $count) {
            $now = $after[$table] ?? 0;

            if ($count === 0 && $now === 0) {
                continue;
            }

            $rows[] = [
                $table,
                $count,
                $now,
                in_array($table, self::KEEP, true) ? 'محفوظ' : 'مُسح',
            ];
        }

        return $rows;
    }
}
