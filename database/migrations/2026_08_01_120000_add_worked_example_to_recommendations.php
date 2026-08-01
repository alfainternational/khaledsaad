<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المثال التطبيقي: النص الجاهز الذي ينسخه صاحب النشاط ويستعمله كما هو.
 *
 * العقد الغني (action_steps وresources وtimeframe) موجود منذ
 * extend_recommendation_contract، لكن التوصية ظلت تقول «اكتب رسالة تعريف»
 * ولا تُري كيف تبدو الرسالة. الفارق بين «يفهم المسألة» و«يقدر ينفّذها» هو
 * هذا العمود.
 *
 * example_source يفصل مثال النموذج عن مثال الأرضية الحتمية: الأول يُصاغ
 * على بيانات المشروع، والثاني قالب قطاعي مأمون. الفصل ضروري لأن تدرّج
 * الدليل (§٤.١) يختلف بينهما، ولأن جودة المثال تُقاس لاحقًا بمصدره.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recommendations', function (Blueprint $table): void {
            $table->json('worked_example')->nullable()->after('action_steps');
            $table->string('example_source')->nullable()->after('worked_example');
        });
    }

    public function down(): void
    {
        Schema::table('recommendations', function (Blueprint $table): void {
            $table->dropColumn(['worked_example', 'example_source']);
        });
    }
};
