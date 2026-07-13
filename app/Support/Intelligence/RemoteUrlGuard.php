<?php

namespace App\Support\Intelligence;

class RemoteUrlGuard
{
    /**
     * @return array{allowed: bool, reason: ?string, normalized_url: ?string, resolved_ips: array<int, string>}
     */
    public function inspect(?string $url): array
    {
        $normalizedUrl = $this->normalizeUrl($url);

        if ($normalizedUrl === null) {
            return [
                'allowed' => false,
                'reason' => 'invalid_url',
                'normalized_url' => null,
                'resolved_ips' => [],
            ];
        }

        $parts = parse_url($normalizedUrl);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $user = (string) ($parts['user'] ?? '');
        $pass = (string) ($parts['pass'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            return $this->blocked('blocked_scheme', $normalizedUrl);
        }

        if ($host === '') {
            return $this->blocked('blocked_host', $normalizedUrl);
        }

        if ($user !== '' || $pass !== '') {
            return $this->blocked('blocked_credentials', $normalizedUrl);
        }

        if ($port !== null && ! in_array($port, [80, 443, 8080, 8443], true)) {
            return $this->blocked('blocked_port', $normalizedUrl);
        }

        if ($this->isLocalHost($host)) {
            return $this->blocked('blocked_private_host', $normalizedUrl);
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $this->isPublicIp($host)
                ? $this->allowed($normalizedUrl, [$host])
                : $this->blocked('blocked_private_ip', $normalizedUrl);
        }

        if (! str_contains($host, '.')) {
            return $this->blocked('blocked_unqualified_host', $normalizedUrl);
        }

        $resolvedIps = $this->resolvedIps($host);
        foreach ($resolvedIps as $ip) {
            if (! $this->isPublicIp($ip)) {
                return $this->blocked('blocked_private_resolution', $normalizedUrl);
            }
        }

        return $this->allowed($normalizedUrl, $resolvedIps);
    }

    private function normalizeUrl(?string $url): ?string
    {
        $url = is_string($url) ? trim($url) : '';
        if ($url === '') {
            return null;
        }

        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = 'https://'.$url;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    private function isLocalHost(string $host): bool
    {
        $blockedHosts = [
            'localhost',
            'metadata.google.internal',
            'metadata.google.internal.',
        ];

        if (in_array($host, $blockedHosts, true)) {
            return true;
        }

        return str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.localdomain')
            || str_ends_with($host, '.internal');
    }

    /**
     * @return array<int, string>
     */
    private function resolvedIps(string $host): array
    {
        $resolved = [];

        $ipv4 = gethostbynamel($host) ?: [];
        foreach ($ipv4 as $ip) {
            if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                $resolved[] = $ip;
            }
        }

        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    $ip = $record['ipv6'] ?? null;
                    if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                        $resolved[] = $ip;
                    }
                }
            }
        }

        return array_values(array_unique($resolved));
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    /**
     * @param  array<int, string>  $resolvedIps
     * @return array{allowed: bool, reason: ?string, normalized_url: ?string, resolved_ips: array<int, string>}
     */
    private function allowed(string $normalizedUrl, array $resolvedIps = []): array
    {
        return [
            'allowed' => true,
            'reason' => null,
            'normalized_url' => $normalizedUrl,
            'resolved_ips' => $resolvedIps,
        ];
    }

    /**
     * @return array{allowed: bool, reason: ?string, normalized_url: ?string, resolved_ips: array<int, string>}
     */
    private function blocked(string $reason, ?string $normalizedUrl): array
    {
        return [
            'allowed' => false,
            'reason' => $reason,
            'normalized_url' => $normalizedUrl,
            'resolved_ips' => [],
        ];
    }
}
