<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Tools\ToolShowcase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class PublicContentController extends Controller
{
    private const array BRAND_KEYS = [
        'name',
        'name_en',
        'tagline',
        'headline',
        'location',
        'experience_years',
        'about',
        'contact',
        'services',
        'problems',
        'method',
        'experience',
        'education',
        'credentials',
        'skills',
        'knowledge',
        'faqs',
    ];

    public function __construct(private readonly ToolShowcase $showcase) {}

    public function bootstrap(): JsonResponse
    {
        return response()->json([
            'data' => [
                'brand' => Arr::only(config('brand', []), self::BRAND_KEYS),
                'tools' => $this->showcase->cards(),
                'tool_stats' => $this->showcase->stats(),
                'entry_tool' => $this->showcase->entryTool(),
                'links' => [
                    'privacy' => route('api.v1.public.legal', 'privacy'),
                    'terms' => route('api.v1.public.legal', 'terms'),
                ],
            ],
        ]);
    }

    public function legal(string $page): JsonResponse
    {
        abort_unless(in_array($page, ['privacy', 'terms'], true), 404);

        $content = config("legal.{$page}");
        abort_unless(is_array($content), 404);

        return response()->json([
            'data' => [
                'slug' => $page,
                ...$content,
            ],
        ]);
    }
}
