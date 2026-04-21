<?php

namespace App\Contracts;

use App\Domain\Integration\Exceptions\CloudIntegrationException;

interface CloudClientContract
{
    /**
     * هل التكامل مفعّلاً ومُعدّاً (عنوان أساسي عند استخدام عميل HTTP).
     */
    public function configured(): bool;

    /**
     * طلب GET يعيد مصفوفة JSON فكّتها، أو مصفوفة فارغة.
     *
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     *
     * @throws CloudIntegrationException
     */
    public function get(string $path, array $query = [], array $headers = []): array;

    /**
     * طلب POST يعيد مصفوفة JSON فكّتها، أو مصفوفة فارغة.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     *
     * @throws CloudIntegrationException
     */
    public function post(string $path, array $payload = [], array $headers = []): array;
}
