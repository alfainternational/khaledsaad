<?php

namespace App\Domain\AI\Worker\Security;

class WorkerSigner
{
    public function signRequest(
        string $secret,
        string $method,
        string $path,
        int $timestamp,
        string $nonce,
        string $body,
    ): string {
        $canonical = implode("\n", [
            strtoupper($method),
            '/'.ltrim($path, '/'),
            (string) $timestamp,
            $nonce,
            hash('sha256', $body),
        ]);

        return hash_hmac('sha256', $canonical, $secret);
    }

    /** @param array<string, mixed> $envelope */
    public function signEnvelope(string $secret, array $envelope): string
    {
        return hash_hmac('sha256', $this->canonicalJson($envelope), $secret);
    }

    /** @param array<string, mixed> $value */
    public function canonicalJson(array $value): string
    {
        return json_encode(
            $this->sort($value),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    private function sort(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->sort($item);
        }

        return $value;
    }
}
