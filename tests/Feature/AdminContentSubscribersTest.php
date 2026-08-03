<?php

namespace Tests\Feature;

use App\Models\ContentSubscriber;
use App\Models\User;
use App\Services\Content\ContentSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminContentSubscribersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_export_and_change_subscriber_status(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $subscriber = ContentSubscriber::query()->create([
            'email' => 'reader@example.com',
            'access_token_hash' => hash('sha256', 'token'),
            'consented_at' => now(),
            'subscribed_at' => now(),
        ]);

        $this->actingAs($admin)->get('/admin/content-subscribers')
            ->assertOk()
            ->assertSee('مشتركو المحتوى')
            ->assertSee('reader@example.com');

        $export = $this->actingAs($admin)->get('/admin/content-subscribers/export');
        $export->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('reader@example.com', $export->streamedContent());

        $this->actingAs($admin)
            ->patch('/admin/content-subscribers/'.$subscriber->id.'/status', ['status' => 'disabled'])
            ->assertRedirect();

        $this->assertSame(ContentSubscriber::STATUS_DISABLED, $subscriber->fresh()->status);
    }

    public function test_non_admin_cannot_manage_subscribers(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/content-subscribers')->assertNotFound();
    }

    public function test_disabled_subscriber_cannot_reactivate_through_public_signup(): void
    {
        ContentSubscriber::query()->create([
            'email' => 'blocked@example.com',
            'status' => ContentSubscriber::STATUS_DISABLED,
            'access_token_hash' => hash('sha256', 'old-token'),
            'consented_at' => now(),
            'subscribed_at' => now(),
        ]);

        $this->expectException(ValidationException::class);

        app(ContentSubscriptionService::class)->subscribe('blocked@example.com', true);
    }

    public function test_csv_export_neutralizes_formula_prefixes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        ContentSubscriber::query()->create([
            'email' => '+command@example.com',
            'access_token_hash' => hash('sha256', 'formula-token'),
            'consented_at' => now(),
            'subscribed_at' => now(),
        ]);

        $export = $this->actingAs($admin)->get('/admin/content-subscribers/export');

        $this->assertStringContainsString("'+command@example.com", $export->streamedContent());
    }
}
