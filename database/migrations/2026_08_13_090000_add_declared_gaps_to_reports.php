<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الفجوات المعلنة تُحفظ ببنيتها لا كجُمل داخل `assumptions`.
 *
 * كان النقص يُسطَّح إلى نصّ: «ناقص نعرفه عنك: {الحقل} — {السبب}». والنصّ
 * لا يُفتح منه باب: لا يُعرف أي سؤال يقصد، ولا يمكن معرفة هل سُدّ لاحقًا،
 * ولا تُحسب نسبة اكتمال. العمود يحفظ المفتاح والتسمية والمصدر، فيصير كل
 * سطر في القائمة زرًّا يفتح سؤاله.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->json('declared_gaps')->nullable()->after('assumptions');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropColumn('declared_gaps');
        });
    }
};
