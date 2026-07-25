<?php

namespace App\Support\ProductQuality;

use JsonException;
use RuntimeException;

final class ParityMatrix
{
    public const array ROLES = ['visitor', 'customer', 'admin'];

    public const array STATUSES = ['missing', 'implemented', 'verified'];

    public function __construct(
        private readonly ?string $path = null,
    ) {}

    /**
     * @return list<array<string, mixed>>
     *
     * @throws JsonException
     */
    public function records(): array
    {
        $path = $this->path ?? dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'docs'
            .DIRECTORY_SEPARATOR.'product'.DIRECTORY_SEPARATOR.'parity-matrix.yaml';

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read parity matrix at {$path}.");
        }

        $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || ! isset($decoded['capabilities']) || ! is_array($decoded['capabilities'])) {
            throw new RuntimeException('The parity matrix must contain a capabilities array.');
        }

        return array_values($decoded['capabilities']);
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws JsonException
     */
    public function forRole(string $role): array
    {
        return array_values(array_filter(
            $this->records(),
            fn (array $record) => ($record['role'] ?? null) === $role,
        ));
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws JsonException
     */
    public function forSurface(string $surface): array
    {
        return array_values(array_filter(
            $this->records(),
            fn (array $record) => ($record[$surface]['applicable'] ?? false) === true,
        ));
    }
}
