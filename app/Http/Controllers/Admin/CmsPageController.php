<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Marketing\Models\CmsPage;
use App\Http\Controllers\Controller;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsPageController extends Controller
{
    public function index(): View
    {
        return view('admin.cms-pages.index', [
            'pages' => CmsPage::query()->orderBy('slug')->paginate(24),
        ]);
    }

    public function create(): View
    {
        return view('admin.cms-pages.form', [
            'page' => new CmsPage(['is_published' => true]),
            'method' => 'POST',
            'action' => route('admin.cms-pages.store'),
        ]);
    }

    public function store(Request $request, FlashMessageCatalog $flash): RedirectResponse
    {
        $data = $this->validated($request);
        $page = CmsPage::query()->create($data);

        return redirect()->route('admin.cms-pages.edit', $page)->with('status', $flash->created('صفحة CMS'));
    }

    public function edit(CmsPage $cmsPage): View
    {
        return view('admin.cms-pages.form', [
            'page' => $cmsPage,
            'method' => 'PUT',
            'action' => route('admin.cms-pages.update', $cmsPage),
        ]);
    }

    public function update(Request $request, CmsPage $cmsPage, FlashMessageCatalog $flash): RedirectResponse
    {
        $data = $this->validated($request, $cmsPage->id);
        $cmsPage->update($data);

        return back()->with('status', $flash->updated('صفحة CMS'));
    }

    public function destroy(CmsPage $cmsPage, FlashMessageCatalog $flash): RedirectResponse
    {
        $cmsPage->delete();

        return redirect()->route('admin.cms-pages.index')->with('status', $flash->deleted('صفحة CMS'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $exceptId = null): array
    {
        $slugRule = 'required|string|max:120|unique:cms_pages,slug';
        if ($exceptId !== null) {
            $slugRule .= ','.$exceptId;
        }

        $data = $request->validate([
            'slug' => [$slugRule],
            'title' => ['required', 'string', 'max:200'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'body_html' => ['nullable', 'string'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        $data['is_published'] = $request->boolean('is_published');

        return $data;
    }
}
