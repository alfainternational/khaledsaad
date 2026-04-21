<?php

namespace App\Application\Integration;

use App\Contracts\CloudClientContract;
use App\Domain\Integration\Exceptions\CloudIntegrationException;
use App\Domain\Integration\Services\CloudIntegrationGate;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * نقطة الدخول الموصى بها لطلبات تكامل السحابة من التطبيق (بعد التحقق من الباقة والـ flag والحد).
 */
final class CloudIntegrationService
{
    public function __construct(
        private readonly CloudClientContract $client,
        private readonly CloudIntegrationGate $gate,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $extraHeaders
     * @return array<string, mixed>
     */
    public function get(Workspace $workspace, ?User $user, string $path, array $query = [], array $extraHeaders = []): array
    {
        $this->gate->assertAllows($workspace, $user);

        $headers = array_merge(
            CloudWorkspaceOutboundHeaders::forWorkspace($workspace),
            $extraHeaders
        );

        return $this->withRateLimit($workspace, function () use ($path, $query, $headers): array {
            return $this->client->get($path, $query, $headers);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $extraHeaders
     * @return array<string, mixed>
     */
    public function post(Workspace $workspace, ?User $user, string $path, array $payload = [], array $extraHeaders = []): array
    {
        $this->gate->assertAllows($workspace, $user);

        $headers = array_merge(
            CloudWorkspaceOutboundHeaders::forWorkspace($workspace),
            $extraHeaders
        );

        return $this->withRateLimit($workspace, function () use ($path, $payload, $headers): array {
            return $this->client->post($path, $payload, $headers);
        });
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function withRateLimit(Workspace $workspace, callable $callback): array
    {
        $key = 'cloud:outbound:workspace:'.$workspace->getKey();
        $maxAttempts = (int) config('cloud.rate_limit_per_minute', 60);

        $result = RateLimiter::attempt($key, $maxAttempts, $callback, 60);

        if ($result === false) {
            throw CloudIntegrationException::rateLimited();
        }

        /** @var array<string, mixed> $result */
        return $result;
    }
}
