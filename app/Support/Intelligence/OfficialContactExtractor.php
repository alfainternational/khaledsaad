<?php

namespace App\Support\Intelligence;

class OfficialContactExtractor
{
    /**
     * @return array{contacts: array<int, array<string, mixed>>, social_links: array<int, string>, contact_pages: array<int, string>}
     */
    public function extract(string $html, string $sourceUrl): array
    {
        $contacts = [];
        $socialLinks = [];
        $contactPages = [];

        preg_match_all('/href=["\']mailto:([^"\']+)["\']/i', $html, $emailMatches);
        foreach ($emailMatches[1] ?? [] as $email) {
            $clean = strtolower(trim($email));
            if (! filter_var($clean, FILTER_VALIDATE_EMAIL) || ! $this->isBusinessEmail($clean)) {
                continue;
            }
            $contacts[] = $this->contact('official_email', $clean, $sourceUrl, true, str_starts_with($clean, 'info@'));
        }

        preg_match_all('/(?:\+?\d[\d\-\s\(\)]{7,}\d)/', strip_tags($html), $phoneMatches);
        foreach ($phoneMatches[0] ?? [] as $phone) {
            $normalized = preg_replace('/\s+/', ' ', trim((string) $phone)) ?: '';
            if ($normalized === '' || strlen(preg_replace('/\D/', '', $normalized) ?: '') < 8) {
                continue;
            }
            $contacts[] = $this->contact('official_phone', $normalized, $sourceUrl, true, false);
        }

        preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $hrefMatches);
        foreach ($hrefMatches[1] ?? [] as $href) {
            $href = trim($href);
            if ($href === '') {
                continue;
            }

            $absolute = $this->absolutizeUrl($href, $sourceUrl);
            if ($absolute === null) {
                continue;
            }

            if (preg_match('/wa\.me|whatsapp\.com/i', $absolute)) {
                $contacts[] = $this->contact('whatsapp', $absolute, $sourceUrl, true, false);
            }

            if ($this->isSocialUrl($absolute)) {
                $socialLinks[] = $absolute;
            }

            if (preg_match('/contact|about|team|location|locations|اتصل|تواصل|about-us|contact-us/i', $absolute)) {
                $contactPages[] = $absolute;
            }
        }

        if (str_contains($html, '<form') && preg_match('/contact|inquiry|quote|book|schedule|تواصل|استفسار|احجز/i', $html)) {
            $contacts[] = $this->contact('contact_form', $sourceUrl, $sourceUrl, true, false);
        }

        return [
            'contacts' => collect($contacts)->unique(fn (array $item) => $item['contact_type'].'|'.$item['contact_value'])->values()->all(),
            'social_links' => array_values(array_unique($socialLinks)),
            'contact_pages' => array_values(array_unique($contactPages)),
        ];
    }

    private function isBusinessEmail(string $email): bool
    {
        $local = strtolower((string) strtok($email, '@'));
        $allowed = ['info', 'sales', 'hello', 'contact', 'support', 'team', 'business', 'partnerships', 'marketing', 'admin', 'office', 'care', 'booking'];

        return in_array($local, $allowed, true)
            || str_contains($local, 'info')
            || str_contains($local, 'sales')
            || str_contains($local, 'contact')
            || str_contains($local, 'support');
    }

    private function isSocialUrl(string $url): bool
    {
        return preg_match('/instagram\.com|facebook\.com|linkedin\.com|tiktok\.com|x\.com|twitter\.com|youtube\.com/i', $url) === 1;
    }

    private function absolutizeUrl(string $href, string $base): ?string
    {
        if (preg_match('/^https?:\/\//i', $href)) {
            return $href;
        }

        if (str_starts_with($href, '//')) {
            return 'https:'.$href;
        }

        if (str_starts_with($href, '#') || str_starts_with(strtolower($href), 'javascript:')) {
            return null;
        }

        $parts = parse_url($base);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $scheme = $parts['scheme'];
        $host = $parts['host'];

        if (str_starts_with($href, '/')) {
            return $scheme.'://'.$host.$href;
        }

        return rtrim($scheme.'://'.$host, '/').'/'.$href;
    }

    /**
     * @return array<string, mixed>
     */
    private function contact(string $type, string $value, string $sourceUrl, bool $verified, bool $primary): array
    {
        return [
            'contact_type' => $type,
            'contact_value' => $value,
            'source_url' => $sourceUrl,
            'is_verified' => $verified,
            'is_primary' => $primary,
        ];
    }
}
