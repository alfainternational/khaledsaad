<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * منح (أو سحب) صلاحية الإدارة لمستخدم بالبريد.
 *
 * الطريق الوحيد لأول آدمن دون لمس قاعدة البيانات يدويًا، وللتحكم بمن يرى
 * لوحة الإعدادات والمفاتيح.
 */
class MakeUserAdmin extends Command
{
    protected $signature = 'users:make-admin {email} {--revoke : سحب الصلاحية بدل منحها}';

    protected $description = 'منح صلاحية الإدارة لمستخدم بالبريد الإلكتروني';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('لا يوجد مستخدم بهذا البريد: '.$this->argument('email'));

            return self::FAILURE;
        }

        $grant = ! $this->option('revoke');
        $user->forceFill(['is_admin' => $grant])->save();

        $this->info($grant
            ? "أصبح «{$user->name}» ({$user->email}) آدمن."
            : "سُحبت صلاحية الإدارة من «{$user->name}».");

        return self::SUCCESS;
    }
}
