<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Services\Content\ContentAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicContentLibraryController extends Controller
{
    public function __construct(private readonly ContentAccessService $access) {}

    public function index(Request $request): JsonResponse
    {
        $type = in_array($request->query('type'), Content::types(), true)
            ? $request->query('type')
            : null;

        $contents = Content::query()
            ->published()
            ->when($type, fn ($query) => $query->where('type', $type))
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Content $content) => $this->summary($content));

        return response()->json([
            'data' => $contents->items(),
            'links' => [
                'first' => $contents->url(1),
                'last' => $contents->url($contents->lastPage()),
                'prev' => $contents->previousPageUrl(),
                'next' => $contents->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $contents->currentPage(),
                'from' => $contents->firstItem(),
                'last_page' => $contents->lastPage(),
                'per_page' => $contents->perPage(),
                'to' => $contents->lastItem(),
                'total' => $contents->total(),
            ],
        ]);
    }

    public function show(Request $request, Content $content): JsonResponse
    {
        abort_unless($content->isPublished(), 404);

        $content->load('sections.items');
        $unlocked = $this->access->canView($content, $this->access->tokenFrom($request));

        return response()->json(['data' => [
            ...$this->summary($content),
            'locked' => ! $unlocked,
            'body_html' => $unlocked ? $content->body_html : null,
            'video_url' => $unlocked ? $content->video_url : null,
            'sections' => $content->type === Content::TYPE_COURSE
                ? $content->sections->map(fn ($section) => [
                    'id' => $section->id,
                    'title' => $section->title,
                    'description' => $section->description,
                    'position' => $section->position,
                    'items' => $section->items
                        ->filter->isPublished()
                        ->map(fn (Content $item) => $this->summary($item))
                        ->values(),
                ])->values()
                : [],
        ]]);
    }

    /** @return array<string, mixed> */
    private function summary(Content $content): array
    {
        return [
            'id' => $content->id,
            'type' => $content->type,
            'title' => $content->title,
            'slug' => $content->slug,
            'excerpt' => $content->excerpt,
            'cover_image_path' => $content->cover_image_path,
            'cover_image_url' => filled($content->cover_image_path)
                ? (str_starts_with($content->cover_image_path, 'http') || str_starts_with($content->cover_image_path, '/')
                    ? $content->cover_image_path
                    : Storage::disk('public')->url($content->cover_image_path))
                : null,
            'duration_minutes' => $content->duration_minutes,
            'published_at' => $content->published_at?->toAtomString(),
            'locked' => $content->isSubscriberOnly(),
            'url' => route('content.show', $content),
        ];
    }
}
