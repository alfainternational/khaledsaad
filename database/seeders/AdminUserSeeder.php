<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * حساب الآدمن الأساسي، يُعاد إنشاؤه مع كل بذر حتى لا يختفي عند تهيئة القاعدة.
 *
 * idempotent: لا يكرّر الحساب، ويضبط الصلاحية وكلمة المرور في كل تشغيل.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'khaledaasaad@gmail.com'],
            ['name' => 'خالد سعد', 'password' => 'Aa@123456#'],
        );

        // is_admin وemail_verified_at خارج fillable: نضبطهما بـ forceFill.
        $user->forceFill([
            'is_admin' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $user->primaryWorkspace();
    }
}
