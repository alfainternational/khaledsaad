<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * لغة المخرَج المولَّد تُحفظ معه.
 *
 * سبب وجود العمود: المخرَج يُولَّد مرة ويُقرأ مرات، وقد يُقرأ بلغة غير
 * التي وُلّد بها. بلا هذا العمود يُعرض تقرير كُتب بالعربية داخل شاشة
 * فرنسية بلا أي إشارة — فيبدو عطلًا في الترجمة لا حدًّا معلنًا، ولا يمكن
 * للنظام أن يعرض «هذا التقرير بالعربية» لأنه لا يعرف.
 *
 * الافتراضي `ar` للسجلات القائمة: كلها وُلّدت قبل هذه القدرة، والبرومبت
 * كان يفرض العربية نصًّا — فالقيمة حقيقة لا تخمين.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('locale', 8)->default('ar')->after('title');
        });

        /*
         * التشغيل يحمل لغته أيضًا لا التقرير وحده: أدلة المهام ومقترحات
         * الرسائل تُولَّد من تشغيل ولا تُنتج تقريرًا، ومع ذلك يقرؤها إنسان.
         */
        Schema::table('tool_runs', function (Blueprint $table) {
            $table->string('locale', 8)->default('ar')->after('status');
        });

        /*
         * محتوى الأكاديمية: لغة المادة نفسها. اليوم كلها عربية، والعمود
         * يجعل إضافة مادة بلغة أخرى تغييرَ بيانات لا تغييرَ مخطط.
         */
        Schema::table('contents', function (Blueprint $table) {
            $table->string('locale', 8)->default('ar')->after('title');
            $table->index(['locale', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('locale');
        });

        Schema::table('tool_runs', function (Blueprint $table) {
            $table->dropColumn('locale');
        });

        Schema::table('contents', function (Blueprint $table) {
            $table->dropIndex(['locale', 'status']);
            $table->dropColumn('locale');
        });
    }
};
