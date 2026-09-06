<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تصنيف العطل يُخزَّن مع التشغيل — لأن الرسالة وحدها لا تكفي.
 *
 * كانت `failure_reason` تحمل نص الاستثناء الخام وتُعرض كما هي، فلم يكن
 * لدى أي طبقة طريقة لتعرف: أهذا عطلنا أم حدُّ المستخدم؟ العمودان هنا
 * يجعلان السؤال مُجابًا في البيانات لا في تخمين الواجهة (INV-8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tool_runs', function (Blueprint $table): void {
            $table->string('failure_kind', 16)->nullable()->after('failure_reason');
            $table->string('failure_code', 64)->nullable()->after('failure_kind');
            // التفصيل التقني ينزل هنا بدل أن يصعد إلى الشاشة.
            $table->text('failure_detail')->nullable()->after('failure_code');
        });
    }

    public function down(): void
    {
        Schema::table('tool_runs', function (Blueprint $table): void {
            $table->dropColumn(['failure_kind', 'failure_code', 'failure_detail']);
        });
    }
};
