<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تصنيف الدليل على مخرجات الجمهور والاستوديو (§٤.١).
 *
 * لوحة الشخصيات ورد الشخصية على رسالة كلاهما `inferred` بطبيعته: لا أحد
 * من هؤلاء اشترى شيئًا. الخطر ليس أنها فرضية بل أن تُقرأ كقياس، فتُبنى
 * ميزانية إعلان على «٧٤/١٠٠» كأنها نتيجة اختبار حقيقي على جمهور حقيقي.
 *
 * العمود يُخزَّن ولا يُشتق عند العرض: التصنيف يُحفظ مع الحقيقة ومصدرها،
 * فيبقى صحيحًا لو تغيّرت طريقة التوليد لاحقًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('persona_panels', function (Blueprint $table): void {
            $table->string('evidence_level', 16)->default('inferred')->after('source');
        });

        Schema::table('message_test_results', function (Blueprint $table): void {
            $table->string('evidence_level', 16)->default('inferred')->after('score');
        });

        Schema::table('persona_tests', function (Blueprint $table): void {
            $table->string('evidence_level', 16)->default('inferred')->after('results');
        });
    }

    public function down(): void
    {
        Schema::table('persona_tests', fn (Blueprint $table) => $table->dropColumn('evidence_level'));
        Schema::table('message_test_results', fn (Blueprint $table) => $table->dropColumn('evidence_level'));
        Schema::table('persona_panels', fn (Blueprint $table) => $table->dropColumn('evidence_level'));
    }
};
