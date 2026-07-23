<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * إنشاء (أو تحديث) حساب آدمن حقيقي بالبريد وكلمة المرور، وضمان مساحة عمل.
 *
 * الطريق الموثوق لأول دخول فعلي على الإنتاج دون لمس قاعدة البيانات يدويًا.
 */
class EnsureAdminAccount extends Command
{
    protected $signature = 'users:create-admin {email} {--password=} {--name=}';

    protected $description = 'إنشاء أو تحديث حساب آدمن بكلمة مرور معلومة';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $password = (string) ($this->option('password') ?: 'Aa@123456#');
        $name = (string) ($this->option('name') ?: 'خالد سعد');

        $existed = User::where('email', $email)->exists();

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,   // يُشفَّر تلقائيًا عبر cast hashed
            ],
        );

        // is_admin وemail_verified_at خارج fillable (حماية الإسناد الجماعي)،
        // فنضبطهما بـ forceFill حتى لا يُسقطا بصمت.
        $user->forceFill([
            'is_admin' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        // مساحة العمل تُنشأ كسولًا عند الحاجة؛ نضمنها الآن حتى لا تتعثر اللوحة.
        $user->primaryWorkspace();

        $this->info(($existed ? 'حُدّث' : 'أُنشئ')." حساب الآدمن: {$user->email}");
        $this->line("كلمة المرور: {$password}");

        return self::SUCCESS;
    }
}
