<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ما تسأل عنه كل وكالة قبل أن تسعّر، ولم تكن المنصة تسأله.
 *
 * كان المستند يصف حالة المشروع تشخيصيًا، فيصلح لصاحبه ولا يصلح لوكالة تريد
 * أن تبني عليه عرضًا: لا تعرف ما المطلوب منها بالضبط، ولا ما جُرِّب قبلها،
 * ولا من يقرر، ولا ما تملكه من حسابات، ولا كيف يُقسَّم المبلغ.
 *
 * budget_includes_agency_fee هو أهم حقل هنا: الرقم نفسه يعني شيئين مختلفين،
 * وبناء توقعات على المعنى الخاطئ يَعِد بما لا يتحقق.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_profiles', function (Blueprint $table) {
            // null = لم يُحدَّد بعد؛ لا نفترض نيابة عن المستخدم.
            $table->boolean('budget_includes_agency_fee')->nullable()->after('monthly_budget');
            $table->json('agency_services')->nullable()->after('budget_includes_agency_fee');
            $table->json('brief')->nullable()->after('agency_services');
        });
    }

    public function down(): void
    {
        Schema::table('project_profiles', function (Blueprint $table) {
            $table->dropColumn(['budget_includes_agency_fee', 'agency_services', 'brief']);
        });
    }
};
