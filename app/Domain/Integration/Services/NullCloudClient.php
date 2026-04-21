<?php

namespace App\Domain\Integration\Services;

use App\Contracts\CloudClientContract;
use App\Domain\Integration\Exceptions\CloudIntegrationException;

final class NullCloudClient implements CloudClientContract
{
    public function configured(): bool
    {
        return false;
    }

    public function get(string $path, array $query = [], array $headers = []): array
    {
        throw CloudIntegrationException::configurationMissing();
    }

    public function post(string $path, array $payload = [], array $headers = []): array
    {
        throw CloudIntegrationException::configurationMissing();
    }
}
