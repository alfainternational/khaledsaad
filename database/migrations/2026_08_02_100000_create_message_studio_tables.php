<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * استوديو الرسائل: إصدارات مستقلة لكل شخصية، ودفعات اختبار ونتائجها.
 *
 * الإصدار لا يُكتب فوقه بعد اختباره — يُنشأ إصدار جديد يشير إلى أبيه.
 * بذلك يبقى ما اختُبر مطابقًا لما قيست عليه النتيجة، ولا تصير الدرجة
 * مربوطة بنصٍّ تغيّر بعدها.
 *
 * جدولا persona_panels وpersona_tests يبقيان كما هما — السجل القديم
 * يُعرض ولا يُحوَّل، لأن تحويل رسالة عامة إلى رسائل شخصيات يخترع نية.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('persona_panel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // مفتاح مشتق من هوية الشخصية لا من ترتيبها في اللوحة.
            $table->string('persona_key', 64);
            $table->string('channel', 32);
            $table->string('objective', 32);
            $table->text('content');
            $table->string('origin', 16)->default('manual');
            $table->string('status', 16)->default('draft');
            $table->foreignId('parent_id')->nullable()
                ->constrained('message_variants')->nullOnDelete();
            // ربط عام بالمصدر: أداة أو تقرير، دون عمود لكل أداة.
            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->json('source_context')->nullable();
            $table->text('teaching_note')->nullable();
            $table->string('reusable_formula')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'persona_key', 'status']);
            $table->index(['persona_panel_id', 'status']);
        });

        Schema::create('message_test_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('persona_panel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 16)->default('single');
            $table->string('channel', 32)->nullable();
            $table->string('objective', 32)->nullable();
            $table->string('status', 16)->default('complete');
            // مقارنة فقط — لا رسالة موحّدة في الخلاصة.
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
        });

        Schema::create('message_test_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_test_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_variant_id')->constrained()->cascadeOnDelete();
            $table->string('persona_key', 64);
            $table->unsignedTinyInteger('score');
            $table->text('reaction');
            $table->text('strength')->nullable();
            $table->text('objection')->nullable();
            $table->text('revised_content')->nullable();
            $table->json('model_metadata')->nullable();
            $table->timestamps();

            $table->index(['message_variant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_test_results');
        Schema::dropIfExists('message_test_batches');
        Schema::dropIfExists('message_variants');
    }
};
