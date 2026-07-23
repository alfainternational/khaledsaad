<?php

namespace Tests\Feature;

use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Tool;
use App\Models\User;
use App\Services\Tools\PipelineSchemas;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * لوحة الآدمن تدير كل البيانات بنظام CRUD كامل دون لمس الكود.
 */
class AdminCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    #[Test]
    public function admin_can_create_edit_and_delete_a_plan(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.plans.store'), [
            'key' => 'startup', 'name' => 'ناشئة', 'interval' => 'monthly',
            'price' => 79, 'monthly_credits' => 80, 'project_limit' => 5,
            'features' => "ميزة أولى\nميزة ثانية", 'is_public' => '1',
        ])->assertRedirect();

        $plan = Plan::where('key', 'startup')->firstOrFail();
        $this->assertSame(['ميزة أولى', 'ميزة ثانية'], $plan->features);

        $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'key' => 'startup', 'name' => 'ناشئة معدّلة', 'interval' => 'monthly',
            'price' => 89, 'monthly_credits' => 80, 'project_limit' => 5,
        ])->assertRedirect();
        $this->assertSame('ناشئة معدّلة', $plan->fresh()->name);

        $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan))->assertRedirect();
        $this->assertNull(Plan::find($plan->id));
    }

    #[Test]
    public function admin_can_manage_credit_packs(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.packs.store'), [
            'name' => 'حزمة اختبار', 'credits' => 100, 'price' => 90, 'currency' => 'SAR', 'is_active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('credit_packs', ['name' => 'حزمة اختبار', 'credits' => 100]);
    }

    #[Test]
    public function admin_can_add_a_paypal_gateway_with_encrypted_keys(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.gateways.store'), [
            'provider' => 'paypal', 'label' => 'PayPal', 'mode' => 'test',
            'credentials' => ['client_id' => 'CID-123', 'secret' => 'SEC-456'],
        ])->assertRedirect();

        $gateway = PaymentGateway::where('provider', 'paypal')->firstOrFail();
        $this->assertSame('CID-123', $gateway->credential('client_id'));

        // المفاتيح مشفّرة في قاعدة البيانات، لا نصًّا.
        $raw = DB::table('payment_gateways')->where('id', $gateway->id)->value('credentials');
        $this->assertStringNotContainsString('CID-123', $raw);
    }

    #[Test]
    public function activating_one_gateway_deactivates_the_others(): void
    {
        $admin = $this->admin();
        // بوابة manual مفعّلة من البذر.
        $paypal = PaymentGateway::create([
            'provider' => 'paypal', 'label' => 'PayPal', 'mode' => 'test',
            'is_active' => false, 'credentials' => ['client_id' => 'x', 'secret' => 'y'],
        ]);

        $this->actingAs($admin)->patch(route('admin.gateways.toggle', $paypal))->assertRedirect();

        $this->assertTrue($paypal->fresh()->is_active);
        $this->assertFalse(PaymentGateway::where('provider', 'manual')->first()->is_active);
    }

    #[Test]
    public function admin_can_create_a_tool_entirely_from_the_panel(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.tools.store'), [
            'key' => 'panel-tool', 'name' => 'Panel Tool', 'title' => 'أداة من اللوحة',
            'description' => 'أداة أُنشئت بالكامل من لوحة الآدمن دون كود.',
            'category' => 'اختبار', 'sort_order' => 20, 'status' => 'published', 'credit_cost' => 3,
            'output_schema' => json_encode(PipelineSchemas::synthesis()),
            'scoring_rules' => json_encode(['rules' => [['field' => 'q1', 'label' => 'س1', 'type' => 'present', 'weight' => 10]]]),
            'section_plan' => json_encode([['key' => 'main', 'title' => 'رئيسي', 'tier' => 'standard']]),
            'fields' => json_encode([['key' => 'q1', 'label' => 'سؤال', 'type' => 'textarea', 'step' => 1, 'step_title' => 'خطوة', 'required' => true, 'why' => 'سبب']]),
        ])->assertRedirect();

        $tool = Tool::where('key', 'panel-tool')->firstOrFail();
        $this->assertTrue($tool->isRunnable());
        $this->assertSame(1, $tool->currentVersion->fields()->count());
        // برومبتات مبدئية أُنشئت للمراحل.
        $this->assertGreaterThan(0, $tool->currentVersion->prompts()->count());
    }

    #[Test]
    public function invalid_json_in_a_tool_definition_is_rejected(): void
    {
        $this->actingAs($this->admin())->post(route('admin.tools.store'), [
            'key' => 'bad-tool', 'name' => 'Bad', 'title' => 'سيئة',
            'description' => 'وصف كافٍ للاختبار هنا.',
            'category' => 'اختبار', 'status' => 'coming_soon', 'credit_cost' => 1,
            'output_schema' => '{ليس JSON صالح',
            'scoring_rules' => json_encode(['rules' => []]),
            'section_plan' => json_encode([]),
            'fields' => json_encode([]),
        ])->assertSessionHasErrors('output_schema');

        $this->assertNull(Tool::where('key', 'bad-tool')->first());
    }

    #[Test]
    public function admin_can_edit_a_mail_setting_that_applies_at_runtime(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'mail_from_name' => 'منصة خالد سعد',
        ])->assertRedirect();

        $this->assertSame('منصة خالد سعد', Setting::get('mail_from_name'));
    }
}
