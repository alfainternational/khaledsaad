<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سقف التكلفة التشغيلي (§٩).
 *
 * لماذا جدولان لا عمود على `workspaces`؟ لأن السقف شهري: عمود واحد يعني
 * محو تاريخ الشهر الماضي كل شهر، فلا يبقى ما يُراجَع حين يشتكي أحدهم من
 * استهلاك لا يعرف مصدره.
 *
 * ولماذا منفصل عن `ai_usage_records`؟ لأن ذاك يسجّل **بعد** الاستدعاء، وهذا
 * يحجز **قبله**. سجل ما مضى لا يمنع شيئًا، وقد كان هو كل ما تملكه المنصة.
 *
 * وهو منفصل تمامًا عن `credit_wallets`: الرصيد التجاري ما اشتراه العميل،
 * والسقف هنا حماية تشغيلية لنا من فاتورة مزوّد. خلطهما يجعل عميلًا دافعًا
 * يُمنع لأننا اقتربنا من حدّنا، أو يجعل حدَّنا يُخترق لأنه دفع.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('query_budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            // «2026-07» — الشهر الميلادي كما يُفوتر المزوّد.
            $table->string('period', 7);

            $table->unsignedInteger('monthly_limit');

            /*
             * المحجوز يشمل ما لم يُستدعَ بعد. الفرق بينه وبين المستهلك هو ما
             * ينتظر في الطابور، وهو ما يجعل الحدّ صادقًا تحت التزامن: عشرة
             * Jobs تُحجز عشرة مواضع قبل أن يُستدعى أولها.
             */
            $table->unsignedInteger('reserved')->default(0);
            $table->unsignedInteger('consumed')->default(0);
            $table->decimal('cost_usd', 12, 6)->default(0);
            $table->timestamp('warned_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'period']);
        });

        Schema::table('workspaces', function (Blueprint $table): void {
            /*
             * null = خذ الافتراضي من الإعدادات. عمودٌ بقيمة افتراضية ثابتة كان
             * سيجمّد سقف كل المساحات القائمة على رقم اليوم، فلا يتحرّك حين
             * يتغيّر الافتراضي.
             */
            $table->unsignedInteger('monthly_query_limit')->nullable()->after('type');
        });

        Schema::create('query_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('query_budget_id')->constrained()->cascadeOnDelete();

            /*
             * الحصة الداخلية: الميزانية على المساحة (§٩)، لكن المشروع يُسجَّل
             * على كل حجز حتى يمكن الإجابة على «أي نشاط استهلك حصتي؟» — وهو
             * السؤال الأول لأي وكالة تدير عشرة عملاء بميزانية واحدة.
             */
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();

            $table->string('purpose');
            $table->unsignedInteger('queries');
            $table->string('status')->default('held');
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['query_budget_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('query_reservations');
        Schema::dropIfExists('query_budgets');

        Schema::table('workspaces', function (Blueprint $table): void {
            $table->dropColumn('monthly_query_limit');
        });
    }
};
