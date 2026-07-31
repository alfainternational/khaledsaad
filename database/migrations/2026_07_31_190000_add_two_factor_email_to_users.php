<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// خطوة تحقق ثانية بالبريد عند الدخول — اختيارية لكل مستخدم (بند ٢٣).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('two_factor_email_enabled')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('two_factor_email_enabled');
        });
    }
};
