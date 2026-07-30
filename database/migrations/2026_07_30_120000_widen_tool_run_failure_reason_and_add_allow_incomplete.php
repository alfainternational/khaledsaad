<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سببان في هجرة واحدة على جدول tool_runs:
 *
 * 1) failure_reason كان VARCHAR(255)، ورسالة «بيانات ناقصة: …» تتجاوزه فتنهار
 *    كتابةُ سبب الفشل نفسها (SQLSTATE 22001) وتُسقط وظيفة الطابور — فتعلق الدفعة.
 *    توسيعه إلى TEXT يجعل تسجيل السبب لا يفشل مهما طال.
 *
 * 2) allow_incomplete: التشخيص الشامل يُشغّل «بما هو معروف» (§٤.٣)، لكن مرحلة
 *    التحقق كانت ترمي على أي نقص. العلَم يُمرَّر من ToolRunService::queue ويُقرأ
 *    في خط الأنابيب ليُكمل بفجوات معلنة بدل أن يفشل. التشغيل المستقل يبقى صارمًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tool_runs', function (Blueprint $table): void {
            $table->text('failure_reason')->nullable()->change();
            $table->boolean('allow_incomplete')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tool_runs', function (Blueprint $table): void {
            $table->string('failure_reason')->nullable()->change();
            $table->dropColumn('allow_incomplete');
        });
    }
};
