<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * توحيد كلمة مرور كل المستخدمين (بمن فيهم الإدارة) إلى قيمة معلومة.
 *
 * عملية إدارية مقصودة على قاعدة بيانات المالك. تُشغَّل يدويًا عند الحاجة.
 */
class ResetAllPasswords extends Command
{
    protected $signature = 'users:reset-password {password} {--only-admins : الإدارة فقط}';

    protected $description = 'تعيين كلمة مرور موحّدة لكل المستخدمين';

    public function handle(): int
    {
        $password = (string) $this->argument('password');
        $hash = Hash::make($password);

        $query = User::query()->when(
            $this->option('only-admins'),
            fn ($q) => $q->where('is_admin', true),
        );

        $count = $query->count();
        $query->update(['password' => $hash]);

        $this->info("حُدّثت كلمة مرور {$count} مستخدمًا.");

        return self::SUCCESS;
    }
}
