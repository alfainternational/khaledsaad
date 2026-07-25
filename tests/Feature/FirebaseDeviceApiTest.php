<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FirebaseDeviceApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_signed_in_user_can_register_refresh_and_remove_a_firebase_device(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.devices.store'), [
            'token' => 'firebase-device-token-1',
            'platform' => 'android',
            'device_name' => 'هاتف الاختبار',
        ])->assertCreated()->assertJsonPath('data.platform', 'android');

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token_hash' => hash('sha256', 'firebase-device-token-1'),
            'platform' => 'android',
        ]);
        $this->assertDatabaseMissing('device_tokens', ['token' => 'firebase-device-token-1']);

        $this->postJson(route('api.v1.devices.store'), [
            'token' => 'firebase-device-token-1',
            'platform' => 'android',
            'device_name' => 'هاتف محدّث',
        ])->assertOk()->assertJsonPath('data.device_name', 'هاتف محدّث');

        $this->deleteJson(route('api.v1.devices.destroy'), [
            'token' => 'firebase-device-token-1',
        ])->assertOk();

        $this->assertDatabaseCount('device_tokens', 0);
    }

    #[Test]
    public function device_registration_requires_authentication_and_valid_input(): void
    {
        $this->postJson(route('api.v1.devices.store'))->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.devices.store'), [
            'token' => 'short',
            'platform' => 'unsupported',
        ])->assertUnprocessable()->assertJsonValidationErrors(['token', 'platform']);
    }
}
