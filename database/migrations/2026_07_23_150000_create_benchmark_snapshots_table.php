<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // لقطة رقم سوق حيّ: تُجلب مرة وتُقرأ كثيرًا، ولها تاريخ يُعرض للمستخدم
        // حتى يعرف متى قيس هذا الرقم بدل أن يظنه لحظة فتحه للصفحة.
        Schema::create('benchmark_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('metric');
            $table->string('industry')->nullable();
            $table->string('geography')->nullable();
            $table->string('business_model')->nullable();
            $table->decimal('value_low', 12, 2)->nullable();
            $table->decimal('value_high', 12, 2)->nullable();
            $table->string('unit', 20)->default('SAR');
            $table->string('source_name');
            $table->string('source_url')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['metric', 'industry', 'geography', 'business_model'], 'benchmark_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benchmark_snapshots');
    }
};
