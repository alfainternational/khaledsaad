<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContentRequest;
use App\Models\Content;
use App\Models\ContentCategory;
use App\Models\ContentResource;
use App\Services\Content\ContentHtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminContentController extends Controller
{
    public function __construct(private readonly ContentHtmlSanitizer $sanitizer) {}

    public function index(Request $request): View
    {
        $contents = Content::query()
            ->with('category')
            ->when($request->filled('q'), fn ($query) => $query->where(function ($nested) use ($request): void {
                $term = '%'.addcslashes((string) $request->input('q'), '%_').'%';
                $nested->where('title', 'like', $term)->orWhere('excerpt', 'like', $term);
            }))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('access_level'), fn ($query) => $query->where('access_level', $request->input('access_level')))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        $categories = ContentCategory::query()->ordered()->get();

        return view('admin.content.index', compact('contents', 'categories'));
    }

    public function create(): View
    {
        return view('admin.content.form', [
            'content' => new Content,
            'categories' => ContentCategory::query()->active()->ordered()->get(),
        ]);
    }

    public function store(ContentRequest $request): RedirectResponse
    {
        $content = DB::transaction(function () use ($request): Content {
            $content = Content::query()->create($this->payload($request) + [
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $this->syncResources($content, $request->validated('resources', []));

            return $content;
        });

        cache()->forget('sitemap.xml');

        return redirect()->route('admin.content.edit', $content)->with('success', 'حُفظ المحتوى.');
    }

    public function edit(Content $content): View
    {
        $categories = ContentCategory::query()
            ->where(fn ($query) => $query->active()->orWhere('id', $content->category_id))
            ->ordered()
            ->get();

        return view('admin.content.form', compact('content', 'categories'));
    }

    public function update(ContentRequest $request, Content $content): RedirectResponse
    {
        DB::transaction(function () use ($request, $content): void {
            $content->update($this->payload($request) + [
                'updated_by' => $request->user()->id,
            ]);
            $this->syncResources($content, $request->validated('resources', []));
        });

        cache()->forget('sitemap.xml');

        return redirect()->route('admin.content.edit', $content)->with('success', 'حُدّث المحتوى.');
    }

    public function archive(Content $content): RedirectResponse
    {
        $content->update(['status' => Content::STATUS_ARCHIVED]);
        cache()->forget('sitemap.xml');

        return back()->with('success', 'نُقل المحتوى إلى الأرشيف.');
    }

    public function restore(Content $content): RedirectResponse
    {
        $content->update(['status' => Content::STATUS_DRAFT]);
        cache()->forget('sitemap.xml');

        return back()->with('success', 'أُعيد المحتوى من الأرشيف.');
    }

    private function payload(ContentRequest $request): array
    {
        $data = $request->validated();
        unset($data['resources'], $data['resources_json']);
        $data['body_html'] = $this->sanitizer->sanitize($data['body_html'] ?? '');
        $data['body_json'] = filled($data['body_json'] ?? null)
            ? json_decode((string) $data['body_json'], true, flags: JSON_THROW_ON_ERROR)
            : null;

        if ($data['status'] === Content::STATUS_PUBLISHED
            && blank($data['published_at'] ?? null)) {
            $data['published_at'] = now();
        }

        return $data;
    }

    private function syncResources(Content $content, array $resources): void
    {
        $content->resources()->delete();

        foreach (array_values($resources) as $position => $resource) {
            $isFile = $resource['type'] === ContentResource::TYPE_FILE;

            $content->resources()->create([
                'type' => $resource['type'],
                'title' => $resource['title'],
                'content_media_id' => $isFile ? $resource['media_id'] : null,
                'url' => $isFile ? null : $resource['url'],
                'position' => $position,
            ]);
        }

        $content->unsetRelation('resources');
    }
}
