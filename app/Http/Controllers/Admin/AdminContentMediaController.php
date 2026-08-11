<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContentMediaRequest;
use App\Models\Content;
use App\Models\ContentMedia;
use App\Models\ContentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminContentMediaController extends Controller
{
    public function index(Request $request): View
    {
        $media = ContentMedia::query()
            ->when($request->filled('q'), fn ($query) => $query->where('original_name', 'like', '%'.$request->string('q')->trim().'%'))
            ->latest()
            ->paginate(24)
            ->withQueryString();

        return view('admin.content.media', compact('media'));
    }

    public function store(ContentMediaRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $extension = strtolower($file->guessExtension() ?: $file->extension());
        $directory = 'content/'.now()->format('Y/m');
        $path = $file->storeAs($directory, Str::uuid().'.'.$extension, 'local');

        abort_if($path === false, 500, __('تعذر حفظ الملف.'));

        $media = ContentMedia::query()->create([
            'disk' => 'local',
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
                'original_name' => $media->original_name,
                'size_bytes' => $media->size_bytes,
            ],
        ], 201);
    }

    public function destroy(ContentMedia $media): RedirectResponse
    {
        $isReferenced = Content::query()
            ->where(function ($query) use ($media): void {
                $query->where('cover_image_path', $media->path)
                    ->orWhere('cover_image_path', $media->url())
                    ->orWhere('cover_image_path', 'like', '%/blog/media/'.$media->id);
            })
            ->orWhere('body_html', 'like', '%'.addcslashes($media->path, '%_').'%')
            ->exists();

        $isReferenced = $isReferenced || ContentResource::query()
            ->where('content_media_id', $media->id)
            ->exists();

        if ($isReferenced) {
            return back()->withErrors(['media' => __('لا يمكن حذف ملف مستخدم داخل محتوى. أزل استخدامه أولًا.')]);
        }

        $disk = Storage::disk($media->disk);

        if ($disk->exists($media->path) && ! $disk->delete($media->path)) {
            return back()->withErrors(['media' => __('تعذر حذف الملف من التخزين. لم يُحذف السجل.')]);
        }

        $media->delete();

        return back()->with('success', __('تم حذف الملف من مكتبة الوسائط.'));
    }
}
