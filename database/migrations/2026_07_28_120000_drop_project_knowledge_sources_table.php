<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إسقاط `project_knowledge_sources` بعد انتقال وظيفته إلى `brain_facts`.
 *
 * الجدولان كانا يحملان الشيء نفسه: سجلًّا تراكميًّا لما يعرفه النظام عن
 * المشروع. بقاؤهما معًا يخلق مصدرَي حقيقة، وهو ما تمنعه §١٥ صراحةً — ومع
 * الوقت يتفرّعان فيصير لكل قارئ إجابة مختلفة عن السؤال نفسه.
 *
 * لا تُرحَّل البيانات: بيانات المشاريع الحالية تجريبية بقرار المالك، والدماغ
 * يُبنى من تفاعلات جديدة لا من سجل اختباري.
 *
 * الرجوع يعيد بناء الجدول فارغًا: البنية تُستعاد، والتاريخ لا. من يحتاج
 * الرجوع فعليًّا يستعيد من نسخة قاعدة البيانات لا من هذه الهجرة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('project_knowledge_sources');
    }

    public function down(): void
    {
        Schema::create('project_knowledge_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('field_key');
            $table->json('value_json')->nullable();
            $table->string('value_hash', 64)->nullable();
            $table->string('event_type')->default('asserted');
            $table->string('source_type');
            $table->string('source_key')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('confidence')->default('medium');
            $table->string('period')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['project_id', 'field_key', 'recorded_at'], 'knowledge_project_field_time');
            $table->index(['source_type', 'source_id']);
        });
    }
};
