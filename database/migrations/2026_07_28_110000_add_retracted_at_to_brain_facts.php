<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سحب الحقيقة: أن يقول المستخدم «لا أعرف» عمّا أجاب عنه سابقًا.
 *
 * لا يُحذف الصف. الفرق بين «لم يُسأل قط» و«أجاب ثم تراجع» معلومة حقيقية عن
 * نضج النشاط، ومحوها يجعل الدماغ ينسى ما تعلّمه.
 *
 * لماذا عمود لا صف جديد بقيمة فارغة: القيمة الفارغة تلتبس بـ«لا حقيقة»، فيصير
 * على كل قارئ أن يميّز بينهما بنفسه. العمود يجعل السريان قابلًا للاستعلام:
 * الحقيقة السارية = لم تُستبدل ولم تُسحب.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brain_facts', function (Blueprint $table): void {
            $table->timestamp('retracted_at')->nullable()->after('superseded_by');
            $table->string('retracted_by_module')->nullable()->after('retracted_at');
        });
    }

    public function down(): void
    {
        Schema::table('brain_facts', function (Blueprint $table): void {
            $table->dropColumn(['retracted_at', 'retracted_by_module']);
        });
    }
};
