<?php

namespace Tests\Feature\Marketing;

use App\Domain\Account\Models\Account;
use App\Domain\Client\Models\Client;
use App\Domain\Marketing\Models\ContactMessage;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContactIntakeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function public_consultation_intake_is_stored_as_structured_lead(): void
    {
        $response = $this->post(route('contact.store'), [
            'message_type' => ContactMessage::TYPE_CONSULTATION,
            'name' => 'أحمد',
            'email' => 'ahmed@example.com',
            'phone' => '+966500000000',
            'company_name' => 'شركة النمو',
            'business_summary' => 'نقدم خدمات تسويق للمشاريع القائمة.',
            'offer' => 'تشخيص وخطة ومحتوى',
            'market' => 'السعودية',
            'ideal_customer' => 'أصحاب مشاريع قائمة',
            'pain_points' => 'ضعف وضوح الرسالة والتحويل',
            'primary_goal' => 'رفع جودة العملاء المحتملين',
            'success_metric' => 'عدد الاستفسارات المؤهلة',
            'timeframe' => '90 يوم',
            'current_channels' => ['إنستغرام', 'إعلانات Meta'],
            'current_state' => 'يوجد حضور متقطع بلا نظام واضح.',
            'priority' => 'إعادة بناء العرض والرسائل',
            'budget_range' => '5,000 - 15,000 ريال',
            'services' => ['تشخيص المشروع', 'الخطة التسويقية'],
            'additional_context' => 'نحتاج رؤية تنفيذية قبل التوسع.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $message = ContactMessage::query()->firstOrFail();

        $this->assertSame(ContactMessage::TYPE_CONSULTATION, $message->message_type);
        $this->assertSame(ContactMessage::STATUS_NEW, $message->status);
        $this->assertSame('طلب استشارة مشروع: شركة النمو', $message->subject);
        $this->assertSame('شركة النمو', data_get($message->payload, 'contact.company_name'));
        $this->assertSame('رفع جودة العملاء المحتملين', data_get($message->payload, 'goals.primary_goal'));
        $this->assertSame(['إنستغرام', 'إعلانات Meta'], data_get($message->payload, 'current_marketing.channels'));
    }

    #[Test]
    public function admin_can_convert_consultation_lead_into_client_project_and_brief(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $admin = User::query()->where('is_super_admin', true)->firstOrFail();
        $owner = User::factory()->create();

        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Growth Account',
            'billing_email' => $owner->email,
            'status' => 'active',
        ]);

        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Growth Workspace',
            'type' => 'agency',
            'status' => 'active',
        ]);

        $message = ContactMessage::query()->create([
            'name' => 'أحمد',
            'email' => 'ahmed@example.com',
            'phone' => '+966500000000',
            'subject' => 'طلب استشارة مشروع: شركة النمو',
            'body' => 'ملخص أولي للمشروع',
            'message_type' => ContactMessage::TYPE_CONSULTATION,
            'source' => 'website',
            'payload' => [
                'contact' => [
                    'company_name' => 'شركة النمو',
                ],
                'business' => [
                    'summary' => 'نقدم خدمات تسويق للمشاريع القائمة.',
                    'offer' => 'تشخيص وخطة ومحتوى',
                    'market' => 'السعودية',
                ],
                'audience' => [
                    'ideal_customer' => 'أصحاب مشاريع قائمة',
                    'pain_points' => 'ضعف وضوح الرسالة والتحويل',
                ],
                'goals' => [
                    'primary_goal' => 'رفع جودة العملاء المحتملين',
                    'success_metric' => 'عدد الاستفسارات المؤهلة',
                ],
                'current_marketing' => [
                    'channels' => ['إنستغرام', 'إعلانات Meta'],
                ],
                'execution' => [
                    'priority' => 'إعادة بناء العرض والرسائل',
                ],
                'commercial' => [
                    'budget_range' => '5,000 - 15,000 ريال',
                ],
                'services' => ['تشخيص المشروع', 'الخطة التسويقية'],
                'notes' => [
                    'additional_context' => 'نحتاج رؤية تنفيذية قبل التوسع.',
                ],
            ],
            'status' => ContactMessage::STATUS_NEW,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.contact-messages.convert', $message), [
                'workspace_id' => $workspace->id,
                'client_name' => 'شركة النمو',
                'project_name' => 'مشروع النمو الاستراتيجي',
                'project_stage' => 3,
            ])
            ->assertRedirect(route('admin.contact-messages.show', $message));

        $client = Client::query()->where('workspace_id', $workspace->id)->firstOrFail();
        $project = Project::query()->where('workspace_id', $workspace->id)->firstOrFail();

        $this->assertSame('شركة النمو', $client->name);
        $this->assertSame('مشروع النمو الاستراتيجي', $project->name);

        $message->refresh();

        $this->assertSame(ContactMessage::STATUS_CONVERTED, $message->status);
        $this->assertSame($workspace->id, $message->converted_workspace_id);
        $this->assertSame($project->id, $message->converted_project_id);

        $this->assertDatabaseHas('workspace_data', [
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'key' => 'project.marketing_brief',
        ]);
    }
}
