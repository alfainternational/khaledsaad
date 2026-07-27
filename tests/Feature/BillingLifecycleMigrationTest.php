<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillingLifecycleMigrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function billing_schema_supports_complete_subscription_and_payment_lifecycles(): void
    {
        $this->assertTrue(Schema::hasColumns('subscriptions', [
            'current_period_starts_at',
            'current_period_ends_at',
            'cancel_at_period_end',
            'scheduled_plan_id',
            'scheduled_change_at',
            'source',
            'last_payment_id',
            'suspended_at',
        ]));

        $this->assertTrue(Schema::hasColumns('payment_gateways', [
            'is_default',
            'health_status',
            'last_health_check_at',
            'last_health_message',
        ]));

        $this->assertTrue(Schema::hasColumns('payments', [
            'payment_gateway_id',
            'idempotency_key',
            'refunded_amount',
            'cancelled_at',
            'expires_at',
            'customer_reference',
            'evidence_path',
        ]));

        $this->assertTrue(Schema::hasTable('payment_refunds'));
        $this->assertTrue(Schema::hasTable('payment_webhook_events'));
        $this->assertTrue(Schema::hasTable('billing_audits'));
    }
}
