<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * العملاء المتوقعون: أشخاص حقيقيون بأسمائهم، لا شخصيات مولّدة.
 *
 * لا عمود هاتف ولا بريد قصدًا. المنصة لا ترسل نيابةً عن أحد، فالرسالة
 * تُنسخ وتُرسل من أداة صاحب المشروع — وحفظ بيانات اتصال طرف ثالث بلا
 * حاجة يخلق مسؤولية بيانات شخصية بلا مقابل. من يحتاج دفتر عناوين يستعمل
 * دفتره.
 *
 * الرسالة لا تُختبر على شخص حقيقي: محاكاة رد إنسان بعينه تُنتج رأيًا
 * مختلقًا منسوبًا إلى مُسمّى، وهذا أسوأ من غياب الاختبار. الاختبار يبقى
 * على الشخصيات وحدها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('organization', 160)->nullable();
            $table->string('role', 120)->nullable();
            $table->string('city', 80)->nullable();
            // ما يعرفه صاحب المشروع عنه فعلًا: مصدر الإشارة الأهم في الرسالة.
            $table->text('notes')->nullable();
            $table->json('interests')->nullable();
            $table->string('temperature', 16)->default('warm');
            $table->string('preferred_channel', 32)->default('whatsapp');
            // الشخصية الأقرب: تعطي النبرة والاعتراض المرجّحين، وتبقى قابلة للتبديل.
            $table->string('persona_key', 64)->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        Schema::create('prospect_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prospect_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 32);
            $table->string('objective', 32);
            $table->text('content');
            $table->text('why')->nullable();
            $table->string('origin', 16)->default('generated');
            $table->string('status', 16)->default('draft');
            // التوليد الجديد لا يمحو السابق: يشير إليه ويبقى الأصل قابلًا للرجوع.
            $table->foreignId('parent_id')->nullable()
                ->constrained('prospect_messages')->nullOnDelete();
            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->json('source_context')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['prospect_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_messages');
        Schema::dropIfExists('prospects');
    }
};
