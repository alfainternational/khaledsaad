<?php

namespace App\Support\Intelligence;

use Illuminate\Support\Str;

class SocialAuditAnalyzer
{
    public function __construct(
        private readonly RemotePageFetcher $fetcher,
    ) {}

    /**
     * @param  array<int, string>  $socialLinks
     * @param  array<int, array<string, mixed>>  $manualProfiles
     * @return array<string, mixed>
     */
    public function analyze(array $socialLinks, ?string $primaryDomain = null, array $manualProfiles = []): array
    {
        $profiles = [];
        $findings = [];
        $manualProfiles = $this->normalizeManualProfiles($manualProfiles);
        $manualProfileUrls = array_values(array_filter(array_map(
            fn (array $profile): string => (string) ($profile['url'] ?? ''),
            $manualProfiles,
        )));
        $manualProfilesWithoutUrls = array_values(array_filter(
            $manualProfiles,
            fn (array $profile): bool => (string) ($profile['url'] ?? '') === '',
        ));
        $requestedLinks = array_values(array_unique(array_filter(array_merge(
            $socialLinks,
            $manualProfileUrls,
        ))));
        $requestedProfileSources = count($requestedLinks) + count($manualProfilesWithoutUrls);
        $failedProfiles = 0;
        $automatedAccessibleProfiles = 0;
        $manuallyVerifiedProfiles = 0;

        foreach ($requestedLinks as $url) {
            $manualProfile = $this->manualProfileForUrl($manualProfiles, $url);
            $response = $this->fetcher->fetch($url);
            $network = $this->networkLabel($url);

            if (! ($response['ok'] ?? false)) {
                if ($manualProfile !== null) {
                    $profiles[] = $this->manualProfilePayload($manualProfile, $url, $network, $response);
                    $manuallyVerifiedProfiles++;
                    continue;
                }

                $failedProfiles++;
                $findings[] = $this->finding(
                    'social',
                    'access',
                    'medium',
                    0.45,
                    6,
                    'تعذر قراءة صفحة '.$network.' العامة',
                    'بيانات الحساب غير متاحة مباشرة من الصفحة العامة، لذلك التقييم هنا محدود الثقة. سبب الجلب: '.($response['error'] ?? 'unknown'),
                    'راجع الرابط الرسمي أو أضف تأكيداً يدوياً لنشاط الحساب ومحتواه.',
                    $url,
                );
                continue;
            }

            $html = (string) ($response['html'] ?? '');
            $automatedAccessibleProfiles++;
            if ($manualProfile !== null) {
                $manuallyVerifiedProfiles++;
            }
            $title = $this->matchOne('/<title[^>]*>(.*?)<\/title>/is', $html);
            if ($title === '') {
                $title = $this->matchMetaTag($html, 'property', 'og:title')
                    ?: $this->matchMetaTag($html, 'name', 'twitter:title');
            }

            $description = $this->matchMetaDescription($html);
            $hasCta = preg_match('/book|contact|visit|shop|link in bio|واتساب|احجز|تواصل|اطلب|ابدأ/i', strip_tags($html)) === 1;
            $mentionsDomain = $primaryDomain !== null
                && $primaryDomain !== ''
                && preg_match('/'.preg_quote($this->hostFromDomain($primaryDomain), '/').'/i', $html) === 1;
            $clarity = Str::length($description) >= 60;
            $resolvedUrl = $this->matchCanonicalUrl($html) ?: $url;
            $manualNetwork = $manualProfile !== null ? (string) ($manualProfile['network'] ?? '') : '';
            $manualHandle = $manualProfile !== null ? (string) ($manualProfile['handle'] ?? '') : '';
            $manualTitle = $manualProfile !== null ? (string) ($manualProfile['title'] ?? '') : '';
            $manualDescription = $manualProfile !== null ? (string) ($manualProfile['description'] ?? '') : '';
            $manualCta = $manualProfile !== null ? (string) ($manualProfile['primary_cta'] ?? '') : '';
            $manualLinksBack = $manualProfile !== null ? (bool) ($manualProfile['links_back_to_site'] ?? false) : false;
            $manualNotes = $manualProfile !== null ? (string) ($manualProfile['verification_notes'] ?? '') : '';

            $profiles[] = [
                'network' => $manualNetwork !== '' ? $manualNetwork : $network,
                'url' => $resolvedUrl,
                'handle' => $manualHandle,
                'title' => $manualTitle !== '' ? $manualTitle : $title,
                'description' => $manualDescription !== '' ? $manualDescription : $description,
                'has_cta' => $hasCta || ($manualCta !== ''),
                'primary_cta' => $manualCta,
                'links_back_to_site' => $mentionsDomain || $manualLinksBack,
                'fetch_error' => $response['error'],
                'attempts' => $response['attempts'] ?? [],
                'verification_source' => $manualProfile !== null ? 'hybrid' : 'automated',
                'verification_notes' => $manualNotes,
            ];

            if (! $clarity) {
                $findings[] = $this->finding(
                    'social',
                    'bio_clarity',
                    'medium',
                    0.70,
                    8,
                    'رسالة الحساب على '.$network.' غير واضحة بما يكفي',
                    'الوصف العام لا يشرح لمن هذا الحساب وما النتيجة التي يقدمها بوضوح.',
                    'أعد كتابة bio يشرح الشريحة والنتيجة ووسيلة القرار في جملة مباشرة.',
                    $url,
                );
            }

            if (! $hasCta) {
                $findings[] = $this->finding(
                    'social',
                    'cta',
                    'medium',
                    0.63,
                    7,
                    'الحساب لا يوجّه إلى خطوة قرار واضحة',
                    'الحساب حاضر لكنه لا يدفع إلى تواصل أو حجز أو زيارة موقع بشكل صريح.',
                    'أضف CTA ثابتاً في bio والمحتوى المثبت أو الروابط الأساسية.',
                    $url,
                );
            }

            if (! $mentionsDomain && $primaryDomain) {
                $findings[] = $this->finding(
                    'social',
                    'site_linking',
                    'medium',
                    0.58,
                    6,
                    'الربط بين السوشيال والموقع ضعيف',
                    'لم يظهر ربط واضح من الحساب إلى الموقع الأساسي، ما يضعف مسار التحويل.',
                    'اربط الحساب بالموقع الرسمي أو بصفحة قرار واضحة تتبع نفس العرض.',
                    $url,
                );
            }
        }

        foreach ($manualProfiles as $manualProfile) {
            $url = (string) ($manualProfile['url'] ?? '');
            if ($url !== '' && in_array($url, $requestedLinks, true)) {
                continue;
            }

            if ($url === '' && trim((string) ($manualProfile['title'] ?? $manualProfile['description'] ?? '')) === '') {
                continue;
            }

            $profiles[] = $this->manualProfilePayload(
                $manualProfile,
                $url !== '' ? $url : null,
                $this->networkLabel($url !== '' ? $url : (string) ($manualProfile['network'] ?? 'social')),
                null,
            );
            $manuallyVerifiedProfiles++;
        }

        if ($requestedProfileSources === 0) {
            $findings[] = $this->finding(
                'social',
                'presence',
                'high',
                0.95,
                15,
                'لا توجد روابط سوشيال رسمية مضافة للمشروع',
                'غياب الروابط الرسمية يمنع قياس الحضور والربط بين القنوات.',
                'أضف الروابط الرسمية أو اترك المجال فارغاً عمداً إذا كانت الاستراتيجية بلا سوشيال.',
                null,
            );
        } elseif ($requestedProfileSources === 1) {
            $findings[] = $this->finding(
                'social',
                'coverage',
                'low',
                0.55,
                4,
                'الحضور الاجتماعي محدود القنوات',
                'وجود قناة واحدة لا يعني ضعفاً دائماً لكنه يقلل وضوح الصورة التسويقية.',
                'تأكد أن القناة الحالية كافية فعلاً لشريحتك، أو وسّع حضورك المدروس عند الحاجة.',
                $requestedLinks[0] ?? null,
            );
        }

        return [
            'profiles' => $profiles,
            'findings' => $findings,
            'analysis_meta' => [
                'requested_profiles' => $requestedProfileSources,
                'accessible_profiles' => count($profiles),
                'failed_profiles' => $failedProfiles,
                'automated_accessible_profiles' => $automatedAccessibleProfiles,
                'manual_verified_profiles' => $manuallyVerifiedProfiles,
                'accessible_networks' => array_values(array_unique(array_map(
                    fn (array $profile): string => (string) ($profile['network'] ?? 'social'),
                    $profiles,
                ))),
            ],
        ];
    }

    private function networkLabel(string $url): string
    {
        return match (true) {
            str_contains($url, 'instagram.com') => 'Instagram',
            str_contains($url, 'facebook.com') => 'Facebook',
            str_contains($url, 'linkedin.com') => 'LinkedIn',
            str_contains($url, 'tiktok.com') => 'TikTok',
            str_contains($url, 'youtube.com') => 'YouTube',
            str_contains($url, 'x.com'), str_contains($url, 'twitter.com') => 'X',
            default => 'social',
        };
    }

    private function matchOne(string $pattern, string $html): string
    {
        preg_match($pattern, $html, $matches);

        return isset($matches[1]) ? trim(strip_tags(html_entity_decode((string) $matches[1]))) : '';
    }

    private function matchMetaDescription(string $html): string
    {
        preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\']/i', $html, $matches);

        if (isset($matches[1])) {
            return trim((string) $matches[1]);
        }

        return $this->matchMetaTag($html, 'property', 'og:description')
            ?: $this->matchMetaTag($html, 'name', 'twitter:description')
            ?: '';
    }

    private function matchMetaTag(string $html, string $attribute, string $value): string
    {
        $xpath = $this->htmlXPath($html);
        if (! $xpath) {
            return '';
        }

        $nodes = $xpath->query('//meta[@'.$attribute.'="'.$value.'"]');
        if ($nodes === false || $nodes->length === 0) {
            return '';
        }

        $content = $nodes->item(0)?->attributes?->getNamedItem('content')?->nodeValue;

        return is_string($content) ? trim(html_entity_decode($content)) : '';
    }

    private function matchCanonicalUrl(string $html): string
    {
        $xpath = $this->htmlXPath($html);
        if (! $xpath) {
            return '';
        }

        $nodes = $xpath->query('//link[@rel="canonical"]');
        if ($nodes === false || $nodes->length === 0) {
            return '';
        }

        $href = $nodes->item(0)?->attributes?->getNamedItem('href')?->nodeValue;

        return is_string($href) ? trim($href) : '';
    }

    private function hostFromDomain(string $domain): string
    {
        $domain = trim($domain);
        $host = parse_url($domain, PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            return $host;
        }

        $host = parse_url('https://'.$domain, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : $domain;
    }

    /**
     * @param  array<int, array<string, mixed>>  $manualProfiles
     * @return array<int, array<string, mixed>>
     */
    private function normalizeManualProfiles(array $manualProfiles): array
    {
        return collect($manualProfiles)
            ->filter(fn (mixed $profile): bool => is_array($profile))
            ->map(function (array $profile): array {
                return [
                    'network' => trim((string) ($profile['network'] ?? '')),
                    'url' => trim((string) ($profile['url'] ?? '')),
                    'handle' => trim((string) ($profile['handle'] ?? '')),
                    'title' => trim((string) ($profile['title'] ?? '')),
                    'description' => trim((string) ($profile['description'] ?? '')),
                    'primary_cta' => trim((string) ($profile['primary_cta'] ?? '')),
                    'links_back_to_site' => (bool) ($profile['links_back_to_site'] ?? false),
                    'verification_notes' => trim((string) ($profile['verification_notes'] ?? '')),
                ];
            })
            ->filter(function (array $profile): bool {
                return collect($profile)
                    ->except('links_back_to_site')
                    ->contains(fn (mixed $field): bool => is_string($field) && $field !== '');
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $manualProfiles
     * @return array<string, mixed>|null
     */
    private function manualProfileForUrl(array $manualProfiles, string $url): ?array
    {
        foreach ($manualProfiles as $profile) {
            if (($profile['url'] ?? '') === $url) {
                return $profile;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $manualProfile
     * @param  array<string, mixed>|null  $response
     * @return array<string, mixed>
     */
    private function manualProfilePayload(array $manualProfile, ?string $url, string $network, ?array $response): array
    {
        return [
            'network' => $manualProfile['network'] !== '' ? $manualProfile['network'] : $network,
            'url' => $url ?? ($manualProfile['url'] ?? ''),
            'handle' => $manualProfile['handle'] ?? '',
            'title' => $manualProfile['title'] ?? '',
            'description' => $manualProfile['description'] ?? '',
            'has_cta' => ($manualProfile['primary_cta'] ?? '') !== '',
            'primary_cta' => $manualProfile['primary_cta'] ?? '',
            'links_back_to_site' => (bool) ($manualProfile['links_back_to_site'] ?? false),
            'fetch_error' => $response['error'] ?? null,
            'attempts' => $response['attempts'] ?? [],
            'verification_source' => 'manual',
            'verification_notes' => $manualProfile['verification_notes'] ?? '',
        ];
    }

    private function htmlXPath(string $html): ?\DOMXPath
    {
        if (trim($html) === '') {
            return null;
        }

        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $loaded ? new \DOMXPath($document) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function finding(
        string $area,
        string $subcategory,
        string $severity,
        float $confidence,
        int $scoreImpact,
        string $title,
        string $evidence,
        string $recommendation,
        ?string $sourceUrl,
    ): array {
        return [
            'area' => $area,
            'subcategory' => $subcategory,
            'severity' => $severity,
            'confidence' => $confidence,
            'score_impact' => $scoreImpact,
            'title' => $title,
            'evidence' => $evidence,
            'recommendation' => $recommendation,
            'source_url' => $sourceUrl,
            'meta' => [],
        ];
    }
}
