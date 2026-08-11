<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تفضيل لغة المستخدم.
 *
 * `null` لا `'ar'`: القيمة الفارغة تعني «لم يختر»، فيتبع المتصفح أو
 * الافتراضي. لو كتبنا `'ar'` افتراضًا لصار كل حساب قديم مختارًا العربية
 * صراحةً، فلا يرى الزائر الفرنسي واجهته حتى لو كان متصفحه فرنسيًّا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('locale', 12)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
