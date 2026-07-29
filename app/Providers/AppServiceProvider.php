<?php

namespace App\Providers;

use App\Contracts\CompetitorProvider;
use App\Modules\AiReadiness\Contracts\PageFetcher;
use App\Modules\AiReadiness\HttpPageFetcher;
use App\Modules\Competitors\LiveCompetitorProvider;
use App\Modules\Intake\Contracts\SpeechToText;
use App\Modules\Intake\HttpSpeechToText;
use App\Services\Billing\Entitlements;
use App\Services\Settings\MailConfigurator;
use App\Support\Settings\SettingsConfig;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // مصدر اكتشاف المنافسين المرشّحين. الحيّ افتراضًا، ويُستبدل بمزيّف في الاختبار.
        $this->app->bind(CompetitorProvider::class, LiveCompetitorProvider::class);

        /*
         * جالب صفحات التدقيق. خلف عقد ليُختبر بلا شبكة: قواعد المحور ٧ تُقاس
         * على HTML ثابت، فتبقى الدرجة قابلة لإعادة الإنتاج ولا تتعلق بحال
         * موقع خارجي لحظة تشغيل الاختبار.
         */
        $this->app->bind(PageFetcher::class, HttpPageFetcher::class);

        /*
         * النسخ الصوتي: خلف عقد لأن دقّة النسخ العربي تتفاوت بشدّة بين
         * المزوّدات على اللهجات الخليجية، وتبديلها قرارُ جودة بعد القياس.
         */
        $this->app->bind(SpeechToText::class, HttpSpeechToText::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * @feature('reports.pdf') في القوالب: يخفي ما لا تسمح به الخطة بدل أن
         * يعرض زرًّا يُرفض عند الضغط. المنع الحقيقي يبقى في المسار/الخدمة —
         * هذا للعرض فقط.
         */
        Blade::if('feature', function (string $key): bool {
            $user = auth()->user();

            return $user !== null && app(Entitlements::class)->allows($user->primaryWorkspace(), $key);
        });

        // إعدادات البريد من قاعدة البيانات تُطبَّق عند الإقلاع، فيضبطها الآدمن
        // من اللوحة دون لمس .env. محمي حتى لا يكسر الإقلاع قبل وجود جدول settings.
        if ($this->app->runningInConsole() === false || app()->environment() !== 'testing') {
            try {
                if (Schema::hasTable('settings')) {
                    app(MailConfigurator::class)->apply();
                    // مفاتيح الذكاء وأرقام السوق: تُطبَّق فوق config حيًّا من اللوحة.
                    app(SettingsConfig::class)->apply();
                }
            } catch (\Throwable $exception) {
                // قاعدة البيانات غير متاحة بعد (تثبيت أولي): نتجاهل بأمان.
            }
        }
    }
}
