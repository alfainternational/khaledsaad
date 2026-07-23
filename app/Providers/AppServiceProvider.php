<?php

namespace App\Providers;

use App\Contracts\CompetitorProvider;
use App\Services\Competitors\LiveCompetitorProvider;
use App\Services\Settings\MailConfigurator;
use App\Support\Settings\SettingsConfig;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
