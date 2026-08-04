<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContentCategoryRequest;
use App\Models\ContentCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminContentCategoryController extends Controller
{
    public function index(): View
    {
        $categories = ContentCategory::query()
            ->withCount('contents')
            ->ordered()
            ->paginate(30);

        return view('admin.content.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.content.categories.form', [
            'category' => new ContentCategory,
        ]);
    }

    public function store(ContentCategoryRequest $request): RedirectResponse
    {
        ContentCategory::query()->create($request->validated());

        return redirect()->route('admin.content-categories.index')->with('success', 'أُضيف القسم.');
    }

    public function edit(ContentCategory $contentCategory): View
    {
        return view('admin.content.categories.form', [
            'category' => $contentCategory,
        ]);
    }

    public function update(ContentCategoryRequest $request, ContentCategory $contentCategory): RedirectResponse
    {
        $contentCategory->update($request->validated());

        return redirect()->route('admin.content-categories.index')->with('success', 'حُدّث القسم.');
    }

    public function destroy(ContentCategory $contentCategory): RedirectResponse
    {
        if ($contentCategory->contents()->exists()) {
            return redirect()->route('admin.content-categories.index')
                ->with('error', 'لا يمكن حذف قسم مرتبط بمحتوى. انقل المواد إلى قسم آخر أولًا.');
        }

        $contentCategory->delete();

        return redirect()->route('admin.content-categories.index')->with('success', 'حُذف القسم.');
    }
}
