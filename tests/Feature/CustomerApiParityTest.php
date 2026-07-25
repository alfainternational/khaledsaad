<?php

namespace Tests\Feature;

use App\Models\CreditPack;
use App\Models\GeoPack;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use App\Services\Billing\SubscriptionManager;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerApiParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function a_customer_can_mark_every_notification_as_read(): void
    {
        $user = User::factory()->create();
        DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'product.test',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['title' => 'اختبار'],
        ]);
        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('data.unread', 0);

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    #[Test]
    public function checkout_cancellation_is_owner_only_and_returns_the_final_state(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $pack = CreditPack::active()->firstOrFail();
        $payment = Payment::create([
            'workspace_id' => $owner->primaryWorkspace()->id,
            'user_id' => $owner->id,
            'provider' => 'manual',
            'purpose' => 'credit_pack',
            'credit_pack_id' => $pack->id,
            'amount' => $pack->price,
            'currency' => $pack->currency,
            'credits_granted' => $pack->credits,
            'status' => Payment::STATUS_PENDING,
        ]);

        Sanctum::actingAs($stranger);
        $this->postJson(route('api.v1.checkout.cancel', $payment))->assertNotFound();

        Sanctum::actingAs($owner);
        $this->postJson(route('api.v1.checkout.cancel', $payment))
            ->assertOk()
            ->assertJsonPath('data.cancelled', true)
            ->assertJsonPath('data.status', Payment::STATUS_CANCELLED);
    }

    #[Test]
    public function a_customer_can_download_the_same_llms_file_as_the_web_surface(): void
    {
        $user = User::factory()->create();
        app(SubscriptionManager::class)->subscribe(
            $user->primaryWorkspace(),
            Plan::where('key', 'professional')->firstOrFail(),
        );
        $project = Project::create([
            'workspace_id' => $user->primaryWorkspace()->id,
            'name' => 'مشروع ملف الآلات',
            'slug' => 'machine-readable-project',
            'industry' => 'تقنية',
            'stage' => 'growth',
            'status' => 'active',
        ]);
        GeoPack::create([
            'project_id' => $project->id,
            'facts' => [],
            'faq' => [],
            'jsonld' => [],
            'llms_txt' => "# {$project->name}\nملف قابل للقراءة الآلية.",
            'source' => 'rules',
            'generated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->get(route('api.v1.geo.llms', $project))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSeeText($project->name);
    }
}
