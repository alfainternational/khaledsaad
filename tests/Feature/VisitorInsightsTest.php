<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Insights\ClientProfile;
use App\Modules\Insights\InsightsReport;
use App\Modules\Insights\InsightsRollup;
use App\Modules\Insights\LocationInference;
use App\Modules\Insights\Models\VisitorDailyStat;
use App\Modules\Insights\Models\VisitorEvent;
use App\Modules\Insights\Models\VisitorPageView;
use App\Modules\Insights\Models\VisitorSession;
use App\Modules\Insights\TrafficOrigin;
use App\Modules\Insights\VisitorIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * إحصاءات الزوّار: القياس الداخلي بالكامل.
 *
 * كل اختبار هنا يحرس تعريفًا لا سطر كود. «مدة البقاء» و«الارتداد»
 * و«المصدر» تعريفات تنجرف بصمت مع أول تعديل، فينقلب معناها بلا أن
 * يفشل شيء — والرقم المنقلب أسوأ من الرقم الغائب.
 */
class VisitorInsightsTest extends TestCase
{
    use RefreshDatabase;

    /* ---------------------------------------------------------------
     * الالتقاط من الخادم
     * --------------------------------------------------------------- */

    #[Test]
    public function a_page_visit_is_recorded_from_the_server_without_javascript(): void
    {
        $this->get('/')->assertOk();

        $session = VisitorSession::firstOrFail();

        $this->assertSame('/', $session->entry_path);
        $this->assertSame(1, $session->page_views_count);
        $this->assertSame('direct', $session->channel);
        $this->assertFalse($session->is_bot);
        $this->assertNotNull($session->ip_hash);
        $this->assertSame(1, VisitorPageView::count());
    }

    #[Test]
    public function the_raw_ip_address_is_never_stored(): void
    {
        $this->get('/');

        $session = VisitorSession::firstOrFail();

        // البصمة مُجزَّأة بمفتاح التطبيق: طولها ثابت ولا تحوي العنوان.
        $this->assertSame(64, strlen((string) $session->ip_hash));
        $this->assertStringNotContainsString('127.0.0.1', (string) $session->ip_hash);
    }

    #[Test]
    public function the_beacon_endpoint_is_not_recorded_as_a_page_visit(): void
    {
        $this->post(route('insights.collect'), ['visit' => 'nope'])->assertNoContent();

        $this->assertSame(0, VisitorPageView::where('path', '/_insights/collect')->count());
    }

    #[Test]
    public function a_second_page_continues_the_same_visit_and_orders_the_journey(): void
    {
        $this->continue($this->get('/'))->get('/services');

        $session = VisitorSession::firstOrFail();

        $this->assertSame(1, VisitorSession::count(), 'الصفحة الثانية بدأت زيارة جديدة بدل أن تُكمل الأولى.');
        $this->assertSame(2, $session->page_views_count);
        $this->assertSame('/services', $session->exit_path);

        $views = $session->pageViews()->orderBy('sequence')->get();
        $this->assertTrue($views[0]->is_entry);
        $this->assertFalse($views[0]->is_exit);
        $this->assertTrue($views[1]->is_exit, 'صفحة الخروج يجب أن تكون الأخيرة وحدها.');
    }

    #[Test]
    public function post_requests_are_not_counted_as_page_views(): void
    {
        $this->continue($this->get('/'))->post('/logout');

        $this->assertSame(1, VisitorPageView::count(), 'الإرسال فعلٌ لا مشاهدة صفحة.');
    }

    #[Test]
    public function a_missing_page_is_recorded_so_broken_links_surface(): void
    {
        $this->get('/no-such-page-anywhere')->assertNotFound();

        $view = VisitorPageView::firstOrFail();

        $this->assertSame(404, $view->status_code);
        $this->assertNotEmpty((new InsightsReport(7))->brokenPaths());
    }

    #[Test]
    public function admin_visits_are_stored_but_excluded_from_the_public_numbers(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)->get('/');

        $this->assertTrue(VisitorSession::firstOrFail()->is_staff, 'زيارة الإدارة تُعلَّم ولا تُحذف.');
        $this->assertSame(0, (new InsightsReport(7))->totals()['sessions'], 'زيارة الإدارة دخلت أرقام السوق.');
    }

    /* ---------------------------------------------------------------
     * تصنيف المصدر
     * --------------------------------------------------------------- */

    #[Test]
    public function an_ai_assistant_referral_is_its_own_channel(): void
    {
        $this->get('/', ['referer' => 'https://chatgpt.com/c/abc']);

        $session = VisitorSession::firstOrFail();

        $this->assertSame('ai', $session->channel, 'إحالة مساعد الذكاء صُنِّفت إحالةً عادية فضاع مؤشّر الظهور.');
        $this->assertSame('ChatGPT', $session->platform);
    }

    #[Test]
    public function a_campaign_tag_beats_the_referrer(): void
    {
        $this->get('/?utm_source=newsletter&utm_medium=email&utm_campaign=launch', [
            'referer' => 'https://www.google.com/search?q=x',
        ]);

        $session = VisitorSession::firstOrFail();

        $this->assertSame('email', $session->channel, 'الوسم نيّة معلنة والمُحيل أثر عابر — الوسم يسبق.');
        $this->assertSame('launch', $session->campaign);
    }

    #[Test]
    public function a_click_id_means_paid_even_without_a_medium_tag(): void
    {
        $this->get('/?gclid=abc123');

        $this->assertSame('paid', VisitorSession::firstOrFail()->channel);
    }

    #[Test]
    public function internal_navigation_is_not_counted_as_a_referral(): void
    {
        $this->get('/', ['referer' => config('app.url').'/services']);

        $session = VisitorSession::firstOrFail();

        $this->assertSame('internal', $session->channel);
        $this->assertNull($session->referrer_host, 'الموقع صار أكبر مُحيل لنفسه.');
    }

    #[Test]
    public function sensitive_query_parameters_are_masked_before_storage(): void
    {
        $this->get('/?utm_campaign=spring&token=super-secret-value');

        $session = VisitorSession::firstOrFail();

        $this->assertStringNotContainsString('super-secret-value', (string) $session->landing_query);
        $this->assertStringContainsString('spring', (string) $session->landing_query);
    }

    #[Test]
    public function an_ai_crawler_is_named_and_kept_out_of_human_numbers(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; GPTBot/1.0; +https://openai.com/gptbot)')->get('/');

        $session = VisitorSession::firstOrFail();

        $this->assertTrue($session->is_bot);
        $this->assertSame('GPTBot', $session->bot_name);
        $this->assertSame('OpenAI', $session->bot_owner);

        $report = new InsightsReport(7);
        $this->assertSame(0, $report->totals()['sessions'], 'زحف البوت دخل أرقام البشر.');
        $this->assertNotEmpty($report->crawlers(), 'زحف نماذج الذكاء حُذف بدل أن يُعرض وحده.');
    }

    /* ---------------------------------------------------------------
     * البيكون وقياس الزمن
     * --------------------------------------------------------------- */

    #[Test]
    public function the_beacon_records_active_time_and_scroll_depth(): void
    {
        $this->get('/');

        $session = VisitorSession::firstOrFail();
        $view = $session->pageViews()->firstOrFail();

        $this->postJson(route('insights.collect'), [
            'visit' => $session->uuid,
            'view' => $view->uuid,
            'type' => 'heartbeat',
            'active_seconds' => 45,
            'scroll_percent' => 80,
            'interactions' => 3,
            'context' => ['tz' => 'Asia/Riyadh', 'sw' => 1920, 'sh' => 1080],
        ])->assertNoContent();

        $this->assertSame(45, $view->fresh()->active_seconds);
        $this->assertSame(80, $view->fresh()->scroll_percent);
        $this->assertSame(45, $session->fresh()->active_seconds);
        $this->assertSame('Asia/Riyadh', $session->fresh()->timezone);
    }

    #[Test]
    public function heartbeats_are_assigned_not_summed(): void
    {
        $this->get('/');

        $session = VisitorSession::firstOrFail();
        $view = $session->pageViews()->firstOrFail();

        foreach ([20, 40, 40] as $seconds) {
            $this->postJson(route('insights.collect'), [
                'visit' => $session->uuid,
                'view' => $view->uuid,
                'active_seconds' => $seconds,
            ]);
        }

        // النبضة تحمل الإجمالي: جمعُها كان سيجعل الرقم ١٠٠ بدل ٤٠.
        $this->assertSame(40, $session->fresh()->active_seconds);
    }

    #[Test]
    public function a_forged_visit_identifier_creates_nothing(): void
    {
        $this->postJson(route('insights.collect'), [
            'visit' => '11111111-2222-3333-4444-555555555555',
            'active_seconds' => 900,
        ])->assertNoContent();

        $this->assertSame(0, VisitorSession::count(), 'نقطة الجمع أنشأت جلسة من العدم.');
    }

    #[Test]
    public function reported_time_is_capped_so_a_forged_payload_cannot_skew_averages(): void
    {
        $this->get('/');
        $session = VisitorSession::firstOrFail();

        $this->postJson(route('insights.collect'), [
            'visit' => $session->uuid,
            'view' => $session->pageViews()->firstOrFail()->uuid,
            'active_seconds' => 999999,
        ]);

        $this->assertLessThanOrEqual(7200, $session->fresh()->active_seconds);
    }

    /* ---------------------------------------------------------------
     * التعريفات
     * --------------------------------------------------------------- */

    #[Test]
    public function a_single_page_visit_with_real_reading_time_is_not_a_bounce(): void
    {
        $this->get('/');

        $session = VisitorSession::firstOrFail();

        $this->assertTrue($session->is_bounce, 'الجلسة تبدأ مرتدّة حتى يثبت العكس.');

        $this->postJson(route('insights.collect'), [
            'visit' => $session->uuid,
            'view' => $session->pageViews()->firstOrFail()->uuid,
            'active_seconds' => 240,
        ]);

        $this->assertFalse(
            $session->fresh()->is_bounce,
            'من قرأ أربع دقائق في صفحة واحدة عُدّ مرتدًّا — التعريف بعدد الصفحات وحده خطأ.',
        );
    }

    #[Test]
    public function a_conversion_event_is_attributed_to_the_first_one_only(): void
    {
        $this->get('/');
        $session = VisitorSession::firstOrFail();
        $view = $session->pageViews()->firstOrFail();

        foreach (['signup', 'purchase'] as $name) {
            $this->postJson(route('insights.collect'), [
                'visit' => $session->uuid,
                'view' => $view->uuid,
                'type' => 'event',
                'event' => ['name' => $name, 'category' => 'conversion'],
            ]);
        }

        $this->assertSame('signup', $session->fresh()->conversion_name, 'التحويل نُسب لآخر ضغطة زر لا لأوّلها.');
        $this->assertSame(2, VisitorEvent::count());
    }

    #[Test]
    public function the_average_duration_divides_by_measured_visits_only(): void
    {
        $this->makeSession(['active_seconds' => 100]);
        $this->makeSession(['active_seconds' => 0]);

        $totals = (new InsightsReport(7))->totals();

        // القسمة على الجلستين كانت ستعطي ٥٠ — وهو رقم لا يصف أيًّا منهما.
        $this->assertSame(100, $totals['avg_seconds']);
        $this->assertSame(1, $totals['measured_sessions']);
        $this->assertSame(2, $totals['sessions']);
    }

    #[Test]
    public function every_ratio_is_reported_with_its_base(): void
    {
        $this->makeSession(['is_bounce' => true]);
        $this->makeSession(['is_bounce' => false]);

        $totals = (new InsightsReport(7))->totals();

        $this->assertSame(50.0, $totals['bounce_rate']);
        $this->assertSame(1, $totals['bounces']);
        $this->assertSame(2, $totals['sessions'], 'النسبة بلا مقامها رقم لا يمكن الحكم عليه (§١٣).');
    }

    #[Test]
    public function the_timeline_fills_empty_days_instead_of_skipping_them(): void
    {
        $this->makeSession(['started_at' => now()->subDays(5)]);

        $timeline = (new InsightsReport(7))->timeline();

        $this->assertCount(7, $timeline, 'اليوم الفارغ حُذف، فيرسم الخط اتصالًا كاذبًا بين نقطتين متباعدتين.');
        $this->assertSame(1, array_sum(array_column($timeline, 'sessions')));
    }

    /* ---------------------------------------------------------------
     * الموقع: فرضية معلنة
     * --------------------------------------------------------------- */

    #[Test]
    public function the_inferred_country_is_always_marked_as_a_hypothesis(): void
    {
        $place = app(LocationInference::class)->resolve('Asia/Riyadh', 'ar-SA,ar;q=0.9');

        $this->assertSame('SA', $place['country']);
        $this->assertSame('timezone', $place['basis']);
        $this->assertSame('inferred', $place['evidence'], 'الموقع المستنتج لا يُرقّى إلى measured (§١٥).');
        $this->assertSame('السعودية', LocationInference::countryName('SA'));
    }

    #[Test]
    public function a_missing_location_signal_is_left_empty_not_guessed(): void
    {
        $place = app(LocationInference::class)->resolve(null, null);

        $this->assertNull($place['country'], 'الفراغ عُبِّئ بتقدير صامت (§٤.٣).');
    }

    /* ---------------------------------------------------------------
     * قراءة الجهاز
     * --------------------------------------------------------------- */

    #[Test]
    public function the_user_agent_parser_prefers_the_most_specific_match(): void
    {
        $profile = app(ClientProfile::class);

        $edge = $profile->fromUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36 Edg/120.0');
        $this->assertSame('Edge', $edge['browser'], 'إيدج يُعلن نفسه كروم — الأخصّ قبل الأعمّ.');
        $this->assertSame('Windows', $edge['os']);
        $this->assertSame('desktop', $edge['device_type']);

        $iphone = $profile->fromUserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 Version/17.2 Mobile/15E148 Safari/604.1');
        $this->assertSame('mobile', $iphone['device_type']);
        $this->assertSame('iOS', $iphone['os']);
        $this->assertSame('17.2', $iphone['os_version']);
    }

    /* ---------------------------------------------------------------
     * اللوحة
     * --------------------------------------------------------------- */

    #[Test]
    public function a_normal_user_cannot_reach_the_insights_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.insights'))
            ->assertNotFound();
    }

    #[Test]
    public function an_admin_reads_the_whole_dashboard(): void
    {
        $this->makeSession(['channel' => 'ai', 'platform' => 'Perplexity', 'active_seconds' => 90]);

        $this->actingAs($this->admin())
            ->get(route('admin.insights'))
            ->assertOk()
            ->assertSee('إحصاءات الزوّار')
            ->assertSee('من أين جاؤوا')
            ->assertSee('Perplexity')
            ->assertSee('زحف الآلات ونماذج الذكاء')
            ->assertSee('كيف تُحسب هذه الأرقام بالضبط؟');
    }

    #[Test]
    public function an_admin_opens_a_single_visitor_journey(): void
    {
        $this->continue($this->get('/'))->get('/services');

        $session = VisitorSession::firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.insights.visitors'))->assertOk();
        $this->actingAs($admin)->get(route('admin.insights.visitor', $session->visitor_id))
            ->assertOk()
            ->assertSee('كيف عرفنا');

        $this->actingAs($admin)->get(route('admin.insights.session', $session->uuid))
            ->assertOk()
            ->assertSee('الرحلة داخل الموقع')
            ->assertSee('/services');
    }

    #[Test]
    public function the_live_feed_answers_who_is_on_the_site_now(): void
    {
        $this->makeSession(['last_activity_at' => now()]);

        $this->actingAs($this->admin())
            ->getJson(route('admin.insights.live'))
            ->assertOk()
            ->assertJsonPath('count', 1);
    }

    #[Test]
    public function the_export_streams_a_csv_with_an_arabic_safe_encoding(): void
    {
        $this->makeSession();

        $response = $this->actingAs($this->admin())->get(route('admin.insights.export', ['days' => 7]));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringStartsWith("\xEF\xBB\xBF", $response->streamedContent());
    }

    /* ---------------------------------------------------------------
     * التجميع والتقليم
     * --------------------------------------------------------------- */

    #[Test]
    public function the_daily_rollup_is_idempotent(): void
    {
        $this->makeSession(['channel' => 'organic', 'platform' => 'Google']);

        $rollup = app(InsightsRollup::class);
        $rollup->rebuildLastDays(2);
        $rollup->rebuildLastDays(2);

        $total = VisitorDailyStat::where('dimension', 'total')->firstOrFail();

        $this->assertSame(1, $total->sessions, 'التشغيل مرتين ضاعف الرقم — التجميع يجب أن يُكتب لا أن يُزاد.');
        $this->assertSame(1, VisitorDailyStat::where('dimension', 'total')->count());
    }

    #[Test]
    public function pruning_keeps_the_aggregate_after_dropping_the_raw_rows(): void
    {
        $old = $this->makeSession(['started_at' => now()->subDays(500), 'last_activity_at' => now()->subDays(500)]);
        VisitorPageView::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'session_id' => $old->id,
            'visitor_id' => $old->visitor_id,
            'path' => '/old',
            'url' => 'https://example.test/old',
            'viewed_at' => now()->subDays(500),
        ]);

        VisitorDailyStat::create([
            'stat_date' => now()->subDays(500)->toDateString(),
            'dimension' => 'total',
            'value' => 'all',
            'sessions' => 1,
        ]);

        app(InsightsRollup::class)->prune(400);

        $this->assertSame(0, VisitorSession::count());
        $this->assertSame(0, VisitorPageView::count());
        $this->assertSame(1, VisitorDailyStat::count(), 'التاريخ المجمَّع ذهب مع الخام.');
    }

    /* ---------------------------------------------------------------
     * أدوات
     * --------------------------------------------------------------- */

    /**
     * متابعة التصفّح بنفس كوكيز الاستجابة السابقة.
     *
     * عميل الاختبار لا يحتفظ بالكوكيز بين طلبين، وبدون نقلها يبدو كل
     * طلب زائرًا جديدًا — فيمرّ اختبار «الجلسة تستمر» على جلسة لم تستمر.
     * القيم تُمرَّر كما وصلت لأنها مشفّرة أصلًا من وسيط التشفير.
     */
    private function continue(TestResponse $response): self
    {
        $cookies = [];

        foreach ($response->headers->getCookies() as $cookie) {
            if ((string) $cookie->getValue() !== '') {
                $cookies[$cookie->getName()] = (string) $cookie->getValue();
            }
        }

        return $this->withUnencryptedCookies($cookies);
    }

    /** @param array<string, mixed> $attributes */
    private function makeSession(array $attributes = []): VisitorSession
    {
        static $counter = 0;
        $counter++;

        return VisitorSession::create(array_merge([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'visitor_id' => 'visitor'.str_pad((string) $counter, 26, '0', STR_PAD_LEFT),
            'started_at' => now(),
            'last_activity_at' => now(),
            'entry_path' => '/',
            'channel' => 'direct',
            'device_type' => 'desktop',
            'active_seconds' => 0,
            'page_views_count' => 1,
        ], $attributes));
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    /** التصنيف مفردة واحدة: يُستدعى ليضمن بقاء الأسماء العربية موحّدة. */
    #[Test]
    public function channel_labels_have_a_single_source(): void
    {
        $this->assertSame('مساعدات ذكاء', TrafficOrigin::CHANNELS['ai']);
        $this->assertArrayHasKey('direct', TrafficOrigin::CHANNELS);
        $this->assertSame(VisitorIdentity::COOKIE, 'ks_visitor');
    }
}
