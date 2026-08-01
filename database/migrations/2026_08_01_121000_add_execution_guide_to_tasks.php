<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المهمة تصير قابلة للتنفيذ لا عنوانًا في عمود.
 *
 * كانت المهمة تنسخ عنوان التوصية ووصفها فقط، فيصل صاحب النشاط إلى لوحة
 * فيها «حسّن وضوح العرض» بلا خطوة واحدة يبدأ بها. الأعمدة هنا تحمل ما
 * يجعلها منفَّذة: خطوات مرقّمة، سقف زمني مقروء، ودليل تنفيذ (كيف/متى/أين/
 * ماذا تقدّم) مع أمثلة قابلة للنسخ.
 *
 * guide_status يجعل التطوير بالذكاء الاصطناعي حالةً مرئية لا صمتًا:
 * pending أثناء الطابور، ready عند النجاح، deterministic حين تُبنى من
 * الأرضية الحتمية بعد فشل المزود. المهمة تبقى حيّة قابلة للتحديث (§٤.٥)،
 * فالدليل يُعاد توليده دون إنشاء مهمة ثانية.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->json('steps')->nullable()->after('description');
            $table->json('worked_example')->nullable()->after('steps');
            $table->json('guide')->nullable()->after('worked_example');
            $table->string('guide_status')->default('none')->after('guide');
            $table->timestamp('guide_generated_at')->nullable()->after('guide_status');
            $table->string('timeframe')->nullable()->after('effort');
            $table->timestamp('reminder_at')->nullable()->after('due_date');
            $table->timestamp('reminded_at')->nullable()->after('reminder_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn([
                'steps', 'worked_example', 'guide', 'guide_status',
                'guide_generated_at', 'timeframe', 'reminder_at', 'reminded_at',
            ]);
        });
    }
};
