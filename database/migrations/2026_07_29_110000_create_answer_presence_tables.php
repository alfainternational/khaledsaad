<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * استطلاع الحضور في إجابات النماذج (المرحلة ٣).
 *
 * جدولان لأن الوحدة الذرّية ليست السؤال بل **المحاولة**: لا قياس من عيّنة
 * واحدة (§٤.٢). سؤال واحد بثلاث محاولات هو ثلاثة صفوف، ومنها وحدها يُحسب
 * `consistency`. لو خُزِّن السؤال بنتيجة واحدة لاستحال التمييز بين علامة
 * تظهر دائمًا وأخرى تظهر مرة من ثلاث.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presence_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('query_reservation_id')->nullable()->constrained()->nullOnDelete();

            $table->string('provider');
            $table->string('model');
            $table->string('locale', 8)->default('ar');

            $table->unsignedSmallInteger('questions_count')->default(0);
            $table->unsignedSmallInteger('attempts_per_question')->default(3);

            $table->string('status')->default('running');
            $table->text('failure_reason')->nullable();
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'started_at']);
        });

        Schema::create('presence_probes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('presence_run_id')->constrained()->cascadeOnDelete();

            $table->string('question_key');
            $table->text('question');

            // رقم المحاولة داخل السؤال الواحد: 1..attempts_per_question.
            $table->unsignedTinyInteger('attempt');

            $table->boolean('brand_mentioned')->default(false);
            $table->boolean('site_cited')->default(false);

            /*
             * كل العلامات المذكورة في هذه المحاولة، لا علامة العميل وحدها:
             * `share_of_voice` مقامه ذكر كل العلامات، وبلا حفظها يستحيل حسابه
             * إلا بإعادة الاستطلاع — أي بدفع ثمنه مرتين.
             */
            $table->json('brands_mentioned')->nullable();
            $table->json('citations')->nullable();

            /*
             * §١٤: raw_response يُحفظ كاملًا دائمًا. التصنيف قد يتغيّر — نكتشف
             * غدًا أن المطابقة أخطأت في اسم بلهجة — والنص الخام هو الدليل الذي
             * يسمح بإعادة التصنيف بلا إعادة الدفع.
             */
            $table->longText('raw_response')->nullable();

            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('status')->default('ok');
            $table->timestamps();

            $table->unique(['presence_run_id', 'question_key', 'attempt'], 'presence_probe_attempt_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presence_probes');
        Schema::dropIfExists('presence_runs');
    }
};
