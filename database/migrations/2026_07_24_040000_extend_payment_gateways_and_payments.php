<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ما تحتاجه بوابة دفع حقيقية ولم يكن موجودًا:
 *
 * - عملة البوابة ومعامل التحويل: أسعارنا بالريال، وPayPal لا يقبل SAR.
 *   فنحتفظ بالسعر الأساسي كما هو ونحوّله لعملة البوابة وقت الدفع.
 * - المبلغ المحصَّل فعلًا وعملته: ما جرى تحصيله لدى البوابة، لا ما عرضناه.
 * - معرّف الالتقاط: مرجع العملية لدى المزوّد للمطابقة والاسترداد لاحقًا.
 * - الاعتماد اليدوي: التحويل البنكي لا يُصدَّق إلا بتأكيد آدمن.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->after('mode');
            $table->decimal('fx_rate', 12, 6)->default(1)->after('currency');
            $table->text('instructions')->nullable()->after('fx_rate');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('charged_amount', 12, 2)->nullable()->after('currency');
            $table->string('charged_currency', 3)->nullable()->after('charged_amount');
            $table->string('external_capture_id')->nullable()->after('external_id');
            $table->string('failure_reason')->nullable()->after('status');
            $table->foreignId('approved_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'charged_amount', 'charged_currency', 'external_capture_id',
                'failure_reason', 'approved_at',
            ]);
        });

        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->dropColumn(['currency', 'fx_rate', 'instructions']);
        });
    }
};
