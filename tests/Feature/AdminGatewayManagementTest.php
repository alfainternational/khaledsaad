<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminGatewayManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_configures_tests_activates_and_selects_a_default_gateway_without_disabling_others(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $manual = PaymentGateway::create([
            'provider' => 'manual', 'label' => 'تحويل بنكي', 'mode' => 'live',
            'is_active' => true, 'is_default' => true, 'credentials' => [],
        ]);

        $this->actingAs($admin)->post(route('admin.gateways.store'), [
            'provider' => 'moyasar', 'label' => 'ميسّر', 'mode' => 'test', 'currency' => 'SAR',
            'credentials' => ['secret_key' => 'sk_test_1', 'webhook_secret' => 'hook_1'],
        ])->assertRedirect();

        $moyasar = PaymentGateway::where('provider', 'moyasar')->firstOrFail();
        $this->assertSame('sk_test_1', $moyasar->credential('secret_key'));

        Http::fake(['https://api.moyasar.com/v1/invoices*' => Http::response(['data' => []])]);
        $this->actingAs($admin)->post(route('admin.gateways.test', $moyasar))->assertRedirect();
        $this->assertSame('healthy', $moyasar->fresh()->health_status);

        $this->actingAs($admin)->patch(route('admin.gateways.toggle', $moyasar))->assertRedirect();
        $this->assertTrue($moyasar->fresh()->is_active);
        $this->assertTrue($manual->fresh()->is_active);

        $this->actingAs($admin)->patch(route('admin.gateways.default', $moyasar))->assertRedirect();
        $this->assertTrue($moyasar->fresh()->is_default);
        $this->assertFalse($manual->fresh()->is_default);
    }

    #[Test]
    public function a_gateway_used_by_payments_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $gateway = PaymentGateway::create([
            'provider' => 'manual', 'label' => 'تحويل', 'mode' => 'live',
            'is_active' => true, 'credentials' => [],
        ]);
        $payment = Payment::create([
            'workspace_id' => $admin->primaryWorkspace()->id,
            'user_id' => $admin->id,
            'payment_gateway_id' => $gateway->id,
            'provider' => 'manual', 'purpose' => 'credits', 'amount' => 10,
            'currency' => 'SAR', 'status' => Payment::STATUS_PENDING,
        ]);

        $this->actingAs($admin)->delete(route('admin.gateways.destroy', $gateway))
            ->assertSessionHasErrors('gateway');

        $this->assertDatabaseHas('payment_gateways', ['id' => $gateway->id]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }
}
