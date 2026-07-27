<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مسار التقرير اليدوي بجانب التلقائي.
 *
 * العميل يختار: تحليل فوري داخل المنصة، أو مراجعة يدوية كاملة من خالد.
 * اليدوي يجمّد التشغيل بانتظار الآدمن، ثم يُركَّب التقرير بنفس البنية
 * مع توثيق أنه رُوجع وroدُقّق يدويًا.
 *
 * إضافة أعمدة فقط — لا إعادة تهيئة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tool_runs', function (Blueprint $table) {
            // auto = خط الأنابيب التلقائي | manual = بانتظار مراجعة الآدمن
            $table->string('delivery_mode')->default('auto')->after('status');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->string('review_mode')->default('auto')->after('status');
            $table->foreignId('reviewed_by')->nullable()->after('review_mode')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_mode', 'reviewed_at']);
        });

        Schema::table('tool_runs', function (Blueprint $table) {
            $table->dropColumn('delivery_mode');
        });
    }
};
