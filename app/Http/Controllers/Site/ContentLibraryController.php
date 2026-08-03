<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Services\Content\ContentAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContentLibraryController extends Controller
{
    public function __construct(private readonly ContentAccessService $access) {}

    public function index(Request $request): View
    {
        $type = in_array($request->query('type'), Content::types(), true)
            ? $request->query('type')
            : null;

        $contents = Content::query()
            ->published()
            ->when($type, fn ($query) => $query->where('type', $type))
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString();

        $brand = config('brand');

        return view('site.content.index', compact('contents', 'type', 'brand'));
    }

    public function show(Request $request, Content $content): View
    {
        abort_unless($content->isPublished(), 404);

        $content->load(['sections.items', 'resources.media']);
        $unlocked = $this->access->canView($content, $this->access->tokenFrom($request));

        $brand = config('brand');

        return view('site.content.show', compact('content', 'unlocked', 'brand'));
    }
}
