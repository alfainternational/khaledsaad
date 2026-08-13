<?php

namespace App\Http\Middleware;

use App\Modules\Shared\I18n\LocaleRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * تحديد لغة الطلب.
 *
 * الترتيب مقصود: الرابط يفوز على التفضيل المحفوظ لأن مبدّل اللغة رابط،
 * والرابط يُشارَك. من يرسل صفحة بالإنجليزية لزميل يجب أن يراها زميله
 * بالإنجليزية حتى لو كان تفضيله المحفوظ عربيًّا. وتفضيل المستخدم المسجَّل
 * يفوز على الكوكي لأنه ينتقل معه بين الأجهزة، والكوكي لا ينتقل.
 *
 * ترويسة المتصفح آخر المصادر لا أولها: الزائر الخليجي كثيرًا ما يستعمل
 * نظامًا إنجليزيًّا وهو يقرأ عربيًّا. ترقيتها فوق الاختيار الصريح تعطيه
 * لغةً لم يطلبها في منتج لغته الأم عربية.
 */
final class SetLocale
{
    public function __construct(private readonly LocaleRegistry $locales) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        $response = $next($request);

        // الاختيار الصريح يُثبَّت: بلا هذا يعود الزائر إلى العربية عند
        // أول رابط داخلي، فيبدو المبدّل معطّلًا وهو يعمل.
        if ($this->explicit($request) !== null) {
            $cookie = (string) config('locales.detection.cookie', 'ks_locale');
            $days = (int) config('locales.detection.cookie_days', 365);

            Cookie::queue(Cookie::make($cookie, $locale, $days * 24 * 60));

            // ومن كان مسجَّلًا يُحفظ اختياره في حسابه لا في كوكي جهازه
            // وحده، فيجده كما تركه على هاتفه وحاسبه معًا.
            $user = $request->user();

            /*
             * الحفظ لا يوقف الطلب إن فشل: عمود `locale` يصل بهجرة، ونشرٌ
             * يسبق الهجرة يجعل كل ضغطة على مبدّل اللغة خطأ ٥٠٠ بدل أن
             * تبدّل اللغة. الكوكي وحده يكفي حتى تُشغَّل الهجرة.
             */
            if ($user !== null && $user->getAttribute('locale') !== $locale) {
                try {
                    $user->forceFill(['locale' => $locale])->saveQuietly();
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        }

        if (! $response->headers->has('Content-Language')) {
            $response->headers->set('Content-Language', $this->locales->htmlLang($locale));
        }

        return $response;
    }

    private function resolve(Request $request): string
    {
        foreach ((array) config('locales.detection.order', ['query', 'user', 'cookie', 'header']) as $source) {
            $candidate = match ($source) {
                'query' => $this->explicit($request),
                'user' => $this->fromUser($request),
                'cookie' => $request->cookie((string) config('locales.detection.cookie', 'ks_locale')),
                'header' => $this->fromHeader($request),
                default => null,
            };

            if (is_string($candidate) && $this->locales->isEnabled($candidate)) {
                return $candidate;
            }
        }

        return $this->locales->source();
    }

    private function explicit(Request $request): ?string
    {
        $key = (string) config('locales.detection.query', 'lang');
        $value = $request->query($key);

        return is_string($value) && $this->locales->isEnabled($value) ? $value : null;
    }

    private function fromUser(Request $request): ?string
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $locale = $user->getAttribute('locale');

        return is_string($locale) ? $locale : null;
    }

    /**
     * أفضل تطابق بين ترويسة المتصفح واللغات المفعّلة، مع مطابقة الجذر:
     * `fr-CA` يجب أن يصل إلى `fr` لا أن يسقط إلى الافتراضي.
     */
    private function fromHeader(Request $request): ?string
    {
        $enabled = $this->locales->enabled();

        foreach ($request->getLanguages() as $language) {
            $normalized = strtolower(str_replace('_', '-', (string) $language));

            if (in_array($normalized, $enabled, true)) {
                return $normalized;
            }

            $root = explode('-', $normalized)[0];

            if (in_array($root, $enabled, true)) {
                return $root;
            }
        }

        return null;
    }
}
