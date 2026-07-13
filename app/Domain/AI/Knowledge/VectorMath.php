<?php

namespace App\Domain\AI\Knowledge;

use InvalidArgumentException;

class VectorMath
{
    public function __construct(
        private readonly int $minimumDimensions = 2,
        private readonly int $maximumDimensions = 4096,
    ) {}

    /** @param array<int, mixed> $vector
     * @return list<float>
     */
    public function normalize(array $vector): array
    {
        $count = count($vector);
        if ($count < $this->minimumDimensions || $count > $this->maximumDimensions || ! array_is_list($vector)) {
            throw new InvalidArgumentException('Embedding dimensions are outside the allowed range.');
        }

        $sum = 0.0;
        $normalized = [];
        foreach ($vector as $value) {
            if (! is_int($value) && ! is_float($value)) {
                throw new InvalidArgumentException('Embedding values must be numeric.');
            }
            $number = (float) $value;
            if (! is_finite($number)) {
                throw new InvalidArgumentException('Embedding values must be finite.');
            }
            $normalized[] = $number;
            $sum += $number * $number;
        }
        if ($sum <= 0.0) {
            throw new InvalidArgumentException('Embedding vector must have a non-zero magnitude.');
        }

        $magnitude = sqrt($sum);

        return array_map(static fn (float $value): float => $value / $magnitude, $normalized);
    }

    /** @param array<int, mixed> $left
     * @param  array<int, mixed>  $right
     */
    public function cosine(array $left, array $right): float
    {
        if (count($left) !== count($right)) {
            throw new InvalidArgumentException('Embedding dimensions must match.');
        }
        $left = $this->normalize($left);
        $right = $this->normalize($right);
        $score = 0.0;
        foreach ($left as $index => $value) {
            $score += $value * $right[$index];
        }

        return max(-1.0, min(1.0, $score));
    }
}
