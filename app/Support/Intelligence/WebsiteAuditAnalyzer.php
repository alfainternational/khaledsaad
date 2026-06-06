<?php

namespace App\Support\Intelligence;

use Illuminate\Support\Str;

class WebsiteAuditAnalyzer
{
    public function __construct(
        private readonly RemotePageFetcher $fetcher,
        private readonly OfficialContactExtractor $contactExtractor,
    ) {}

    /**
     * @param  array<int, string>  $knownSocialLinks
     * @return array<string, mixed>
     */
    public function analyze(?string $url, string $sector, array $knownSocialLinks = []): array
    {
        $response = $this->fetcher->fetch($url);
        $findings = [];

        if (! ($response['ok'] ?? false)) {
            $findings[] = $this->finding(
                'website',
                'availability',
                'high',
                0.95,
                24,
                'موقعك لا يمكن الوصول إليه حالياً',
                'تعذّر فتح صفحتك الرئيسية، وهذا يوقف التقييم الفني والتجاري الدقيق.',
                'تأكد من الدومين والاستضافة أو من أي قيود على الوصول ثم أعد التحليل.',
                $response['url'] ?? $url,
            );

            return [
                'snapshot' => $response,
                'findings' => $findings,
                'contacts' => [],
                'discovered_social_links' => $knownSocialLinks,
                'raw_metrics' => [
                    'response_time_ms' => $response['duration_ms'] ?? null,
                    'status' => $response['status'] ?? null,
                ],
            ];
        }

        $html = (string) ($response['html'] ?? '');
        $title = $this->matchOne('/<title[^>]*>(.*?)<\/title>/is', $html);
        $description = $this->matchMetaDescription($html);
        $h1Count = preg_match_all('/<h1\b/i', $html) ?: 0;
        $hasViewport = preg_match('/<meta[^>]+name=["\']viewport["\']/i', $html) === 1;
        $hasPrivacy = preg_match('/privacy|الخصوصية/i', $html) === 1;
        $hasTerms = preg_match('/terms|الشروط|الاستخدام/i', $html) === 1;
        $hasContact = preg_match('/contact|اتصل|تواصل|whatsapp|phone|هاتف/i', $html) === 1;
        $ctaCount = preg_match_all('/book|quote|contact us|get started|start now|buy now|request|احجز|اطلب|ابدأ|تواصل/i', strip_tags($html)) ?: 0;
        $serviceLinks = preg_match_all('/href=["\'][^"\']*(service|services|pricing|solution|solutions|خدمات|الاسعار|الحلول)[^"\']*["\']/i', $html) ?: 0;
        $imagesCount = preg_match_all('/<img\b/i', $html) ?: 0;
        $altCount = preg_match_all('/<img\b[^>]*alt=["\'][^"\']*["\']/i', $html) ?: 0;
        $responseTime = (int) ($response['duration_ms'] ?? 0);
        $sizeKb = (int) round(strlen($html) / 1024);
        $extract = $this->contactExtractor->extract($html, (string) $response['url']);
        $contactEvidence = $this->crawlContactEvidence((string) $response['url'], $extract);
        $socialLinks = array_values(array_unique(array_merge(
            $knownSocialLinks,
            $extract['social_links'],
            $contactEvidence['social_links'],
        )));
        $contacts = array_values(array_unique(array_merge(
            $extract['contacts'],
            $contactEvidence['contacts'],
        ), SORT_REGULAR));
        $contactPages = array_values(array_unique(array_merge(
            $extract['contact_pages'],
            $contactEvidence['visited_pages'],
        )));
        $hasAbout = preg_match('/about|عن|من نحن|our story/i', $html) === 1;
        $hasFaq = preg_match('/faq|الاسئلة|الأسئلة|questions/i', $html) === 1;

        if ($responseTime > 2500) {
            $findings[] = $this->finding('website', 'performance', 'high', 0.84, 16, 'صفحتك الرئيسية بطيئة في التحميل', 'استغرقت الصفحة أكثر من 2.5 ثانية حتى بدأت بالظهور.', 'خفّف الصور والأكواد الثقيلة وفعّل التخزين المؤقت لتسريع الفتح.', (string) $response['url']);
        } elseif ($responseTime > 1200) {
            $findings[] = $this->finding('website', 'performance', 'medium', 0.71, 8, 'سرعة الصفحة متوسطة وتحتاج تحسيناً', 'الصفحة تعمل لكنها أبطأ من المستوى المريح في أول انطباع.', 'ابدأ بتخفيف العناصر الثقيلة في الصفحة الأولى ثم راقب التحسّن.', (string) $response['url']);
        }

        if (! str_starts_with((string) ($response['url'] ?? ''), 'https://')) {
            $findings[] = $this->finding('trust', 'ssl', 'high', 0.98, 18, 'موقعك لا يعمل عبر اتصال آمن (HTTPS)', 'غياب الاتصال الآمن يضعف ثقة الزائر ويظهر تنبيه أمان في المتصفح ويؤثر على ظهورك في البحث.', 'فعّل شهادة الأمان (SSL) ووجّه كل الزيارات إلى النسخة الآمنة من الموقع.', (string) $response['url']);
        }

        if ($title === '' || Str::length($title) < 20) {
            $findings[] = $this->finding('seo', 'title', 'high', 0.88, 12, 'عنوان صفحتك الرئيسية ضعيف أو ناقص', 'العنوان الحالي لا يشرح نشاطك بوضوح كافٍ لمحركات البحث ولا للزائر.', 'اكتب عنواناً واضحاً يجمع نشاطك والفائدة التي تقدّمها أو السوق الذي تستهدفه.', (string) $response['url']);
        }

        if ($description === '' || Str::length($description) < 80) {
            $findings[] = $this->finding('seo', 'meta_description', 'medium', 0.82, 8, 'الوصف التعريفي للصفحة غير كافٍ', 'الوصف الحالي قصير أو غائب، وهذا يضعف وضوح نتيجتك عند ظهورها في البحث.', 'اكتب وصفاً من 120 إلى 155 حرفاً يشرح قيمتك ويشجّع الزائر على النقر.', (string) $response['url']);
        }

        if ($h1Count === 0) {
            $findings[] = $this->finding('seo', 'heading_structure', 'medium', 0.86, 7, 'صفحتك الرئيسية بلا عنوان رئيسي واضح', 'غياب العنوان الرئيسي يجعل فهم محتوى الصفحة أصعب على الزائر وعلى محركات البحث.', 'أضف عنواناً رئيسياً واحداً يشرح الرسالة الأساسية للصفحة.', (string) $response['url']);
        }

        if (! $hasViewport) {
            $findings[] = $this->finding('website', 'mobile', 'high', 0.92, 13, 'عرض الموقع على الجوال غير مضمون', 'لم نجد الإعداد الذي يضبط عرض الصفحة على شاشات الجوال، وهذا يضرّ بتجربة زوارك من الهاتف.', 'اضبط الموقع ليظهر بشكل سليم ومتجاوب على شاشات الجوال.', (string) $response['url']);
        }

        if ($ctaCount === 0) {
            $findings[] = $this->finding('conversion', 'cta', 'high', 0.88, 16, 'لا يوجد زر إجراء واضح في الصفحة', 'الصفحة لا توجّه الزائر بسهولة إلى خطوة واضحة مثل الطلب أو الحجز أو التواصل.', 'أضف زر إجراء مباشراً في أعلى الصفحة وكرّره في الأقسام الأساسية.', (string) $response['url']);
        } elseif ($ctaCount === 1) {
            $findings[] = $this->finding('conversion', 'cta', 'medium', 0.64, 6, 'زر الإجراء موجود لكنه ضعيف الحضور', 'يوجد زر إجراء واحد لكنه لا يبدو واضحاً ومسيطراً على الصفحة.', 'قوِّ زر الإجراء الرئيسي واربطه بنتيجة أو خطوة واضحة.', (string) $response['url']);
        }

        if ($serviceLinks < 1 && in_array($sector, ['b2b_services', 'clinic', 'saas'], true)) {
            $findings[] = $this->finding('website', 'service_depth', 'medium', 0.69, 8, 'صفحات خدماتك قليلة التفصيل', 'لا توجد صفحات خدمات واضحة بما يكفي لإقناع الزائر أو لظهورها في البحث.', 'أنشئ صفحة مستقلة لكل خدمة رئيسية مع زر إجراء ودليل يثبت جودتك.', (string) $response['url']);
        }

        if (! $hasPrivacy || ! $hasTerms) {
            $findings[] = $this->finding('trust', 'policies', 'medium', 0.81, 9, 'صفحات السياسات والثقة ناقصة', 'لا يظهر بوضوح وجود صفحات الخصوصية أو شروط الاستخدام.', 'أظهر روابط الخصوصية وشروط الاستخدام بوضوح في أسفل الموقع.', (string) $response['url']);
        }

        if (! $hasContact) {
            $findings[] = $this->finding('trust', 'contact_presence', 'high', 0.84, 15, 'وسائل التواصل غير واضحة بما يكفي', 'غياب وسيلة تواصل ظاهرة يضعف الثقة ويقلّل تحويل الزائر إلى عميل.', 'أظهر رقم الهاتف أو البريد أو نموذج التواصل في أعلى الموقع وأسفله وفي الصفحة الرئيسية.', (string) $response['url']);
        }

        if ($imagesCount > 0 && $altCount / max(1, $imagesCount) < 0.5) {
            $findings[] = $this->finding('website', 'accessibility', 'medium', 0.74, 6, 'سهولة الاستخدام تحتاج تحسيناً', 'جزء كبير من الصور بلا نص بديل واضح يصفها، وهذا يضعف فهمها وظهورها في البحث.', 'أضف نصاً وصفياً مختصراً لكل صورة لها معنى أو ترتبط بزر إجراء.', (string) $response['url']);
        }

        if (! $hasAbout && ! $hasFaq) {
            $findings[] = $this->finding('ai_visibility', 'quote_ready_content', 'medium', 0.66, 7, 'المحتوى القابل للاقتباس ضعيف', 'غياب صفحة تعريف أو صفحة أسئلة شائعة يضعف ظهورك في محركات الإجابة الذكية.', 'أضف صفحة تعريف واضحة وأسئلة شائعة تشرح خدمتك ونتيجتها وما يميّزك.', (string) $response['url']);
        }

        if (empty($contactPages)) {
            $findings[] = $this->finding('lead_readiness', 'contact_paths', 'medium', 0.73, 8, 'طرق التواصل مع نشاطك محدودة', 'لم تظهر صفحات تواصل أو "من نحن" أو الفريق بشكل واضح في الموقع.', 'أضف صفحات للتواصل والتعريف والفريق، وأضف الفروع أو المواقع عند الحاجة.', (string) $response['url']);
        }

        return [
            'snapshot' => [
                'ok' => true,
                'url' => $response['url'],
                'status' => $response['status'],
                'error' => null,
                'title' => $title,
                'description' => $description,
                'response_time_ms' => $responseTime,
                'content_size_kb' => $sizeKb,
                'h1_count' => $h1Count,
                'cta_count' => $ctaCount,
                'service_links' => $serviceLinks,
                'contact_pages_count' => count($contactPages),
                'contact_pages_crawled' => count($contactEvidence['visited_pages']),
            ],
            'findings' => $findings,
            'contacts' => $contacts,
            'discovered_social_links' => $socialLinks,
            'raw_metrics' => [
                'response_time_ms' => $responseTime,
                'content_size_kb' => $sizeKb,
                'status' => $response['status'],
                'images_count' => $imagesCount,
                'images_with_alt' => $altCount,
                'contact_pages_crawled' => count($contactEvidence['visited_pages']),
                'contact_page_failures' => count($contactEvidence['failed_pages']),
            ],
        ];
    }

    /**
     * @param  array{contacts: array<int, array<string, mixed>>, social_links: array<int, string>, contact_pages: array<int, string>}  $seedExtract
     * @return array{contacts: array<int, array<string, mixed>>, social_links: array<int, string>, visited_pages: array<int, string>, failed_pages: array<int, string>}
     */
    private function crawlContactEvidence(string $baseUrl, array $seedExtract): array
    {
        $baseHost = parse_url($baseUrl, PHP_URL_HOST);
        $queue = collect($seedExtract['contact_pages'] ?? [])
            ->filter(fn (mixed $url): bool => is_string($url) && trim($url) !== '')
            ->filter(function (string $url) use ($baseHost): bool {
                $host = parse_url($url, PHP_URL_HOST);

                return $host !== null && $host === $baseHost;
            })
            ->unique()
            ->values()
            ->take(4)
            ->all();

        $visited = [];
        $failed = [];
        $contacts = [];
        $socialLinks = [];

        while ($queue !== [] && count($visited) < 4) {
            $pageUrl = array_shift($queue);
            if (! is_string($pageUrl) || $pageUrl === '' || in_array($pageUrl, $visited, true)) {
                continue;
            }

            $visited[] = $pageUrl;
            $page = $this->fetcher->fetch($pageUrl);
            if (! ($page['ok'] ?? false)) {
                $failed[] = $pageUrl;

                continue;
            }

            $pageExtract = $this->contactExtractor->extract((string) ($page['html'] ?? ''), $pageUrl);
            $contacts = array_merge($contacts, $pageExtract['contacts']);
            $socialLinks = array_merge($socialLinks, $pageExtract['social_links']);

            foreach ($pageExtract['contact_pages'] as $discoveredPage) {
                $host = parse_url($discoveredPage, PHP_URL_HOST);
                if ($host !== null && $host === $baseHost && ! in_array($discoveredPage, $visited, true) && ! in_array($discoveredPage, $queue, true)) {
                    $queue[] = $discoveredPage;
                }
            }
        }

        return [
            'contacts' => array_values(array_unique($contacts, SORT_REGULAR)),
            'social_links' => array_values(array_unique($socialLinks)),
            'visited_pages' => $visited,
            'failed_pages' => $failed,
        ];
    }

    private function matchOne(string $pattern, string $html): string
    {
        preg_match($pattern, $html, $matches);

        return isset($matches[1]) ? trim(strip_tags(html_entity_decode((string) $matches[1]))) : '';
    }

    private function matchMetaDescription(string $html): string
    {
        preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\']/i', $html, $matches);

        return isset($matches[1]) ? trim((string) $matches[1]) : '';
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
