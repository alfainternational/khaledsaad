<?php

use App\Modules\Shared\Evidence\EvidenceLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الدماغ التجاري: طبقة الحقائق واللقطات والأحداث.
 *
 * المفتاح project_id لا account_id: المواصفة كانت تستخدم كلمة «حساب» لمعنيين
 * متضاربين — النشاط التجاري هنا، ومالك ميزانية الاستعلامات (Workspace) في
 * سقف التكلفة. عمود باسم account_id يحمل معرّف مشروع كان سيُقرأ لاحقًا كمفتاح
 * إلى users أو workspaces.
 *
 * لا backfill: بيانات المستخدمين الحالية تجريبية بقرار المالك، والنقل من
 * project_knowledge_sources يجري بالكود لا بالهجرة. يبقى الجدول القديم قائمًا
 * حتى نقل خدماته، ثم يُسقط بهجرة لاحقة كي لا يبقى مصدر حقيقة ثانٍ.
 */
return new class extends Migration
{
    /**
     * الجداول التي تحمل مخرجات تُعرض للمستخدم، فتلزمها مرتبة دليل.
     *
     * @var array<int, string>
     */
    private const EVIDENCE_TABLES = [
        'project_knowledge_sources',
        'findings',
        'recommendations',
        'consultation_answers',
        'consultation_inferences',
    ];

    public function up(): void
    {
        Schema::create('brain_facts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->json('value_json')->nullable();
            $table->string('value_hash', 64)->nullable();
            $table->string('evidence_level')->default(EvidenceLevel::Inferred->value);
            $table->string('source_module');
            $table->string('source_reference')->nullable();
            $table->string('period')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('observed_at');

            /*
             * الحقيقة لا تُحذف بل تُستبدل: التاريخ هو ما يجعل الدماغ ذكيًا،
             * وبدونه لا يمكن معرفة متى تغيّر النشاط ولا إعادة إنتاج درجة قديمة.
             */
            $table->foreignId('superseded_by')->nullable()
                ->constrained('brain_facts')->nullOnDelete();

            $table->timestamps();

            $table->index(['project_id', 'key', 'observed_at'], 'brain_facts_project_key_time');
            // الحقائق السارية = ما لم يُستبدل. استعلام يتكرر في كل قراءة سياق.
            $table->index(['project_id', 'superseded_by'], 'brain_facts_project_active');
        });

        Schema::create('brain_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamp('taken_at');
            $table->json('payload');
            // شكل الحمولة يتغير مع نمو الدماغ؛ بلا رقم شكل تصير اللقطات القديمة غير مقروءة.
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->timestamps();

            $table->index(['project_id', 'taken_at']);
        });

        Schema::create('brain_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->json('body')->nullable();
            // النتيجة تُملأ لاحقًا: الحدث يُسجَّل عند وقوعه وتُعرف نتيجته بعد حين.
            $table->string('outcome')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['project_id', 'type', 'occurred_at'], 'brain_events_project_type_time');
        });

        foreach (self::EVIDENCE_TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('evidence_level')->default(EvidenceLevel::Inferred->value)
                    ->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach (self::EVIDENCE_TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn('evidence_level');
            });
        }

        Schema::dropIfExists('brain_events');
        Schema::dropIfExists('brain_snapshots');

        // المفتاح الذاتي يمنع إسقاط الجدول قبل فكّه.
        Schema::table('brain_facts', function (Blueprint $table): void {
            $table->dropForeign(['superseded_by']);
        });
        Schema::dropIfExists('brain_facts');
    }
};
