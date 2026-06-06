<?php

namespace Tests\Feature\App;

use App\Domain\Intelligence\Models\DiagnosisCase;
use App\Domain\Project\Models\Project;
use App\Jobs\RunGuestDiagnosisJob;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GuestDiagnosisFunnelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_can_open_the_diagnosis_form(): void
    {
        $this->get(route('diagnose.form'))->assertOk();
    }

    #[Test]
    public function starting_a_diagnosis_creates_a_case_and_runs_analysis_inline(): void
    {
        Http::fake(['*' => Http::response($this->sampleHtml(), 200)]);

        $response = $this->post(route('diagnose.start'), [
            'case_type' => 'website',
            'input_url' => 'example.com',
            'goal' => 'leads',
            'competitor' => 'competitor.test',
        ]);

        $case = DiagnosisCase::query()->firstOrFail();
        $response->assertRedirectToRoute('diagnose.show', $case);
        $this->assertSame('https://example.com', $case->input_url);
        $this->assertSame('ready', $case->status); // ran inline (no worker needed)
        $this->assertNotNull($case->expires_at);
        $response->assertSessionHas('diagnosis_public_id', $case->public_id);
    }

    #[Test]
    public function analysis_job_produces_a_ready_partial_report_in_house(): void
    {
        Http::fake(['*' => Http::response($this->sampleHtml(), 200)]);

        $case = DiagnosisCase::query()->create([
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'input_url' => 'https://example.com',
            'case_type' => 'website',
            'sector' => 'general',
            'status' => 'queued',
            'expires_at' => now()->addDays(7),
        ]);

        (new RunGuestDiagnosisJob($case->id))->handle(app(\App\Support\Intelligence\GuestDiagnosisService::class));

        $case->refresh();
        $this->assertSame('ready', $case->status);
        $this->assertNotNull($case->executive_score);
        $this->assertIsArray($case->report_json);
        $this->assertArrayHasKey('partial', $case->report_json);

        $this->getJson(route('diagnose.status', $case))
            ->assertOk()
            ->assertJson(['status' => 'ready', 'ready' => true]);
    }

    #[Test]
    public function ready_case_requires_email_before_showing_the_result(): void
    {
        $case = $this->readyCase();

        // Before email: the show page asks for email (no locked CTA yet).
        $this->get(route('diagnose.show', $case))
            ->assertOk()
            ->assertSee('أدخل بريدك', false);

        // Capture email, then the partial result is revealed.
        $this->post(route('diagnose.email', $case), ['email' => 'lead@example.com'])
            ->assertRedirectToRoute('diagnose.show', $case);

        $case->refresh();
        $this->assertSame('lead@example.com', $case->email);

        $this->get(route('diagnose.show', $case))
            ->assertOk()
            ->assertSee('القراءة الأولية', false);
    }

    #[Test]
    public function expired_case_shows_expiry_screen_not_the_result(): void
    {
        $case = $this->readyCase();
        $case->update(['expires_at' => now()->subDay()]);

        $this->get(route('diagnose.show', $case))
            ->assertOk()
            ->assertSee('انتهت صلاحية', false);
    }

    #[Test]
    public function registration_converts_the_diagnosis_into_a_project(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        Queue::fake();

        $case = $this->readyCase();
        $case->update(['input_url' => 'https://example.com', 'business_name' => 'My Brand']);

        $this->withSession(['diagnosis_public_id' => $case->public_id])
            ->post(route('register.store'), [
                'name' => 'New Owner',
                'email' => 'owner@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertRedirect();

        $case->refresh();
        $this->assertSame('converted', $case->status);
        $this->assertNotNull($case->converted_project_id);

        $project = Project::query()->findOrFail($case->converted_project_id);
        $this->assertSame('https://example.com', $project->primary_domain);
        $this->assertSame('My Brand', $project->name);
    }

    private function readyCase(): DiagnosisCase
    {
        return DiagnosisCase::query()->create([
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'input_url' => 'https://example.com',
            'case_type' => 'website',
            'sector' => 'general',
            'status' => 'ready',
            'executive_score' => 62,
            'integrity_status' => 'partial',
            'report_json' => [
                'report' => ['executive_scores' => ['executive' => 62]],
                'partial' => [
                    'executive_score' => 62,
                    'integrity_status' => 'partial',
                    'top_problems' => [['title' => 'العرض غير واضح', 'area' => 'conversion', 'severity' => 'high']],
                    'immediate_opportunity' => 'وضّح جملتك التعريفية في الأعلى.',
                    'competitor_comparison' => null,
                    'locked_problems_count' => 4,
                ],
            ],
            'expires_at' => now()->addDays(7),
        ]);
    }

    private function sampleHtml(): string
    {
        return <<<'HTML'
        <!doctype html><html lang="ar"><head>
        <title>متجر تجريبي</title>
        <meta name="description" content="نبيع منتجات رائعة">
        <meta name="viewport" content="width=device-width">
        </head><body>
        <h1>أهلاً بك</h1>
        <a href="/contact">تواصل معنا</a>
        <a href="/services">خدماتنا</a>
        <p>احجز الآن واطلب عرض سعر</p>
        </body></html>
        HTML;
    }
}
