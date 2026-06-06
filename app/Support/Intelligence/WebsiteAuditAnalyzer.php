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
                'الموقع غير قابل للوصول حالياً',
                'تعذر جلب الصفحة الأساسية، ما يوقف التقييم الفني والتجاري الدقيق.',
                'تحقق من الدومين، الاستضافة، أو قيود الوصول ثم أعد التحليل.',
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
            $findings[] = $this->finding('website', 'performance', 'high', 0.84, 16, 'زمن استجابة الصفحة الأساسية بطيء', 'الصفحة استغرقت أكثر من 2.5 ثانية في الاستجابة الأولية.', 'خفّف السكربتات والصور وراجع طبقة التخزين المؤقت.', (string) $response['url']);
        } elseif ($responseTime > 1200) {
            $findings[] = $this->finding('website', 'performance', 'medium', 0.71, 8, 'الاستجابة متوسطة وتحتاج تحسيناً', 'الصفحة ليست معطّلة لكنها أبطأ من المستوى المريح في أول انطباع.', 'ابدأ بالموارد الثقيلة في الصفحة الأولى ثم راقب التحسن.', (string) $response['url']);
        }

        if (! str_starts_with((string) ($response['url'] ?? ''), 'https://')) {
            $findings[] = $this->finding('trust', 'ssl', 'high', 0.98, 18, 'الموقع لا يعمل عبر HTTPS', 'غياب HTTPS يضعف الثقة ويؤثر على المتصفح ومحركات البحث.', 'فعّل SSL بشكل كامل ووجّه كل الزيارات إلى HTTPS.', (string) $response['url']);
        }

        if ($title === '' || Str::length($title) < 20) {
            $findings[] = $this->finding('seo', 'title', 'high', 0.88, 12, 'عنوان الصفحة الأساسية ضعيف أو ناقص', 'العنوان الحالي لا يشرح النشاط بوضوح كافٍ لمحرك البحث أو للمستخدم.', 'اكتب عنواناً واضحاً يضم النشاط والفائدة أو السوق المستهدف.', (string) $response['url']);
        }

        if ($description === '' || Str::length($description) < 80) {
            $findings[] = $this->finding('seo', 'meta_description', 'medium', 0.82, 8, 'الوصف التعريفي غير كافٍ', 'الوصف الحالي قصير أو غائب، ما يضعف وضوح النتيجة في البحث.', 'اكتب وصفاً من 120 إلى 155 حرفاً يشرح القيمة ويدفع للنقر.', (string) $response['url']);
        }

        if ($h1Count === 0) {
            $findings[] = $this->finding('seo', 'heading_structure', 'medium', 0.86, 7, 'الصفحة الأساسية بلا H1 واضح', 'غياب العنوان الرئيسي البنيوي يضعف الفهم الهيكلي للمحتوى.', 'أضف H1 واحداً يشرح الرسالة الأساسية للصفحة.', (string) $response['url']);
        }

        if (! $hasViewport) {
            $findings[] = $this->finding('website', 'mobile', 'high', 0.92, 13, 'توافق الجوال غير مضمون', 'لم يتم العثور على viewport meta tag واضح، ما يهدد تجربة الجوال.', 'أضف viewport واضبط layout responsive في أول الشاشة.', (string) $response['url']);
        }

        if ($ctaCount === 0) {
            $findings[] = $this->finding('conversion', 'cta', 'high', 0.88, 16, 'لا يوجد CTA واضح في المحتوى الظاهر', 'الصفحة لا تدفع الزائر إلى خطوة قرار صريحة بسهولة.', 'أضف CTA مباشر في الهيرو وأعد تكراره في الأقسام الأساسية.', (string) $response['url']);
        } elseif ($ctaCount === 1) {
            $findings[] = $this->finding('conversion', 'cta', 'medium', 0.64, 6, 'الـ CTA موجود لكنه خفيف الحضور', 'يوجد CTA محدود لكنه لا يبدو مسيطراً على رحلة الصفحة.', 'قوّ النداء الرئيسي واربطه بنتيجة أو خطوة زمنية واضحة.', (string) $response['url']);
        }

        if ($serviceLinks < 1 && in_array($sector, ['b2b_services', 'clinic', 'saas'], true)) {
            $findings[] = $this->finding('website', 'service_depth', 'medium', 0.69, 8, 'عمق صفحات الخدمات محدود', 'لا تظهر بنية خدمات واضحة تكفي للإقناع أو للفهرسة.', 'أنشئ صفحة خدمة مستقلة لكل عرض رئيسي مع CTA ودليل ثقة.', (string) $response['url']);
        }

        if (! $hasPrivacy || ! $hasTerms) {
            $findings[] = $this->finding('trust', 'policies', 'medium', 0.81, 9, 'إشارات السياسات والثقة ناقصة', 'وجود صفحات السياسات أو الشروط غير واضح بما يكفي.', 'أظهر روابط الخصوصية والشروط بوضوح في الفوتر.', (string) $response['url']);
        }

        if (! $hasContact) {
            $findings[] = $this->finding('trust', 'contact_presence', 'high', 0.84, 15, 'وسائل التواصل غير واضحة بما يكفي', 'غياب إشارات التواصل يضعف الثقة والتحويل.', 'أظهر الهاتف أو البريد أو النموذج الرسمي في الهيدر أو الفوتر والصفحة الرئيسية.', (string) $response['url']);
        }

        if ($imagesCount > 0 && $altCount / max(1, $imagesCount) < 0.5) {
            $findings[] = $this->finding('website', 'accessibility', 'medium', 0.74, 6, 'إمكانية الوصول تحتاج تحسيناً', 'جزء كبير من الصور لا يحمل alt واضحاً، ما يضعف الوصول والفهم.', 'أضف alt وصفي لكل صورة تخدم معنى أو CTA.', (string) $response['url']);
        }

        if (! $hasAbout && ! $hasFaq) {
            $findings[] = $this->finding('ai_visibility', 'quote_ready_content', 'medium', 0.66, 7, 'المحتوى القابل للاقتباس ضعيف', 'غياب صفحات تعريفية أو FAQ يضعف جاهزية العلامة للتمثيل الواضح في بيئات الإجابة.', 'أضف صفحة تعريف واضحة وأسئلة شائعة توضح الخدمة والنتيجة والتميّز.', (string) $response['url']);
        }

        if (empty($contactPages)) {
            $findings[] = $this->finding('lead_readiness', 'contact_paths', 'medium', 0.73, 8, 'مسارات التواصل الرسمية محدودة', 'لم تظهر صفحات تواصل/عنّا/فريق بشكل واضح ضمن البنية.', 'أضف صفحات تواصل وتعريف وفريق أو مواقع/فروع حين تنطبق.', (string) $response['url']);
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
