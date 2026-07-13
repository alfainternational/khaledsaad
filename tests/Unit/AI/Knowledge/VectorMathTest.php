<?php

namespace Tests\Unit\AI\Knowledge;

use App\Domain\AI\Knowledge\VectorMath;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class VectorMathTest extends TestCase
{
    #[Test]
    public function it_normalizes_finite_vectors_and_calculates_cosine_similarity(): void
    {
        $math = new VectorMath(2, 8);

        $this->assertSame([0.6, 0.8], $math->normalize([3, 4]));
        $this->assertEqualsWithDelta(1.0, $math->cosine([0.6, 0.8], [0.6, 0.8]), 0.000001);
        $this->assertEqualsWithDelta(0.0, $math->cosine([1, 0], [0, 1]), 0.000001);
    }

    #[Test]
    public function it_rejects_unsafe_or_incompatible_vectors(): void
    {
        $math = new VectorMath(2, 8);

        foreach ([[0, 0], [1], [1, INF], [1, NAN], ['1', 2]] as $vector) {
            try {
                $math->normalize($vector);
                $this->fail('Expected an invalid vector to be rejected.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(InvalidArgumentException::class);
        $math->cosine([1, 0], [1, 0, 0]);
    }
}
