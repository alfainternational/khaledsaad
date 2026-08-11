<?php

namespace App\Providers;

use App\Modules\Shared\I18n\BladeTranslator;
use App\Modules\Shared\I18n\JsPhrases;
use App\Modules\Shared\I18n\LocaleRegistry;
use App\Modules\Shared\I18n\LocaleUrls;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * ربط طبقة تعدد اللغات.
 *
 * أهم سطر هنا هو `prepareStringsForCompilationUsing`: به يصير كل نص عربي
 * في أي قالب قابلًا للترجمة دون تعديل حرف واحد في القوالب نفسها.
 *
 * تنبيه تشغيلي: هذا التغليف يقع وقت تصريف القالب، ونتيجته تُخزَّن في
 * `storage/framework/views`. تغيير منطق التغليف لا يُبطل ذلك الكاش لأن
 * Blade يقارن تاريخ الملف لا محتوى المصرِّف — فأي تعديل في
 * `BladeTranslator` يتبعه `php artisan view:clear` وإلا بقيت الصفحات
 * تُعرض بمنطق قديم بلا أي عرض للخطأ.
 */
final class LocalizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LocaleRegistry::class);
        $this->app->singleton(BladeTranslator::class);
        $this->app->singleton(JsPhrases::class);
        $this->app->singleton(LocaleUrls::class);
    }

    public function boot(): void
    {
        /*
         * السجل نفسه يُشارَك مع كل القوالب — لا الاتجاه كنصّ.
         *
         * الفرق ليس تفصيلًا: `View::share` تُنفَّذ مرة عند الإقلاع، فلو
         * شاركنا `'rtl'` لبقيت `rtl` بعد أن تتبدّل اللغة. أما الكائن
         * فيقرأ `app()->getLocale()` لحظة استدعائه، فيصدق دائمًا.
         *
         * ولأنها هنا لا في الوسيط، تعمل القوالب المُصيَّرة خارج دورة
         * الطلب أيضًا — بريد في طابور، أو PDF من أمر سطري.
         */
        View::share('appLocales', $this->app->make(LocaleRegistry::class));

        /*
         * قاموس نصوص JavaScript — للسبب نفسه ولنفس السلوك: قيمةٌ تُقرأ
         * لحظة العرض لا عند الإقلاع. `JsPhrases` يقرأ لغة الطلب بنفسه،
         * فتصل الشاشة قاموسَ لغتها لا قاموس اللغة التي أقلع بها الخادم.
         */
        View::share('jsPhrases', $this->app->make(JsPhrases::class));
        View::share('localeUrls', $this->app->make(LocaleUrls::class));

        $this->carryLocaleIntoTheQueue();

        if (config('locales.blade_auto_wrap', true) === false) {
            return;
        }

        $translator = $this->app->make(BladeTranslator::class);

        $excluded = array_map(
            static fn (string $path): string => str_replace('\\', '/', base_path($path)),
            (array) config('locales.scan.blade.exclude', []),
        );

        Blade::prepareStringsForCompilationUsing(
            static function (string $template) use ($translator, $excluded): string {
                /*
                 * القائمة المستثناة كانت تحرس **الاستخراج** وحده، فيمرّ
                 * المستثنى بالمغلّف رغم ذلك.
                 *
                 * والمغلّف لا يكتفي بتغليف النصّ: يضمّ الأسطر المتجاورة في
                 * جملة واحدة (مفتاح واحد لكل جملة)، ويُخرج الناتج مهرَّبًا.
                 * على قالب HTML لا أثر لذلك. أما `llms.txt` فملفّ Markdown
                 * نصّي: ضمُّ أسطره يمحو بنيته كلها، و`>` تصير `&gt;` —
                 * فيصل النموذجَ اللغوي ملفٌّ بسطر واحد ورموز مهرَّبة، وهو
                 * الملفّ الذي كُتب ليقرأه هو تحديدًا.
                 *
                 * `Blade::getPath()` هو ما يجعل هذا ممكنًا: النداء لا يحمل
                 * المسار في وسائطه، والمصرّف يحتفظ به أثناء التصريف.
                 */
                $path = str_replace('\\', '/', (string) Blade::getPath());

                foreach ($excluded as $prefix) {
                    if ($path !== '' && str_starts_with($path, $prefix)) {
                        return $template;
                    }
                }

                return $translator->rewrite($template);
            },
        );
    }

    /**
     * لغة صاحب الطلب تسافر مع المهمة إلى العامل.
     *
     * `SetLocale` وسيطٌ يعمل داخل دورة الطلب وحدها. والعامل عملية أخرى
     * تُقلع على `config('app.locale')` — أي العربية دائمًا. فكان من يطلب
     * تشخيصًا وواجهته بالفرنسية يحصل على تقرير عربي، لأن التوليد يجري في
     * `RunToolPipeline` بعد أن تنتهي دورة طلبه بوقت طويل.
     *
     * الحقن في حمولة الطابور لا في بانِي كل مهمة: البانِي يعمل وقت
     * الإرسال (داخل الطلب) فيصلح، لكنه يحتاج سطرًا في كل مهمة — والمهمة
     * الخامسة التي تُكتب بعد شهر ستولّد عربيًّا بلا أن ينتبه أحد. هنا
     * تُوسَم كل مهمة، الموجودة والقادمة.
     */
    private function carryLocaleIntoTheQueue(): void
    {
        Queue::createPayloadUsing(static fn (): array => ['locale' => app()->getLocale()]);

        Queue::before(function (JobProcessing $event): void {
            $locale = $event->job->payload()['locale'] ?? null;

            if (is_string($locale) && $this->app->make(LocaleRegistry::class)->isEnabled($locale)) {
                $this->app->setLocale($locale);
            }
        });
    }
}
