<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إعدادات المنصة القابلة للتحرير من لوحة الآدمن دون لمس الكود:
 * - settings: مفتاح/قيمة عام (بريد، هوية، سياسات).
 * - payment_gateways: بوابات الدفع بمفاتيحها المشفّرة.
 * - credit_packs: حزم أرصدة قابلة للشراء.
 * - payments: سجل عمليات الدفع وربطها بالأرصدة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group')->default('general');
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->string('type')->default('string'); // string|bool|int|json|secret
            $table->timestamps();
        });

        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique(); // paypal|moyasar|tap|manual
            $table->string('label');
            $table->string('mode')->default('test'); // test|live
            $table->boolean('is_active')->default(false);
            // بيانات الاعتماد مشفّرة في العمود عبر cast encrypted.
            $table->longText('credentials')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('credit_packs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('credits');
            $table->unsignedInteger('price'); // بالعملة الأساسية
            $table->string('currency', 3)->default('SAR');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');
            $table->string('purpose'); // credit_pack|plan
            $table->foreignId('credit_pack_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('SAR');
            $table->unsignedInteger('credits_granted')->default(0);
            $table->string('status')->default('pending'); // pending|paid|failed|cancelled
            $table->string('external_id')->nullable(); // معرّف العملية لدى البوابة
            $table->json('meta')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('credit_packs');
        Schema::dropIfExists('payment_gateways');
        Schema::dropIfExists('settings');
    }
};
