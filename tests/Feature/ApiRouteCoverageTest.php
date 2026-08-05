<?php

namespace Tests\Feature;

use App\Support\ProductQuality\ParityMatrix;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiRouteCoverageTest extends TestCase
{
    #[Test]
    public function every_declared_api_capability_resolves_to_a_named_route(): void
    {
        $missing = [];

        foreach ((new ParityMatrix)->forSurface('api') as $record) {
            $name = $record['api']['route'] ?? null;

            if (is_string($name) && $name !== '' && ! Route::has($name)) {
                $missing[] = "{$record['id']}:{$name}";
            }
        }

        $this->assertSame([], $missing, 'Every declared API route must exist.');
    }

    #[Test]
    public function every_declared_web_capability_resolves_to_a_named_route(): void
    {
        $missing = [];

        foreach ((new ParityMatrix)->forSurface('web') as $record) {
            $name = $record['web']['route'] ?? null;

            if (is_string($name) && $name !== '' && ! Route::has($name)) {
                $missing[] = "{$record['id']}:{$name}";
            }
        }

        $this->assertSame([], $missing, 'Every declared web route must exist.');
    }

    #[Test]
    public function role_and_surface_filters_return_only_matching_records(): void
    {
        $matrix = new ParityMatrix;

        $this->assertNotEmpty($matrix->forRole('admin'));
        $this->assertNotEmpty($matrix->forSurface('api'));

        foreach ($matrix->forRole('admin') as $record) {
            $this->assertSame('admin', $record['role']);
        }

        foreach ($matrix->forSurface('api') as $record) {
            $this->assertTrue($record['api']['applicable']);
        }
    }
}
