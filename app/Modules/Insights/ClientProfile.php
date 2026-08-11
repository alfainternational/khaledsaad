<?php

namespace App\Modules\Insights;

/**
 * قراءة الجهاز والمتصفح ونظام التشغيل من ترويسة User-Agent.
 *
 * بلا مكتبة ولا نداء خارجي (§١٠ الفئة أ): الترويسة نصّ يصل مع كل طلب،
 * وتفكيكها بأنماط ثابتة يعطي ٩٥٪ من القيمة بلا تبعية تُحدَّث شهريًّا.
 *
 * الترتيب مقصود: كروم يُعلن نفسه سفاري أيضًا، وإيدج يُعلن نفسه كروم،
 * فمن يفحص الشائع أولًا يُخطئ التصنيف. الأخصّ قبل الأعمّ دائمًا.
 */
class ClientProfile
{
    /** @var array<int, array{0: string, 1: string}> */
    private const BROWSERS = [
        ['Edg', 'Edge'],
        ['OPR', 'Opera'],
        ['Opera', 'Opera'],
        ['SamsungBrowser', 'Samsung Internet'],
        ['YaBrowser', 'Yandex'],
        ['Vivaldi', 'Vivaldi'],
        ['Brave', 'Brave'],
        ['CriOS', 'Chrome'],
        ['FxiOS', 'Firefox'],
        ['Firefox', 'Firefox'],
        ['Chrome', 'Chrome'],
        ['Safari', 'Safari'],
        ['MSIE', 'Internet Explorer'],
        ['Trident', 'Internet Explorer'],
    ];

    /**
     * ملف الجهاز كاملًا من ترويسة واحدة.
     *
     * @return array<string, mixed>
     */
    public function fromUserAgent(?string $userAgent): array
    {
        $agent = trim((string) $userAgent);

        if ($agent === '') {
            // ترويسة فارغة ليست «سطح مكتب مجهول» بل أداة آلية غالبًا.
            return $this->machine(__('غير معروف'), null, 'unknown');
        }

        $bot = $this->detectBot($agent);

        if ($bot !== null) {
            return array_merge(
                $this->machine($bot['name'], $bot['owner'], 'bot'),
                ['user_agent' => mb_substr($agent, 0, 500)],
            );
        }

        [$browser, $browserVersion] = $this->browser($agent);
        [$os, $osVersion] = $this->operatingSystem($agent);

        return [
            'device_type' => $this->deviceType($agent),
            'browser' => $browser,
            'browser_version' => $browserVersion,
            'os' => $os,
            'os_version' => $osVersion,
            'is_bot' => false,
            'bot_name' => null,
            'bot_owner' => null,
            'user_agent' => mb_substr($agent, 0, 500),
        ];
    }

    /**
     * البوت المصنَّف يُسمّى ويُنسب لمالكه؛ وغير المصنَّف يُعلَّم بوتًا بلا اسم.
     *
     * الفرق مهم: «زارك GPTBot ١٤ مرة هذا الأسبوع» جوابٌ عن سؤال منتج،
     * و«زارك بوت مجهول» ضوضاء تُستبعد فقط.
     *
     * @return array{name: string, owner: string|null}|null
     */
    private function detectBot(string $agent): ?array
    {
        foreach ((array) config('insights.ai_crawlers', []) as $token => $owner) {
            if (stripos($agent, (string) $token) !== false) {
                return ['name' => (string) $token, 'owner' => (string) $owner];
            }
        }

        foreach ((array) config('insights.bot_patterns', []) as $pattern) {
            if (stripos($agent, (string) $pattern) !== false) {
                return ['name' => __('آلي غير مصنّف'), 'owner' => null];
            }
        }

        return null;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function browser(string $agent): array
    {
        foreach (self::BROWSERS as [$token, $label]) {
            if (stripos($agent, $token) === false) {
                continue;
            }

            /*
             * سفاري يضع رقم محرّكه بعد «Safari/» ورقم نفسه بعد «Version/».
             * قراءة الأول تعطي «سفاري 604.1» لكل إصدارات iOS منذ سنوات،
             * فيبدو أن أحدًا لم يحدّث متصفحه منذ ٢٠١٧.
             */
            if ($label === 'Safari' && preg_match('#Version/([0-9]+(?:\.[0-9]+)?)#i', $agent, $version)) {
                return [$label, $version[1]];
            }

            preg_match('#'.preg_quote($token, '#').'[/ ]?([0-9]+(?:\.[0-9]+)?)#i', $agent, $matches);

            return [$label, $matches[1] ?? null];
        }

        return [null, null];
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function operatingSystem(string $agent): array
    {
        // أندرويد يحتوي «Linux» في ترويسته، فيسبقه في الفحص.
        if (preg_match('#Android ([0-9.]+)#i', $agent, $m)) {
            return ['Android', $m[1]];
        }

        if (preg_match('#(?:iPhone|iPad|iPod).*?OS ([0-9_]+)#i', $agent, $m)) {
            return ['iOS', str_replace('_', '.', $m[1])];
        }

        if (preg_match('#Windows NT ([0-9.]+)#i', $agent, $m)) {
            // ويندوز ١١ يُعلن نفسه 10.0 ولا سبيل للتفريق من الترويسة وحدها.
            return ['Windows', $m[1] === '10.0' ? '10/11' : $m[1]];
        }

        if (preg_match('#Mac OS X ([0-9_]+)#i', $agent, $m)) {
            return ['macOS', str_replace('_', '.', $m[1])];
        }

        if (stripos($agent, 'CrOS') !== false) {
            return ['ChromeOS', null];
        }

        if (stripos($agent, 'Linux') !== false) {
            return ['Linux', null];
        }

        return [null, null];
    }

    private function deviceType(string $agent): string
    {
        if (preg_match('#iPad|Tablet|PlayBook|Silk|Android(?!.*Mobile)#i', $agent)) {
            return 'tablet';
        }

        if (preg_match('#Mobi|iPhone|iPod|Android|Windows Phone|BlackBerry#i', $agent)) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * @return array<string, mixed>
     */
    private function machine(string $name, ?string $owner, string $device): array
    {
        return [
            'device_type' => $device === 'unknown' ? 'unknown' : 'bot',
            'browser' => null,
            'browser_version' => null,
            'os' => null,
            'os_version' => null,
            'is_bot' => true,
            'bot_name' => $name,
            'bot_owner' => $owner,
            'user_agent' => null,
        ];
    }
}
