<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Marketing\Models\CaseStudy;
use App\Http\Controllers\Controller;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CaseStudyController extends Controller
{
    public function index(): View
    {
        return view('admin.case-studies.index', [
            'items' => CaseStudy::query()->orderBy('sort_order')->orderByDesc('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.case-studies.form', [
            'caseStudy' => new CaseStudy(['is_published' => false, 'sort_order' => 0]),
            'method' => 'POST',
            'action' => route('admin.case-studies.store'),
        ]);
    }

    public function store(Request $request, FlashMessageCatalog $flash): RedirectResponse
    {
        $data = $this->validated($request);
        unset($data['cover_image']);
        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('case-studies', 'public');
        }
        $study = CaseStudy::query()->create($data);

        return redirect()->route('admin.case-studies.edit', $study)->with('status', $flash->created('دراسة الحالة'));
    }

    public function edit(CaseStudy $caseStudy): View
    {
        return view('admin.case-studies.form', [
            'caseStudy' => $caseStudy,
            'method' => 'PUT',
            'action' => route('admin.case-studies.update', $caseStudy),
        ]);
    }

    public function update(Request $request, CaseStudy $caseStudy, FlashMessageCatalog $flash): RedirectResponse
    {
        $data = $this->validated($request, $caseStudy->id);
        unset($data['cover_image']);
        if ($request->hasFile('cover_image')) {
            if ($caseStudy->cover_image) {
                Storage::disk('public')->delete($caseStudy->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')->store('case-studies', 'public');
        }
        $caseStudy->update($data);

        return back()->with('status', $flash->updated('دراسة الحالة'));
    }

    public function destroy(CaseStudy $caseStudy, FlashMessageCatalog $flash): RedirectResponse
    {
        if ($caseStudy->cover_image) {
            Storage::disk('public')->delete($caseStudy->cover_image);
        }
        $caseStudy->delete();

        return redirect()->route('admin.case-studies.index')->with('status', $flash->deleted('دراسة الحالة'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $exceptId = null): array
    {
        $slugRule = 'required|string|max:160|unique:case_studies,slug';
        if ($exceptId !== null) {
            $slugRule .= ','.$exceptId;
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => [$slugRule],
            'client_name' => ['required', 'string', 'max:160'],
            'industry' => ['nullable', 'string', 'max:120'],
            'summary' => ['required', 'string', 'max:1000'],
            'body_html' => ['required', 'string'],
            'cover_image' => ['nullable', 'image', 'max:4096'],
            'published_at' => ['nullable', 'date'],
            'is_published' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ]);

        $data['is_published'] = $request->boolean('is_published');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        if (empty($data['published_at'])) {
            $data['published_at'] = null;
        }

        return $data;
    }
}
