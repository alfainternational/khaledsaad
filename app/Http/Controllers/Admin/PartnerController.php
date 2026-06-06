<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Marketing\Models\Partner;
use App\Http\Controllers\Controller;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PartnerController extends Controller
{
    public function index(): View
    {
        return view('admin.partners.index', [
            'partners' => Partner::query()->orderBy('sort_order')->orderBy('name')->paginate(24),
        ]);
    }

    public function create(): View
    {
        return view('admin.partners.form', [
            'partner' => new Partner(['is_published' => true, 'sort_order' => 0]),
            'method' => 'POST',
            'action' => route('admin.partners.store'),
        ]);
    }

    public function store(Request $request, FlashMessageCatalog $flash): RedirectResponse
    {
        $data = $this->validated($request);
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('partners', 'public');
        }
        $partner = Partner::query()->create($data);

        return redirect()->route('admin.partners.edit', $partner)->with('status', $flash->created('الشريك'));
    }

    public function edit(Partner $partner): View
    {
        return view('admin.partners.form', [
            'partner' => $partner,
            'method' => 'PUT',
            'action' => route('admin.partners.update', $partner),
        ]);
    }

    public function update(Request $request, Partner $partner, FlashMessageCatalog $flash): RedirectResponse
    {
        $data = $this->validated($request);
        if ($request->hasFile('logo')) {
            if ($partner->logo_path) {
                Storage::disk('public')->delete($partner->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('partners', 'public');
        }
        $partner->update($data);

        return back()->with('status', $flash->updated('الشريك'));
    }

    public function destroy(Partner $partner, FlashMessageCatalog $flash): RedirectResponse
    {
        if ($partner->logo_path) {
            Storage::disk('public')->delete($partner->logo_path);
        }
        $partner->delete();

        return redirect()->route('admin.partners.index')->with('status', $flash->deleted('الشريك'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'logo' => ['nullable', 'image', 'max:4096'],
            'description' => ['nullable', 'string', 'max:2000'],
            'website_url' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        $data['is_published'] = $request->boolean('is_published');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        unset($data['logo']);

        return $data;
    }
}
