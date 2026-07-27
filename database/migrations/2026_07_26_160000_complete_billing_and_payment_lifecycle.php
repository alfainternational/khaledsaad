<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->foreignId('scheduled_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->timestamp('scheduled_change_at')->nullable();
            $table->string('source')->default('system');
            $table->unsignedBigInteger('last_payment_id')->nullable();
            $table->timestamp('suspended_at')->nullable();

            $table->foreign('last_payment_id')->references('id')->on('payments')->nullOnDelete();
            $table->index(['status', 'current_period_ends_at']);
        });

        Schema::table('payment_gateways', function (Blueprint $table): void {
            $table->boolean('is_default')->default(false);
            $table->string('health_status')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->string('last_health_message', 500)->nullable();

            $table->index(['is_active', 'is_default', 'sort_order']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('payment_gateway_id')->nullable()->constrained('payment_gateways')->nullOnDelete();
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('customer_reference')->nullable();
            $table->string('evidence_path')->nullable();

            $table->index(['payment_gateway_id', 'status']);
        });

        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider');
            $table->string('external_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('status')->default('pending');
            $table->string('reason')->nullable();
            $table->uuid('idempotency_key')->unique();
            $table->json('meta')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['payment_id', 'status']);
        });

        Schema::create('payment_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_gateway_id')->nullable()->constrained('payment_gateways')->nullOnDelete();
            $table->string('provider');
            $table->string('event_id');
            $table->string('event_type')->nullable();
            $table->string('payload_hash', 64);
            $table->string('status')->default('received');
            $table->string('error', 500)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
        });

        Schema::create('billing_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_audits');
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_refunds');

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_gateway_id');
            $table->dropUnique(['idempotency_key']);
            $table->dropIndex(['payment_gateway_id', 'status']);
            $table->dropColumn([
                'idempotency_key', 'refunded_amount', 'cancelled_at', 'expires_at',
                'customer_reference', 'evidence_path',
            ]);
        });

        Schema::table('payment_gateways', function (Blueprint $table): void {
            $table->dropIndex(['is_active', 'is_default', 'sort_order']);
            $table->dropColumn(['is_default', 'health_status', 'last_health_check_at', 'last_health_message']);
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropForeign(['last_payment_id']);
            $table->dropConstrainedForeignId('scheduled_plan_id');
            $table->dropIndex(['status', 'current_period_ends_at']);
            $table->dropColumn([
                'current_period_starts_at', 'current_period_ends_at', 'cancel_at_period_end',
                'scheduled_change_at', 'source', 'last_payment_id', 'suspended_at',
            ]);
        });
    }
};
