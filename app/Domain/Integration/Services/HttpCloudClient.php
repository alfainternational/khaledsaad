<?php

namespace App\Domain\Integration\Services;

use App\Contracts\CloudClientContract;
use App\Domain\Integration\Exceptions\CloudIntegrationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class HttpCloudClient implements CloudClientContract
{
    public function configured(): bool
    {
        return (bool) config('cloud.enabled', false)
            && filled(config('cloud.base_url'));
    }

    public function get(string $path, array $query = [], array $headers = []): array
    {
        return $this->send('get', $path, $query, [], $headers);
    }

    public function post(string $path, array $payload = [], array $headers = []): array
    {
        return $this->send('post', $path, [], $payload, $headers);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $query, array $payload, array $headers): array
    {
        if (! $this->configured()) {
            throw CloudIntegrationException::configurationMissing();
        }

        $base = rtrim((string) config('cloud.base_url'), '/');
        $timeout = (float) config('cloud.timeout', 10);
        $connectTimeout = (float) config('cloud.connect_timeout', 5);
        $token = config('cloud.token');
        $maxAttempts = (int) config('cloud.max_attempts', 3);
        $retryDelayMs = (int) config('cloud.retry_delay_ms', 200);
        $correlationId = (string) Str::ulid();

        $mergedHeaders = array_merge(
            [
                'X-Correlation-Id' => $correlationId,
                'X-Request-Id' => $correlationId,
            ],
            $headers
        );

        $channel = (string) config('cloud.log_channel', 'stack');
        $exposeDetail = (bool) config('cloud.expose_error_detail', false) || (bool) config('app.debug', false);

        $lastThrowable = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $started = microtime(true);

            try {
                $pending = Http::baseUrl($base)
                    ->timeout($timeout)
                    ->connectTimeout($connectTimeout)
                    ->acceptJson()
                    ->withHeaders($mergedHeaders);

                if (filled($token)) {
                    $pending = $pending->withToken((string) $token);
                }

                $response = match (strtolower($method)) {
                    'get' => $pending->get($path, $query),
                    'post' => $pending->post($path, $payload),
                    default => throw new CloudIntegrationException('Unsupported HTTP method: '.$method),
                };

                $durationMs = (int) round((microtime(true) - $started) * 1000);

                $this->logOutcome($channel, $correlationId, $method, $path, $mergedHeaders, $response, $durationMs, $attempt);

                if ($response->successful()) {
                    /** @var array<string, mixed>|null $json */
                    $json = $response->json();

                    return is_array($json) ? $json : [];
                }

                if ($this->shouldRetryHttpStatus($response->status()) && $attempt < $maxAttempts) {
                    $this->sleepMs($retryDelayMs * $attempt);

                    continue;
                }

                throw CloudIntegrationException::fromFailedResponse($response, $exposeDetail);
            } catch (CloudIntegrationException $e) {
                throw $e;
            } catch (ConnectionException $e) {
                $lastThrowable = $e;
                $this->logFailure($channel, $correlationId, $method, $path, $attempt, $e);

                if ($attempt < $maxAttempts) {
                    $this->sleepMs($retryDelayMs * $attempt);

                    continue;
                }

                throw CloudIntegrationException::fromConnection($e);
            } catch (Throwable $e) {
                $lastThrowable = $e;
                $this->logFailure($channel, $correlationId, $method, $path, $attempt, $e);

                if ($attempt < $maxAttempts && $this->isRetryableThrowable($e)) {
                    $this->sleepMs($retryDelayMs * $attempt);

                    continue;
                }

                throw new CloudIntegrationException(
                    'فشل طلب تكامل السحابة: '.$e->getMessage(),
                    0,
                    $e
                );
            }
        }

        if ($lastThrowable instanceof Throwable) {
            throw CloudIntegrationException::fromConnection($lastThrowable);
        }

        throw new CloudIntegrationException('فشل طلب تكامل السحابة بعد عدة محاولات.');
    }

    private function shouldRetryHttpStatus(int $status): bool
    {
        return $status >= 500 && $status <= 599;
    }

    private function isRetryableThrowable(Throwable $e): bool
    {
        return $e instanceof ConnectionException;
    }

    private function sleepMs(int $ms): void
    {
        if ($ms <= 0) {
            return;
        }

        usleep($ms * 1000);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function logOutcome(
        string $channel,
        string $correlationId,
        string $method,
        string $path,
        array $headers,
        Response $response,
        int $durationMs,
        int $attempt,
    ): void {
        $workspacePublic = $headers['X-Workspace-Public-Id'] ?? null;

        Log::channel($channel)->info('cloud.http.completed', [
            'correlation_id' => $correlationId,
            'method' => strtoupper($method),
            'path' => $path,
            'attempt' => $attempt,
            'status' => $response->status(),
            'duration_ms' => $durationMs,
            'workspace_public_id' => $workspacePublic,
        ]);
    }

    private function logFailure(
        string $channel,
        string $correlationId,
        string $method,
        string $path,
        int $attempt,
        Throwable $e,
    ): void {
        Log::channel($channel)->warning('cloud.http.retry', [
            'correlation_id' => $correlationId,
            'method' => strtoupper($method),
            'path' => $path,
            'attempt' => $attempt,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);
    }
}
