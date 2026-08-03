<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\ContentResource;
use App\Services\Content\ContentAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContentResourceController extends Controller
{
    public function __construct(private readonly ContentAccessService $access) {}

    public function __invoke(Request $request, Content $content, ContentResource $resource): StreamedResponse
    {
        abort_unless($content->isPublished(), 404);
        abort_unless(
            $resource->content_id === $content->id
            && $resource->type === ContentResource::TYPE_FILE,
            404,
        );
        abort_unless($this->access->canView($content, $this->access->tokenFrom($request)), 404);

        $media = $resource->media;
        abort_unless($media && Storage::disk($media->disk)->exists($media->path), 404);

        return Storage::disk($media->disk)->download($media->path, $media->original_name, [
            'Content-Type' => $media->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
