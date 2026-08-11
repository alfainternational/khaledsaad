<?php

namespace App\Modules\Insights;

use Illuminate\Http\Request;

/**
 * «جاء من وين»: تصنيف مصدر الزيارة من المُحيل ووسوم الحملة.
 *
 * القاعدة الحاكمة هنا أن وسم الحملة يسبق المُحيل دائمًا. من ينقر رابطًا
 * موسومًا `utm_source=newsletter` قادمٌ من النشرة حتى لو أظهر المتصفح
 * أن المُحيل جوجل — الوسم نيّة معلنة والمُحيل أثر عابر.
 *
 * ولذلك تُخزَّن القناة **والدليل الخام معًا**: القناة محسوبة (`derived`)
 * ويبقى المُحيل ووسومه كما وصلا، فيمكن مراجعة التصنيف لاحقًا بلا فقدان.
 */
class TrafficOrigin
{
    /**
     * خريطة المُحيلين: النطاق ⇐ [القناة، اسم المنصة بالعربية].
     *
     * مساعدات الذكاء قناة مستقلة لا «إحالة»: زائرٌ وصل من ChatGPT وصل
     * لأن نموذجًا ذكر العلامة، وهذا مقياس ظهور لا مقياس رابط. خلطه
     * بالإحالات العامة يخفي المؤشّر الوحيد الذي يقيس المحور السابع سلوكيًّا.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const HOSTS = [
        // مساعدات الذكاء
        'chatgpt.com' => ['ai', 'ChatGPT'],
        'chat.openai.com' => ['ai', 'ChatGPT'],
        'openai.com' => ['ai', 'OpenAI'],
        'perplexity.ai' => ['ai', 'Perplexity'],
        'www.perplexity.ai' => ['ai', 'Perplexity'],
        'claude.ai' => ['ai', 'Claude'],
        'gemini.google.com' => ['ai', 'Gemini'],
        'bard.google.com' => ['ai', 'Gemini'],
        'copilot.microsoft.com' => ['ai', 'Copilot'],
        'you.com' => ['ai', 'You.com'],
        'poe.com' => ['ai', 'Poe'],
        'grok.com' => ['ai', 'Grok'],
        'deepseek.com' => ['ai', 'DeepSeek'],

        // محرّكات البحث
        'google.com' => ['organic', 'Google'],
        'google.com.sa' => ['organic', 'Google'],
        'google.ae' => ['organic', 'Google'],
        'google.com.kw' => ['organic', 'Google'],
        'google.com.qa' => ['organic', 'Google'],
        'google.com.eg' => ['organic', 'Google'],
        'bing.com' => ['organic', 'Bing'],
        'duckduckgo.com' => ['organic', 'DuckDuckGo'],
        'search.yahoo.com' => ['organic', 'Yahoo'],
        'yandex.com' => ['organic', 'Yandex'],
        'ecosia.org' => ['organic', 'Ecosia'],
        'brave.com' => ['organic', 'Brave Search'],

        // شبكات اجتماعية
        'facebook.com' => ['social', 'Facebook'],
        'l.facebook.com' => ['social', 'Facebook'],
        'm.facebook.com' => ['social', 'Facebook'],
        'lm.facebook.com' => ['social', 'Facebook'],
        'instagram.com' => ['social', 'Instagram'],
        'l.instagram.com' => ['social', 'Instagram'],
        'twitter.com' => ['social', 'X'],
        'x.com' => ['social', 'X'],
        't.co' => ['social', 'X'],
        'linkedin.com' => ['social', 'LinkedIn'],
        'lnkd.in' => ['social', 'LinkedIn'],
        'tiktok.com' => ['social', 'TikTok'],
        'snapchat.com' => ['social', 'Snapchat'],
        'youtube.com' => ['social', 'YouTube'],
        'youtu.be' => ['social', 'YouTube'],
        'pinterest.com' => ['social', 'Pinterest'],
        'reddit.com' => ['social', 'Reddit'],
        'threads.net' => ['social', 'Threads'],
        'threads.com' => ['social', 'Threads'],
        'medium.com' => ['social', 'Medium'],
        'quora.com' => ['social', 'Quora'],

        // مراسلة: نقرة من محادثة خاصة، وهي إشارة توصية لا إعلان.
        'web.whatsapp.com' => ['social', 'WhatsApp'],
        'api.whatsapp.com' => ['social', 'WhatsApp'],
        'chat.whatsapp.com' => ['social', 'WhatsApp'],
        'wa.me' => ['social', 'WhatsApp'],
        't.me' => ['social', 'Telegram'],
        'telegram.me' => ['social', 'Telegram'],
        'web.telegram.org' => ['social', 'Telegram'],
        'discord.com' => ['social', 'Discord'],
        'slack.com' => ['social', 'Slack'],

        // بريد الويب: نقرة من رسالة، تُصنَّف بريدًا لا إحالة.
        'mail.google.com' => ['email', 'Gmail'],
        'outlook.live.com' => ['email', 'Outlook'],
        'outlook.office.com' => ['email', 'Outlook'],
        'mail.yahoo.com' => ['email', 'Yahoo Mail'],
    ];

    /** أسماء القنوات بالعربية — مكان واحد يمنع تفرّع المفردة عبر الشاشات. */
    public const CHANNELS = [
        'direct' => 'مباشر',
        'organic' => 'بحث مجاني',
        'paid' => 'إعلان مدفوع',
        'social' => 'شبكات اجتماعية',
        'email' => 'بريد',
        'ai' => 'مساعدات ذكاء',
        'referral' => 'إحالة من موقع',
        'internal' => 'تنقّل داخلي',
    ];

    /**
     * @return array<string, mixed>
     */
    public function fromRequest(Request $request): array
    {
        $referrer = (string) $request->headers->get('referer', '');
        $host = $this->hostOf($referrer);
        $utm = $this->campaignTags($request);

        // إحالة من نطاقنا نفسه ليست مصدرًا: هي تنقّل داخل الموقع، وعدّها
        // مصدرًا يجعل الموقع أكبر مُحيل لنفسه ويطمس المصادر الحقيقية.
        $isInternal = $host !== null && $this->isOwnHost($host);

        [$channel, $platform] = $this->classify($host, $isInternal, $utm);

        return [
            'channel' => $channel,
            'platform' => $platform,
            'source' => $utm['source'] ?? ($isInternal ? null : $host),
            'medium' => $utm['medium'] ?? $this->impliedMedium($channel),
            'campaign' => $utm['campaign'] ?? null,
            'term' => $utm['term'] ?? null,
            'content' => $utm['content'] ?? null,
            'referrer_host' => $isInternal ? null : $host,
            'referrer_url' => $isInternal ? null : ($referrer !== '' ? mb_substr($referrer, 0, 1000) : null),
            'landing_query' => $this->sanitizedQuery($request),
        ];
    }

    /**
     * القرار: الوسم أولًا، ثم النطاق المعروف، ثم إحالة عامة، ثم مباشر.
     *
     * @param  array<string, string>  $utm
     * @return array{0: string, 1: string|null}
     */
    private function classify(?string $host, bool $isInternal, array $utm): array
    {
        $known = $host !== null ? $this->lookup($host) : null;

        // معرّفات نقر المنصات الإعلانية: وجودها يعني نقرة مدفوعة حتى لو
        // نُسي وسم utm_medium — والنسيان هو الحالة الغالبة لا الاستثناء.
        if (isset($utm['medium']) && preg_match('#cp[cmv]|p?paid|ppc|ads?#i', $utm['medium'])) {
            return ['paid', $utm['source'] ?? ($known[1] ?? null)];
        }

        if ($utm['click_id'] ?? false) {
            return ['paid', $utm['source'] ?? ($known[1] ?? null)];
        }

        /*
         * وسم المصدر يحسم القناة، ولا يُستشار المُحيل معه.
         *
         * رابط نشرة بريدية يُفتح من جيميل يصل بمُحيل جوجل. الرجوع إلى
         * خريطة المُحيلين هنا كان يصنّفه «بحث مجاني»، فتُنسب زيارات
         * النشرة إلى قناة لم ترسل أحدًا.
         */
        if (isset($utm['source'])) {
            [$channel, $platform] = $this->fromSourceName($utm['source'], $utm['medium'] ?? null);

            return [$channel, $platform ?? $utm['source']];
        }

        if ($known !== null) {
            return $known;
        }

        if ($isInternal) {
            return ['internal', null];
        }

        if ($host !== null) {
            return ['referral', $host];
        }

        /*
         * لا مُحيل ولا وسم = «مباشر»، وهي أكثر خانة يُساء فهمها.
         *
         * ليست بالضرورة «كتب العنوان بيده»: نقرة من تطبيق جوّال أو من
         * PDF أو من رابط https إلى http تصل بلا مُحيل أيضًا. لذلك تُعرض
         * في اللوحة موصوفةً بـ«بلا مُحيل معلن» لا بـ«كتب العنوان».
         */
        return ['direct', null];
    }

    /**
     * وسوم الحملة كما وصلت، مقصوصة بطول آمن.
     *
     * @return array<string, string>
     */
    private function campaignTags(Request $request): array
    {
        $tags = [];

        foreach (['source', 'medium', 'campaign', 'term', 'content'] as $key) {
            $value = $request->query('utm_'.$key);

            if (is_string($value) && trim($value) !== '') {
                $tags[$key] = mb_substr(trim($value), 0, 120);
            }
        }

        foreach (['gclid', 'gbraid', 'wbraid', 'fbclid', 'ttclid', 'msclkid', 'li_fat_id', 'twclid'] as $clickId) {
            if ($request->query($clickId) !== null) {
                $tags['click_id'] = $clickId;
                break;
            }
        }

        return $tags;
    }

    /**
     * اسم مصدر مكتوب يدويًّا ⇐ [القناة، المنصة إن عُرفت].
     *
     * `utm_source` نصّ حرّ يكتبه من بنى الرابط: قد يكون «facebook» أو
     * «Facebook Ads» أو «fb». نطابقه بخريطتنا أولًا، فإن لم يُعرف نقرأ
     * الوسيط، فإن لم يُفد نعدّه إحالة — ولا نخترع تصنيفًا.
     *
     * @return array{0: string, 1: string|null}
     */
    private function fromSourceName(string $source, ?string $medium = null): array
    {
        $needle = mb_strtolower($source);

        foreach (self::HOSTS as $host => [$channel, $platform]) {
            if (str_contains($needle, mb_strtolower($platform)) || str_contains($needle, $host)) {
                return [$channel, $platform];
            }
        }

        if (in_array($needle, ['newsletter', 'email', 'mail', 'نشرة', 'crm'], true)) {
            return ['email', null];
        }

        $medium = mb_strtolower((string) $medium);

        return match (true) {
            str_contains($medium, 'email'), str_contains($medium, 'newsletter') => ['email', null],
            str_contains($medium, 'social') => ['social', null],
            str_contains($medium, 'organic') => ['organic', null],
            default => ['referral', null],
        };
    }

    /** @return array{0: string, 1: string}|null */
    private function lookup(string $host): ?array
    {
        if (isset(self::HOSTS[$host])) {
            return self::HOSTS[$host];
        }

        // النطاقات الفرعية: `m.facebook.com` و`ar.wikipedia.org` وأمثالها.
        foreach (self::HOSTS as $known => $mapping) {
            if (str_ends_with($host, '.'.$known)) {
                return $mapping;
            }
        }

        return null;
    }

    private function hostOf(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return mb_strtolower(preg_replace('#^www\.#', '', $host) ?? $host);
    }

    private function isOwnHost(string $host): bool
    {
        $own = $this->hostOf((string) config('app.url')) ?? '';

        return $host === $own || str_ends_with($host, '.'.$own);
    }

    private function impliedMedium(string $channel): ?string
    {
        return match ($channel) {
            'organic' => 'organic',
            'social' => 'social',
            'email' => 'email',
            'referral' => 'referral',
            'ai' => 'ai_assistant',
            default => null,
        };
    }

    /**
     * سلسلة الاستعلام بلا ما يُعرّف شخصًا.
     *
     * الرابط قد يحمل بريدًا أو رمز دعوة أو رمز إعادة تعيين. تخزينها في
     * جدول تحليلات يجعل تسريب الجدول تسريب حسابات — والقيمة التحليلية
     * لوسم الحملة تبقى كاملة بدونها.
     */
    public function sanitizedQuery(Request $request): ?string
    {
        $query = $request->query();

        if ($query === []) {
            return null;
        }

        $forbidden = ['token', 'password', 'secret', 'signature', 'email', 'api_key', 'key', 'code', 'otp'];

        foreach ($query as $key => $value) {
            foreach ($forbidden as $needle) {
                if (str_contains(mb_strtolower((string) $key), $needle)) {
                    $query[$key] = __('[محجوب]');
                }
            }
        }

        return mb_substr(http_build_query($query), 0, 500) ?: null;
    }
}
