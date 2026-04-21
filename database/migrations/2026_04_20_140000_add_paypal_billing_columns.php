<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->decimal('annual_price', 10, 2)->nullable()->after('monthly_price');
            $table->string('paypal_plan_id_monthly')->nullable()->after('features_json');
            $table->string('paypal_plan_id_annual')->nullable()->after('paypal_plan_id_monthly');
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('billing_cycle')->nullable()->after('status');
            $table->string('paypal_subscription_id')->nullable()->unique()->after('billing_cycle');
            $table->foreignId('checkout_plan_id')->nullable()->after('plan_id')->constrained('plans')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('current_period_end');
        });

        Schema::create('paypal_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('event_type');
            $table->string('resource_id')->nullable();
            $table->json('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paypal_webhook_events');

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropForeign(['checkout_plan_id']);
            $table->dropColumn([
                'billing_cycle',
                'paypal_subscription_id',
                'checkout_plan_id',
                'cancelled_at',
            ]);
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn([
                'annual_price',
                'paypal_plan_id_monthly',
                'paypal_plan_id_annual',
            ]);
        });
    }
};
