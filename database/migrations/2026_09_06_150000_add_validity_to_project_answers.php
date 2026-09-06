<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * صلاحية الحقيقة (`04-project-facts.md` §3-4).
 *
 * العطل الصامت الذي تسدّه: ميزانيةٌ سُجّلت قبل أربعة أشهر تُستعمل اليوم
 * كأنها الحاضر. لا خطأ يظهر، ولا اختبار يحمرّ — والتشخيص كله يُبنى على
 * رقمٍ لم يعد صحيحًا. والمستخدم لا يعرف أننا نستعمله أصلًا لأننا لم
 * نسأله عنه ثانيةً منذ ذلك اليوم.
 *
 * ولها وجهٌ آخر: انتهاء الصلاحية سببٌ **مشروع** للتواصل. «ميزانيتك
 * مسجّلة من أربعة أشهر — أهي كما هي؟» رسالةٌ تبني قيمة، لا إزعاج.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_answers', function (Blueprint $table): void {
            // آخر مرة أكّد فيها صاحب النشاط هذه الحقيقة صراحةً.
            $table->timestamp('confirmed_at')->nullable()->after('source_run_id');
            // متى تصير مشكوكًا فيها. `null` تعني حقيقةً لا تتقادم (اسم النشاط).
            $table->timestamp('valid_until')->nullable()->after('confirmed_at');

            $table->index(['project_id', 'valid_until']);
        });

        // الإجابات القائمة أُدلي بها فعلًا، فتاريخ إنشائها هو تأكيدها الأول.
        // تركها فارغةً كان سيجعلها كلها «غير مؤكَّدة» فجأة، فيُطلب من كل
        // مستخدم تأكيد كل ما قاله — وهو أسوأ من ألّا نسأل.
        \Illuminate\Support\Facades\DB::table('project_answers')
            ->whereNull('confirmed_at')
            ->update(['confirmed_at' => \Illuminate\Support\Facades\DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('project_answers', function (Blueprint $table): void {
            $table->dropIndex(['project_id', 'valid_until']);
            $table->dropColumn(['confirmed_at', 'valid_until']);
        });
    }
};
