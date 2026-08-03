<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\ContentMedia;
use App\Services\Content\ContentAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContentMediaController extends Controller
{
    public function __construct(private readonly ContentAccessService $access) {}

    public function __invoke(Request $request, ContentMedia $media): StreamedResponse
    {
        if (! $request->user()?->is_admin) {
            $references = Content::query()
                ->published()
                ->where(function ($query) use ($media): void {
                    $query->where('cover_image_path', $media->url())
                        ->orWhere('cover_image_path', 'like', '%/blog/media/'.$media->id)
                        ->orWhere('body_html', 'like', '%/blog/media/'.$media->id.'%')
                        ->orWhereHas('resources', fn ($resources) => $resources->where('content_media_id', $media->id));
                })
                ->with('resources')
                ->get()
                ->filter(fn (Content $content) => $this->referencesMedia($content, $media));

            abort_if($references->isEmpty(), 404);

            $isPublicReference = $references->contains(
                fn (Content $content) => $this->containsMediaUrl((string) $content->cover_image_path, $media)
                    || ! $content->isSubscriberOnly(),
            );

            $hasGatedAccess = $references->contains(
                fn (Content $content) => $this->access->canView($content, $this->access->tokenFrom($request)),
            );

            abort_unless($isPublicReference || $hasGatedAccess, 404);
        }

        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);

        return Storage::disk($media->disk)->response($media->path, $media->original_name, [
            'Content-Type' => $media->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function referencesMedia(Content $content, ContentMedia $media): bool
    {
        return $this->containsMediaUrl((string) $content->cover_image_path, $media)
            || $this->containsMediaUrl((string) $content->body_html, $media)
            || $content->resources->contains('content_media_id', $media->id);
    }

    private function containsMediaUrl(string $value, ContentMedia $media): bool
    {
        return preg_match('#/blog/media/'.preg_quote((string) $media->id, '#').'(?![0-9])#', $value) === 1;
    }
}
