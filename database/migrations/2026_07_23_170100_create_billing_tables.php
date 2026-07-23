<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الفوترة والأرصدة.
 *
 * الأرصدة هي وحدة الاستهلاك: كل تشغيل أداة يحجز رصيدًا ثم يخصمه عند النجاح.
 * BR-011: الفشل التقني لا يستهلك رصيدًا نهائيًا — الحجز يُلغى.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('interval')->default('monthly');
            $table->unsignedInteger('price')->default(0);
            $table->unsignedInteger('monthly_credits')->default(0);
            $table->unsignedSmallInteger('project_limit')->default(1);
            $table->json('features')->nullable();
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->string('status')->default('active');
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->integer('balance')->default(0);
            $table->timestamps();
        });

        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tool_run_id')->nullable()->constrained()->nullOnDelete();
            // hold = حجز | charge = خصم نهائي | refund = استرداد | grant = منحة/تجديد
            $table->string('type');
            $table->integer('amount');
            $table->integer('balance_after');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['credit_wallet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
        Schema::dropIfExists('credit_wallets');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
