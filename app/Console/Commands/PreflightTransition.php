<?php

namespace App\Console\Commands;

use App\Models\PaymentGateway;
use App\Models\User;
use App\Support\Deployment\ClassmapAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * فحص ما قبل التحوّل: يقرأ ولا يكتب.
 *
 * يُشغَّل على الخادم قبل `platform:reset`. كل بند فيه سبق أن كان سيتحوّل إلى
 * عطل صامت بعد المسح، حين لا يبقى ما يُرجع إليه:
 *
 *   - `payment_gateways.credentials` مشفَّرة بـ`APP_KEY`. مفتاح مبدَّل يعني
 *     صفوفًا سليمة الشكل لا تُفكّ، ومنصة بلا مسار دفع عامل.
 *   - خريطة الأصناف تُولَّد وقت التثبيت. إدخال يشير إلى ملف منقول يكسر
 *     الأصناف المُحمَّلة عبر الحاوية وحدها، فيبدو الموقع سليمًا حتى يزور
 *     أحدهم صفحة متأثرة.
 *   - هجرة معلّقة تعني قاعدة لا تطابق الكود.
 *   - غياب حساب إدارة يجعل المسح يقفل الباب على الجميع.
 */
class PreflightTransition extends Command
{
    protected $signature = 'platform:preflight';

    protected $description = 'فحص جاهزية الخادم للتحوّل: المفتاح، بوابات الدفع، خريطة الأصناف، الهجرات';

    private bool $blocked = false;

    public function handle(): int
    {
        $this->line('فحص ما قبل التحوّل — قراءة فقط، لا يكتب شيئًا.');
        $this->newLine();

        $rows = [
            $this->checkAppKey(),
            $this->checkGateways(),
            $this->checkClassmap(),
            $this->checkMigrations(),
            $this->checkAdmin(),
        ];

        $this->table(['الفحص', 'الحالة', 'التفصيل'], $rows);

        if ($this->blocked) {
            $this->error('التحوّل محجوب. أصلح ما سبق قبل تشغيل platform:reset.');

            return self::FAILURE;
        }

        $this->info('الخادم جاهز للتحوّل.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function checkAppKey(): array
    {
        $key = (string) config('app.key');

        if ($key === '') {
            return $this->blocker('APP_KEY', 'غير مضبوط — لا شيء يُفكّ بلا مفتاح.');
        }

        // البصمة لا المفتاح: تكفي لمطابقة بيئتين بلا تسريب السر في السجلات.
        return $this->ok('APP_KEY', 'مضبوط · بصمة '.substr(hash('sha256', $key), 0, 12));
    }

    /**
     * @return array<int, string>
     */
    private function checkGateways(): array
    {
        if (! Schema::hasTable('payment_gateways')) {
            return $this->blocker('بوابات الدفع', 'الجدول غير موجود.');
        }

        $total = 0;
        $failed = [];

        foreach (PaymentGateway::all() as $gateway) {
            $total++;

            try {
                // القراءة وحدها تفكّ التشفير عبر cast؛ الفشل يرمي.
                $credentials = $gateway->credentials;

                if ($credentials === null) {
                    continue;
                }
            } catch (Throwable) {
                $failed[] = $gateway->provider;
            }
        }

        if ($total === 0) {
            return $this->caution('بوابات الدفع', 'لا بوابات مُعرَّفة.');
        }

        if ($failed !== []) {
            return $this->blocker(
                'بوابات الدفع',
                'تعذّر فكّ: '.implode('، ', $failed).' — المفتاح لا يطابق ما شُفِّرت به.',
            );
        }

        return $this->ok('بوابات الدفع', "{$total} بوابة، كلها تُفكّ بالمفتاح الحالي.");
    }

    /**
     * @return array<int, string>
     */
    private function checkClassmap(): array
    {
        $file = base_path('vendor/composer/autoload_static.php');

        if (! is_file($file)) {
            return $this->caution('خريطة الأصناف', 'لا يوجد autoload_static — توليد غير مُحسَّن.');
        }

        // نفس المنطق الذي يستعمله المُصلح، لا نسخة ثانية منه.
        $audit = app(ClassmapAudit::class);
        $foreign = count($audit->foreignInStatic($file, base_path()));

        if ($foreign > 0) {
            return $this->blocker(
                'خريطة الأصناف',
                "{$foreign} إدخالًا يُحمّل الكود من worktree آخر — أعد توليد Composer من جذر هذا الإصدار.",
            );
        }

        $stale = count($audit->staleInStatic($file))
            + count($audit->staleInClassmap(base_path('vendor/composer/autoload_classmap.php')));

        if ($stale > 0) {
            return $this->blocker(
                'خريطة الأصناف',
                "{$stale} إدخالًا يشير إلى ملف غير موجود — شغّل: php deploy/prune-classmap.php",
            );
        }

        return $this->ok('خريطة الأصناف', 'سليمة.');
    }

    /**
     * @return array<int, string>
     */
    private function checkMigrations(): array
    {
        if (! Schema::hasTable('migrations')) {
            return $this->blocker('الهجرات', 'جدول الهجرات غير موجود.');
        }

        $ran = DB::table('migrations')->pluck('migration')->all();
        $files = glob(database_path('migrations/*.php')) ?: [];
        $pending = [];

        foreach ($files as $file) {
            $name = basename($file, '.php');

            if (! in_array($name, $ran, true)) {
                $pending[] = $name;
            }
        }

        if ($pending !== []) {
            return $this->blocker('الهجرات', count($pending).' معلّقة — القاعدة لا تطابق الكود.');
        }

        return $this->ok('الهجرات', count($ran).' مُطبَّقة، ولا معلّقة.');
    }

    /**
     * @return array<int, string>
     */
    private function checkAdmin(): array
    {
        $admins = User::query()->where('is_admin', true)->count();

        if ($admins === 0) {
            return $this->blocker('حساب الإدارة', 'لا يوجد — المسح سيقفل الباب على الجميع.');
        }

        return $this->ok('حساب الإدارة', "{$admins} حساب سيبقى بعد المسح.");
    }

    /**
     * @return array<int, string>
     */
    private function ok(string $name, string $detail): array
    {
        return [$name, 'سليم', $detail];
    }

    /**
     * @return array<int, string>
     */
    private function caution(string $name, string $detail): array
    {
        return [$name, 'تنبيه', $detail];
    }

    /**
     * @return array<int, string>
     */
    private function blocker(string $name, string $detail): array
    {
        $this->blocked = true;

        return [$name, 'حاجب', $detail];
    }
}
