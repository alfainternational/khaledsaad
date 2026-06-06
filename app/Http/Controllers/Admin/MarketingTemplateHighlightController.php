<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Marketing\Models\MarketingTemplateHighlight;
use App\Http\Controllers\Controller;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketingTemplateHighlightController extends Controller
{
    public function index(): View
    {
        return view('admin.marketing-template-highlights.index', [
            'items' => MarketingTemplateHighlight::query()->orderBy('sort_order')->orderBy('title')->paginate(24),
        ]);
    }

    public function create(): View
    {
        return view('admin.marketing-template-highlights.form', [
            'item' => new MarketingTemplateHighlight(['is_published' => true, 'sort_order' => 0]),
            'method' => 'POST',
            'action' => route('admin.marketing-template-highlights.store'),
        ]);
    }

    public function store(Request $request, FlashMessageCatalog $flash): RedirectResponse
    {
        $data = $this->validated($request);
        $item = MarketingTemplateHighlight::query()->create($data);

        return redirect()->route('admin.marketing-template-highlights.edit', $item)->with('status', $flash->created('عنصر القالب'));
    }

    public function edit(MarketingTemplateHighlight $marketingTemplateHighlight): View
    {
        return view('admin.marketing-template-highlights.form', [
            'item' => $marketingTemplateHighlight,
            'method' => 'PUT',
            'action' => route('admin.marketing-template-highlights.update', $marketingTemplateHighlight),
        ]);
    }

    public function update(Request $request, MarketingTemplateHighlight $marketingTemplateHighlight, FlashMessageCatalog $flash): RedirectResponse
    {
        $data = $this->validated($request);
        $marketingTemplateHighlight->update($data);

        return back()->with('status', $flash->updated('عنصر القالب'));
    }

    public function destroy(MarketingTemplateHighlight $marketingTemplateHighlight, FlashMessageCatalog $flash): RedirectResponse
    {
        $marketingTemplateHighlight->delete();

        return redirect()->route('admin.marketing-template-highlights.index')->with('status', $flash->deleted('عنصر القالب'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:1000'],
            'body_html' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:120'],
            'icon_emoji' => ['nullable', 'string', 'max:20'],
            'cta_label' => ['nullable', 'string', 'max:80'],
            'cta_url' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        $data['is_published'] = $request->boolean('is_published');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
