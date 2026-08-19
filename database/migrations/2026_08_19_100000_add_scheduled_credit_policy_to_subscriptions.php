<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سياسة الرصيد المرافقة للتغيير المؤجَّل.
 *
 * التغيير المجدول كان يحفظ الخطة الهدف وحدها، فيصل موعده بلا ذاكرة عمّا
 * قرره الآدمن للرصيد. تُحفظ هنا حتى يطبّقها `subscriptions:apply-scheduled`
 * كما اختيرت، فلا تصل الباقة بلا مزاياها ولا تُمنح مرتين.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('scheduled_credit_policy', 20)->nullable()->after('scheduled_change_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn('scheduled_credit_policy');
        });
    }
};
