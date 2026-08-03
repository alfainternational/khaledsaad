<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\ContentCategory;
use App\Services\Content\ContentAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ContentLibraryController extends Controller
{
    public function __construct(private readonly ContentAccessService $access) {}

    public function index(Request $request): View
    {
        $type = in_array($request->query('type'), Content::types(), true)
            ? $request->query('type')
            : null;
        $search = Str::limit(trim((string) $request->query('q')), 100, '');
        $category = ContentCategory::query()
            ->active()
            ->where('slug', $request->query('category'))
            ->first();

        $contents = Content::query()
            ->with('category')
            ->published()
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($category, fn ($query) => $query->where('category_id', $category->id))
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%'.addcslashes($search, '%_').'%';
                $query->where(fn ($nested) => $nested
                    ->where('title', 'like', $term)
                    ->orWhere('excerpt', 'like', $term));
            })
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString();

        $categories = ContentCategory::query()
            ->active()
            ->withCount(['contents as published_contents_count' => fn ($query) => $query->published()])
            ->ordered()
            ->get();
        $typeCounts = Content::query()
            ->published()
            ->selectRaw('type, COUNT(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type')
            ->map(fn ($count) => (int) $count);
        $totalCount = $typeCounts->sum();

        $brand = config('brand');

        return view('site.content.index', compact(
            'contents',
            'type',
            'search',
            'category',
            'categories',
            'typeCounts',
            'totalCount',
            'brand',
        ));
    }

    public function show(Request $request, Content $content): View
    {
        abort_unless($content->isPublished(), 404);

        $content->load(['category', 'sections.items', 'resources.media']);
        $unlocked = $this->access->canView($content, $this->access->tokenFrom($request));

        $relatedContents = Content::query()
            ->with('category')
            ->published()
            ->whereKeyNot($content->getKey())
            ->when(
                $content->category_id,
                fn ($query) => $query->where('category_id', $content->category_id),
                fn ($query) => $query->where('type', $content->type),
            )
            ->latest('published_at')
            ->limit(3)
            ->get();

        $brand = config('brand');

        return view('site.content.show', compact('content', 'unlocked', 'relatedContents', 'brand'));
    }
}
