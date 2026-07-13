<?php

namespace App\Support\Intelligence;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;

class RemotePageFetcher
{
    private readonly RemoteUrlGuard $urlGuard;

    public function __construct(
        ?RemoteUrlGuard $urlGuard = null,
    ) {
        $this->urlGuard = $urlGuard ?? app(RemoteUrlGuard::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function fetch(?string $url): array
    {
        $inspection = $this->urlGuard->inspect($url);
        $normalizedUrl = $inspection['normalized_url'];

        if ($inspection['allowed'] !== true || $normalizedUrl === null) {
            return [
                'ok' => false,
                'url' => $normalizedUrl ?? $url,
                'requested_url' => $url,
                'status' => null,
                'html' => '',
                'duration_ms' => null,
                'content_type' => null,
                'error' => $inspection['reason'] ?? 'invalid_url',
                'attempts' => [],
                'redirect_chain' => [],
            ];
        }

        $started = microtime(true);
        $lastResult = null;
        $attemptLog = [];

        foreach ($this->attemptProfiles($normalizedUrl) as $attempt) {
            try {
                $responseMeta = $this->requestWithRedirects($normalizedUrl, $attempt);
                $response = $responseMeta['response'];
                $effectiveUrl = $responseMeta['effective_url'];
                $bodyError = $response->successful() ? $this->bodyError($response) : null;

                $lastResult = [
                    'ok' => $response->successful() && $bodyError === null,
                    'url' => $effectiveUrl,
                    'requested_url' => $normalizedUrl,
                    'status' => $response->status(),
                    'html' => $bodyError === null ? $response->body() : '',
                    'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                    'content_type' => $response->header('Content-Type'),
                    'error' => $response->successful() ? $bodyError : 'http_'.$response->status(),
                    'attempts' => array_merge($attemptLog, [[
                        'profile' => $attempt['name'],
                        'status' => $response->status(),
                        'ok' => $response->successful() && $bodyError === null,
                    ]]),
                    'redirect_chain' => $responseMeta['redirect_chain'],
                ];

                if ($response->successful() || ! $this->shouldRetry($normalizedUrl, $response->status())) {
                    return $lastResult;
                }

                $attemptLog = $lastResult['attempts'];
            } catch (ConnectionException $exception) {
                $lastResult = [
                    'ok' => false,
                    'url' => $normalizedUrl,
                    'requested_url' => $normalizedUrl,
                    'status' => null,
                    'html' => '',
                    'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                    'content_type' => null,
                    'error' => $exception->getMessage(),
                    'attempts' => array_merge($attemptLog, [[
                        'profile' => $attempt['name'],
                        'status' => null,
                        'ok' => false,
                    ]]),
                    'redirect_chain' => [],
                ];

                $attemptLog = $lastResult['attempts'];
            } catch (\Throwable $exception) {
                return [
                    'ok' => false,
                    'url' => $normalizedUrl,
                    'requested_url' => $normalizedUrl,
                    'status' => null,
                    'html' => '',
                    'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                    'content_type' => null,
                    'error' => $exception->getMessage(),
                    'attempts' => array_merge($attemptLog, [[
                        'profile' => $attempt['name'],
                        'status' => null,
                        'ok' => false,
                    ]]),
                    'redirect_chain' => [],
                ];
            }
        }

        return $lastResult ?? [
            'ok' => false,
            'url' => $normalizedUrl,
            'requested_url' => $normalizedUrl,
            'status' => null,
            'html' => '',
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'content_type' => null,
            'error' => 'fetch_failed',
            'attempts' => [],
            'redirect_chain' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $attempt
     * @return array{response: Response, effective_url: string, redirect_chain: array<int, string>}
     */
    private function requestWithRedirects(string $normalizedUrl, array $attempt): array
    {
        $redirectChain = [];
        $currentUrl = $normalizedUrl;
        $maxBytes = max(1, (int) config('services.web_search.max_response_bytes', 2097152));

        for ($redirectCount = 0; $redirectCount < 5; $redirectCount++) {
            $currentInspection = $this->urlGuard->inspect($currentUrl);
            if ($currentInspection['allowed'] !== true) {
                throw new \RuntimeException($currentInspection['reason'] ?? 'blocked_request_target');
            }

            $curlOptions = [
                CURLOPT_HTTP_VERSION => $attempt['curl_http_version'],
            ];
            $resolve = $this->pinnedResolution($currentUrl, (array) ($currentInspection['resolved_ips'] ?? []));
            if ($resolve !== []) {
                $curlOptions[CURLOPT_RESOLVE] = $resolve;
            }

            $response = Http::timeout((int) $attempt['timeout'])
                ->connectTimeout((int) $attempt['connect_timeout'])
                ->withOptions([
                    'allow_redirects' => false,
                    'http_errors' => false,
                    'version' => $attempt['http_version'],
                    'curl' => $curlOptions,
                    'on_headers' => static function (ResponseInterface $response) use ($maxBytes): void {
                        $length = (int) $response->getHeaderLine('Content-Length');
                        if ($length > $maxBytes) {
                            throw new \RuntimeException('response_too_large');
                        }
                    },
                    'progress' => static function (int $downloadTotal, int $downloadedBytes) use ($maxBytes): void {
                        if ($downloadedBytes > $maxBytes || $downloadTotal > $maxBytes) {
                            throw new \RuntimeException('response_too_large');
                        }
                    },
                ])
                ->withHeaders($attempt['headers'])
                ->get($currentUrl);

            if (! $this->isRedirect($response)) {
                $effectiveUrl = (string) ($response->effectiveUri() ?? $currentUrl);
                $effectiveInspection = $this->urlGuard->inspect($effectiveUrl);

                if ($effectiveInspection['allowed'] !== true) {
                    throw new \RuntimeException($effectiveInspection['reason'] ?? 'blocked_redirect_target');
                }

                return [
                    'response' => $response,
                    'effective_url' => $effectiveInspection['normalized_url'] ?? $effectiveUrl,
                    'redirect_chain' => $redirectChain,
                ];
            }

            $nextUrl = $this->resolveRedirectUrl($currentUrl, (string) $response->header('Location'));
            $nextInspection = $this->urlGuard->inspect($nextUrl);

            if ($nextInspection['allowed'] !== true || ($nextInspection['normalized_url'] ?? null) === null) {
                throw new \RuntimeException($nextInspection['reason'] ?? 'blocked_redirect_target');
            }

            $currentUrl = $nextInspection['normalized_url'];
            $redirectChain[] = $currentUrl;
        }

        throw new \RuntimeException('redirect_limit_exceeded');
    }

    private function isRedirect(Response $response): bool
    {
        return in_array($response->status(), [301, 302, 303, 307, 308], true)
            && filled($response->header('Location'));
    }

    private function resolveRedirectUrl(string $currentUrl, string $location): string
    {
        $location = trim($location);

        if ($location === '') {
            return $currentUrl;
        }

        if (filter_var($location, FILTER_VALIDATE_URL)) {
            return $location;
        }

        return (string) UriResolver::resolve(new Uri($currentUrl), new Uri($location));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function attemptProfiles(string $url): array
    {
        $standardHeaders = [
            'User-Agent' => 'KhaledSaadIntelligenceBot/1.0 (+marketing audit)',
            'Accept-Language' => 'ar,en;q=0.8',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ];

        $profiles = [[
            'name' => 'standard',
            'headers' => $standardHeaders,
            'timeout' => 12,
            'connect_timeout' => 6,
            'http_version' => 1.1,
            'curl_http_version' => CURL_HTTP_VERSION_1_1,
        ]];

        if ($this->isSocialUrl($url)) {
            $profiles[] = [
                'name' => 'browser_fallback',
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'ar,en-US;q=0.9,en;q=0.8',
                    'Cache-Control' => 'no-cache',
                    'Pragma' => 'no-cache',
                    'Upgrade-Insecure-Requests' => '1',
                ],
                'timeout' => 15,
                'connect_timeout' => 8,
                'http_version' => 1.1,
                'curl_http_version' => CURL_HTTP_VERSION_1_1,
            ];
        }

        return $profiles;
    }

    private function shouldRetry(string $url, ?int $status): bool
    {
        if (! $this->isSocialUrl($url)) {
            return false;
        }

        return $status === null || in_array($status, [403, 429, 999], true);
    }

    private function isSocialUrl(string $url): bool
    {
        return preg_match('/instagram\.com|facebook\.com|linkedin\.com|tiktok\.com|x\.com|twitter\.com|youtube\.com/i', $url) === 1;
    }

    private function bodyError(Response $response): ?string
    {
        $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        if (! in_array($contentType, ['text/html', 'application/xhtml+xml'], true)) {
            return 'unsupported_content_type';
        }

        $maxBytes = max(1, (int) config('services.web_search.max_response_bytes', 2097152));
        if (strlen($response->body()) > $maxBytes) {
            return 'response_too_large';
        }

        return null;
    }

    /**
     * Pin cURL to the addresses that passed the guard so DNS cannot change
     * between validation and connection.
     *
     * @param  array<int, string>  $resolvedIps
     * @return array<int, string>
     */
    private function pinnedResolution(string $url, array $resolvedIps): array
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [];
        }

        $port = (int) ($parts['port'] ?? (($parts['scheme'] ?? '') === 'http' ? 80 : 443));

        return array_map(
            static fn (string $ip): string => sprintf('%s:%d:%s', $host, $port, str_contains($ip, ':') ? '['.$ip.']' : $ip),
            array_values(array_filter($resolvedIps, 'is_string')),
        );
    }
}
