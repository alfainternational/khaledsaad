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
        'professional_headline',
        'about',
        'contact',
        'services',
        'problems',
        'method',
        'experience',
        'education',
        'credentials',
        'skills',
        'professional_services',
        'principles',
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
                    'profile' => route('profile'),
                    'profile_pdf' => route('profile.pdf'),
                    'services' => route('services'),
                    'methodology' => route('methodology'),
                    'knowledge' => route('knowledge'),
                    'faq' => route('faq'),
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

    public function mobileApp(): JsonResponse
    {
        $path = (string) config('mobile.apk_path');
        $available = is_file($path);

        return response()->json(['data' => [
            'name' => 'Khaled Saad Growth',
            'version' => (string) config('mobile.version'),
            'build' => (int) config('mobile.build'),
            'android_package' => (string) config('mobile.android_package'),
            'ios_bundle' => (string) config('mobile.ios_bundle'),
            'available' => $available,
            'download_url' => $available ? route('mobile.download') : null,
            'size_bytes' => $available ? filesize($path) : null,
            'sha256' => $available ? hash_file('sha256', $path) : null,
        ]]);
    }
}
