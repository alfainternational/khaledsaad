<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ترجمات المحتوى — طبقة فوق الأصل لا نسخة منه.
 *
 * لماذا جدول منفصل لا صفوف `contents` إضافية؟
 *
 * عمود `contents.locale` يصف **لغة المادة التي كُتبت بها**، وهو الصحيح
 * لمادة تُؤلَّف أصلًا بالإنجليزية. أما هذه فترجمة لمادة عربية لها أصل
 * واحد، والفرق ليس فلسفيًّا: عشرات الاستعلامات في التطبيق تلتقط الدروس
 * بـ`source_key LIKE 'marketing-course-%'` — في `ContentLibraryController`
 * و`LearningPresenter` و`MarketingLessonContextBuilder` و`llms.txt`. صفٌّ
 * ثانٍ لكل درس مترجم يجعل كل واحد من هذه الاستعلامات يُرجع أربعين درسًا
 * بدل عشرين، وترتيب `learning_order` يتكرر، وتقدّم المستخدم ينقسم على
 * صفّين. الطبقة تُبقي الأصل واحدًا: الاستعلامات لا تتغيّر، والترجمة
 * تُركَّب وقت العرض وحده.
 *
 * `source_text_hash` ليس زينة: هو بصمة **النصّ العربي الذي تُرجم منه**.
 * بلاه لا يمكن معرفة أن الأصل عُدّل بعد الترجمة، فتبقى نسخة إنجليزية
 * تصف محتوى لم يعد موجودًا — وهي أسوأ من غياب الترجمة لأنها لا تُعلن
 * عن نفسها. وجوده يجعل التقادم قابلًا للكشف بمقارنة واحدة (§٤.٣).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);

            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body_html');
            $table->json('body_json')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();

            /*
             * بصمة الأصل وقت الترجمة، ومصدرها: الترجمة الآلية والمراجعة
             * البشرية لا تُعامَلان سواءً. `reviewed_by` المملوء يمنع أمر
             * البناء من دهس سطر صحّحه إنسان، كما في `i18n:translate`.
             */
            $table->string('source_text_hash', 64);
            $table->string('translator', 32)->default('ai');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->unique(['content_id', 'locale']);
            $table->index('locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_translations');
    }
};
