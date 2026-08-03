<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContentMediaRequest;
use App\Models\ContentMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class AdminContentMediaController extends Controller
{
    public function store(ContentMediaRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $extension = strtolower($file->guessExtension() ?: $file->extension());
        $directory = 'content/'.now()->format('Y/m');
        $path = $file->storeAs($directory, Str::uuid().'.'.$extension, 'public');

        abort_if($path === false, 500, '???? ??? ?????.');

        $media = ContentMedia::query()->create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => (string) $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'alt_text' => $request->validated('alt_text'),
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => [
                'id' => $media->id,
                'url' => $media->url(),
                'path' => $media->path,
                'alt_text' => $media->alt_text,
                'mime_type' => $media->mime_type,
            ],
        ], 201);
    }
}
