<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ذكاء المدخلات: ما نقدّمه للمستخدم قبل أن يجيب، وما نقيسه في إجابته بعدها.
 *
 * جدولان لأن الاتجاهين مختلفان تمامًا:
 *
 *   - `question_assists` مخرج نموذج لغوي **قبل** الإجابة: دليل يشرح السؤال بلغة
 *     نشاط المستخدم، ومقترحات ملموسة، وترشيح لأفضل خيار متاح. تكلفته حقيقية
 *     فيُخزَّن ويُعاد استعماله ما لم يتغيّر السياق الذي بُني عليه.
 *
 *   - `answer_fitness` قياس حتميّ **بعد** الإجابة: هل ما كتبه يكفي فعلًا؟
 *     «الجميع» و«رجال أعمال من ٣٠ إلى ٤٥ في الرياض يشترون لشركاتهم» كلاهما نصٌّ
 *     غير فارغ، وكان `presentFactor` يمنحهما الدرجة نفسها. الفرق بينهما هو ما
 *     يقيسه هذا الجدول، ومنه تقرأ `Diagnosis` بلا اتصال شبكي.
 *
 * كلاهما `inferred` ولا يبلغ غيرها: مصدر الأول نموذج لغوي، ومصدر الثاني حكم
 * منهجي على نصّ كتبه صاحب النشاط عن نفسه (§٤.١، §١٥).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_assists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('query_reservation_id')->nullable()->constrained()->nullOnDelete();

            /*
             * السطح يفصل سؤال الاستشارة عن حقل الأداة: المفتاح قد يتكرر بين
             * السطحين بمعنى مختلف، ودمجهما يجعل دليل أحدهما يظهر في الآخر.
             */
            $table->string('surface', 32);
            $table->string('question_key');

            /*
             * بصمة السياق الذي بُني عليه المخرج: نوع النشاط، وما نعرفه من
             * الدماغ، ونصّ السؤال وخياراته. تغيّرها يعني أن الدليل صار قديمًا
             * ويجب إعادة توليده؛ ثباتها يعني إعادة استعمال بلا تكلفة.
             *
             * بلا هذه البصمة كان الاختيار بين استدعاء النموذج عند كل عرض للسؤال
             * (تكلفة تتضاعف بلا فائدة) أو تجميد أول مخرج للأبد (دليل يتحدث عن
             * نشاط لم يعد قائمًا).
             */
            $table->string('context_hash', 64);

            $table->text('guide');
            $table->json('suggestions');

            /*
             * أفضل خيار متاح — لأسئلة الاختيار وحدها. النموذج لا يخترع خيارًا
             * جديدًا: قيم الخيارات مربوطة بخرائط نقاط في حساب المحاور، وخيار
             * مُختلق يعطي صفرًا صامتًا لمن اختاره.
             */
            $table->string('recommended_value')->nullable();
            $table->text('recommendation_reason')->nullable();

            // على أي معلومة بُني هذا: يُعرض للمستخدم، فالمقترح بلا أساس معلن دعوى.
            $table->json('basis')->nullable();

            $table->string('evidence_level', 16)->default('inferred');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['project_id', 'surface', 'question_key'], 'question_assist_unique');
        });

        Schema::create('answer_fitness', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            /*
             * المفتاح هو مفتاح الحقيقة في الدماغ (`field_key`) لا مفتاح السؤال:
             * السؤال الواحد قد يُسأل من أداتين ومن الاستشارة، والمقيس هو
             * المعلومة لا موضع طرحها. وعليه تقرأ `Diagnosis` بمفتاح المحور
             * نفسه بلا ترجمة وسيطة.
             */
            $table->string('field_key');

            $table->unsignedTinyInteger('score');
            $table->string('verdict', 32);

            // ما ينقص هذه الإجابة بالاسم، لتُعرض في التقرير وفي قائمة الإصلاح.
            $table->json('gaps')->nullable();
            // كيف حُسبت الدرجة: بند بند. رقمٌ لا يُعرف كيف حُسب لا يُعرض (§١٥).
            $table->json('basis')->nullable();

            /*
             * بصمة القيمة المقيسة: تمنع إعادة الحساب على نصّ لم يتغيّر، وتكشف
             * درجةً بقيت من إجابة قديمة بعد أن صحّح المستخدم إجابته.
             */
            $table->string('value_fingerprint', 64);

            // deterministic | assist — من قاس. الحتميّ وحده يدخل حساب الدرجة.
            $table->string('source', 32)->default('deterministic');
            $table->string('evidence_level', 16)->default('inferred');
            $table->timestamp('scored_at');
            $table->timestamps();

            $table->unique(['project_id', 'field_key'], 'answer_fitness_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answer_fitness');
        Schema::dropIfExists('question_assists');
    }
};
