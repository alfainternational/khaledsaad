<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجل محاولات التشغيل + حالة الانتظار.
 *
 * `awaiting_capacity` ليست تجميلًا لـ`failed`: هي حالة **قابلة للاستئناف
 * تلقائيًّا**. الفرق أن الأولى تعيد التشغيل من نفسها حين تعود القدرة،
 * والثانية تنتظر أن يضغط المستخدم زرًّا لا يعرف أنه موجود. ومجهود ستين
 * إجابة أثمن من أن يُعلَّق على تذكّر أحدهم.
 *
 * وسجل المحاولات هو ما يجعل السؤال «لماذا تأخّر هذا التقرير؟» قابلًا
 * للإجابة بعد وقوعه — لا في اللحظة وحدها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_run_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tool_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt');
            $table->string('provider')->nullable();
            $table->string('status', 32);
            $table->string('failure_kind', 16)->nullable();
            $table->string('error_class')->nullable();
            $table->text('error_detail')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tool_run_id', 'attempt']);
        });

        Schema::table('tool_runs', function (Blueprint $table): void {
            // متى تُعاد المحاولة تلقائيًّا. `null` يعني لا إعادة مجدولة.
            $table->timestamp('retry_after')->nullable()->after('failure_detail');
            $table->unsignedSmallInteger('auto_attempts')->default(0)->after('retry_after');

            $table->index(['status', 'retry_after']);
        });
    }

    public function down(): void
    {
        Schema::table('tool_runs', function (Blueprint $table): void {
            $table->dropIndex(['status', 'retry_after']);
            $table->dropColumn(['retry_after', 'auto_attempts']);
        });

        Schema::dropIfExists('tool_run_attempts');
    }
};
