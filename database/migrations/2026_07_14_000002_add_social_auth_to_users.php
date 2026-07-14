<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مصادقة اجتماعية (Socialite): تسجيل/دخول عبر Google/Facebook/Twitter/LinkedIn.
 * - password يصبح nullable (مستخدم اجتماعي بلا كلمة مرور).
 * - provider / provider_id لربط الهوية الاجتماعية.
 * - avatar لصورة المستخدم من المزوّد (اختياري).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('provider', 40)->nullable()->after('email');
            $table->string('provider_id', 191)->nullable()->after('provider');
            $table->string('avatar', 500)->nullable()->after('provider_id');

            $table->index(['provider', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['provider', 'provider_id']);
            $table->dropColumn(['provider', 'provider_id', 'avatar']);
            // ملاحظة: لا نعيد password إلى NOT NULL في down لتفادي كسر صفوف اجتماعية.
        });
    }
};
