<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * محرك النمو المستمر: التحوّل من «تقرير يُطلب مرة» إلى «نظام يراقب ويعود بالقيمة».
 *
 * أربع قدرات بجداولها: مراقبة التقارير (التقرير الحي)، النبض الأسبوعي،
 * حزمة الظهور في محركات الذكاء (GEO)، والجمهور الاصطناعي — مع تغذية راجعة
 * عامة تُعلّم المنصة أي المخرجات ينفع فعلًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        // مراقب التقرير: يحفظ بصمة مدخلات التقرير يوم تفعيله، ويُفحص دوريًا
        // ضد حالة المشروع الحالية. تغيّرت البصمة = تغيّر ما بُني عليه التقرير.
        Schema::create('report_watchers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->string('baseline_fingerprint', 64);
            // آخر بصمة أُشعر عنها: تمنع تكرار التنبيه عن نفس التغيير كل يوم.
            $table->string('notified_fingerprint', 64)->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_changed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_checked_at']);
        });

        // النبض الأسبوعي: خلاصة أسبوع واحدة لكل مشروع — ما تغيّر وما الخطوة.
        Schema::create('pulse_digests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->json('items');
            $table->json('next_step')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'week_start']);
            $table->index(['workspace_id', 'week_start']);
        });

        // حزمة الظهور للآلات: نسخة المشروع القابلة للاستهلاك من مساعدات الذكاء
        // الاصطناعي — حقائق، أسئلة وأجوبة، JSON-LD، وملف llms.txt.
        Schema::create('geo_packs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('facts');
            $table->json('faq');
            $table->json('jsonld');
            $table->longText('llms_txt');
            $table->json('credibility')->nullable();
            // ai = صاغه النموذج، rules = الأرضية الحتمية حين تعذّر التوليد.
            $table->string('source', 20)->default('ai');
            $table->timestamp('generated_at');
            $table->timestamps();
        });

        // الجمهور الاصطناعي: لوحة شخصيات ثابتة لكل مشروع تُختبر عليها الرسائل.
        Schema::create('persona_panels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('personas');
            $table->string('source', 20)->default('ai');
            $table->timestamp('generated_at');
            $table->timestamps();
        });

        Schema::create('persona_tests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('persona_panel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->json('results');
            $table->timestamps();

            $table->index(['persona_panel_id', 'created_at']);
        });

        // تغذية راجعة عامة على أي مخرج (تقرير الآن، وأي كيان لاحقًا عبر morphs):
        // هذه هي حلقة التعلّم التي تتحسن بها الصياغة مع الوقت.
        Schema::create('content_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('subject');
            $table->string('verdict', 8);
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_feedback');
        Schema::dropIfExists('persona_tests');
        Schema::dropIfExists('persona_panels');
        Schema::dropIfExists('geo_packs');
        Schema::dropIfExists('pulse_digests');
        Schema::dropIfExists('report_watchers');
    }
};
