<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // لغة العميل تُخزَّن مع الأداة نفسها: مشكلته، وما سيخرج به، وكم يستغرق.
        // بهذا لا تختلف الرسالة بين الويب والتطبيق، ولا تُكتب في مكانين.
        Schema::table('tools', function (Blueprint $table) {
            $table->text('pain')->nullable()->after('description');
            $table->text('promise')->nullable()->after('pain');
            $table->string('audience')->nullable()->after('promise');
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('audience');
        });

        // لكل سؤال سبب معلن: لماذا نسأله وكيف يغيّر النتيجة.
        Schema::table('tool_fields', function (Blueprint $table) {
            $table->string('why', 500)->nullable()->after('help');
            $table->string('example', 500)->nullable()->after('why');
        });

        // ذاكرة إجابات المشروع: ما أجاب عنه المستخدم مرة واحدة لا يُسأل عنه مجددًا
        // في أي أداة أخرى، ويبقى قابلًا للتعديل من مكان واحد.
        Schema::create('project_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('field_key');
            $table->json('value_json');
            $table->string('source_tool_key')->nullable();
            $table->foreignId('source_run_id')->nullable()->constrained('tool_runs')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_answers');

        Schema::table('tool_fields', function (Blueprint $table) {
            $table->dropColumn(['why', 'example']);
        });

        Schema::table('tools', function (Blueprint $table) {
            $table->dropColumn(['pain', 'promise', 'audience', 'duration_minutes']);
        });
    }
};
