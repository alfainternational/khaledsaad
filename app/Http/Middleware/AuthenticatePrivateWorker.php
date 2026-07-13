<?php

namespace App\Http\Middleware;

use App\Domain\AI\Worker\Models\IntelligenceWorker;
use App\Domain\AI\Worker\Models\IntelligenceWorkerNonce;
use App\Domain\AI\Worker\Security\WorkerSigner;
use App\Support\Settings\SettingsStore;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class AuthenticatePrivateWorker
{
    public function __construct(
        private readonly WorkerSigner $signer,
        private readonly SettingsStore $settings,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $runtimeState = $this->settings->getFresh('services.private_worker.enabled');
        $enabled = is_bool($runtimeState)
            ? $runtimeState
            : (bool) config('services.private_worker.enabled', false);
        if (! $enabled) {
            abort(404);
        }

        $workerId = trim((string) $request->header('X-Worker-Id'));
        $timestamp = (string) $request->header('X-Worker-Timestamp');
        $nonce = trim((string) $request->header('X-Worker-Nonce'));
        $signature = strtolower(trim((string) $request->header('X-Worker-Signature')));

        if (
            $workerId === ''
            || preg_match('/\A\d{10}\z/D', $timestamp) !== 1
            || preg_match('/\A[a-zA-Z0-9-]{16,80}\z/D', $nonce) !== 1
            || preg_match('/\A[0-9a-f]{64}\z/D', $signature) !== 1
        ) {
            return $this->error('WORKER_AUTH_HEADERS_INVALID', 401);
        }

        $requestTime = (int) $timestamp;
        if (abs(now()->timestamp - $requestTime) > (int) config('services.private_worker.clock_drift_seconds', 300)) {
            return $this->error('WORKER_TIMESTAMP_INVALID', 401);
        }

        $worker = IntelligenceWorker::query()
            ->where('public_id', $workerId)
            ->where('status', 'active')
            ->first();
        if (! $worker) {
            return $this->error('WORKER_UNKNOWN', 401);
        }

        try {
            $secret = Crypt::decryptString($worker->secret_ciphertext);
        } catch (Throwable) {
            return $this->error('WORKER_SECRET_INVALID', 401);
        }

        $expected = $this->signer->signRequest(
            $secret,
            $request->method(),
            $request->getPathInfo(),
            $requestTime,
            $nonce,
            $request->getContent(),
        );
        if (! hash_equals($expected, $signature)) {
            return $this->error('WORKER_SIGNATURE_INVALID', 401);
        }

        try {
            $stored = IntelligenceWorkerNonce::query()->firstOrCreate(
                ['intelligence_worker_id' => $worker->id, 'nonce' => $nonce],
                [
                    'request_timestamp' => $requestTime,
                    'expires_at' => now()->addSeconds((int) config('services.private_worker.nonce_ttl_seconds', 600)),
                ],
            );
            if (! $stored->wasRecentlyCreated) {
                return $this->error('WORKER_NONCE_REPLAYED', 409);
            }
        } catch (QueryException) {
            return $this->error('WORKER_NONCE_REPLAYED', 409);
        }

        $worker->update([
            'last_seen_at' => now(),
            'last_ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
            'version' => mb_substr((string) $request->header('X-Worker-Version'), 0, 80) ?: $worker->version,
        ]);

        app()->instance('currentPrivateWorker', $worker);
        app()->instance('currentPrivateWorkerSecret', $secret);

        return $next($request);
    }

    private function error(string $code, int $status): JsonResponse
    {
        return response()->json(['message' => 'Private worker authentication failed.', 'code' => $code], $status);
    }
}
