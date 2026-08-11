<?php

namespace Tests\Feature;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * لغة صاحب الطلب تسافر مع المهمة إلى العامل.
 *
 * `SetLocale` وسيطٌ يعمل داخل دورة الطلب وحدها، والعامل عملية أخرى تُقلع
 * على `config('app.locale')` — أي العربية. فكان من يطلب تشخيصًا وواجهته
 * فرنسية يحصل على تقرير عربي، لأن التوليد يجري بعد أن تنتهي دورة طلبه
 * بوقت طويل. لا خطأ في السجل، ولا شيء في الكود يبدو معطوبًا.
 */
class QueueLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_job_runs_in_the_locale_of_whoever_dispatched_it(): void
    {
        $this->app->setLocale('fr');

        LocaleProbeJob::$seen = null;
        LocaleProbeJob::dispatch();

        $this->assertSame('fr', LocaleProbeJob::$seen);
    }

    public function test_the_source_locale_survives_the_same_path(): void
    {
        $this->app->setLocale('ar');

        LocaleProbeJob::$seen = null;
        LocaleProbeJob::dispatch();

        $this->assertSame('ar', LocaleProbeJob::$seen);
    }

    /**
     * حمولة غير معروفة اللغة لا تُغيّر شيئًا.
     *
     * المهام المُرسَلة قبل هذه القدرة لا تحمل مفتاح `locale`. سقوطها في
     * لغة فارغة يعني تقارير بلا لغة أصلًا — أسوأ من عربية.
     */
    public function test_a_payload_without_a_locale_leaves_the_worker_locale_alone(): void
    {
        $this->app->setLocale('en');

        Queue::createPayloadUsing(static fn (): array => []);

        LocaleProbeJob::$seen = null;
        LocaleProbeJob::dispatch();

        $this->assertSame('en', LocaleProbeJob::$seen);
    }
}

class LocaleProbeJob implements ShouldQueue
{
    use Queueable;

    public static ?string $seen = null;

    public function handle(): void
    {
        self::$seen = app()->getLocale();
    }
}
