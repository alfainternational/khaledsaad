<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مفتاح تكرار لكل حركة رصيد (INV-9).
 *
 * العطل الذي يسدّه: `hold()` كان يخصم في كل نداء. ضغطةٌ مزدوجة، أو إعادة
 * إرسال نموذج، أو إعادة محاولة من الطابور، أو إشعار بوابة دفع يصل مرتين —
 * كلها كانت تخصم أو تمنح مرتين. الرصيد ينقص بلا سبب ظاهر، ولا يكشفه
 * اختبارٌ ولا سجلّ، لأن كل حركة على حدة صحيحة تمامًا.
 *
 * القيد فريد على مستوى الجدول لا على مستوى التطبيق: الحماية يجب أن تكون
 * في قاعدة البيانات كي تصمد أمام طلبين متزامنين في عمليتين مختلفتين.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table): void {
            // يقبل NULL للحركات التاريخية؛ وMySQL لا يعدّ NULL تكرارًا.
            $table->string('idempotency_key', 191)->nullable()->after('reason');
            $table->unique('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
