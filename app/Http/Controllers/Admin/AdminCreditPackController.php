<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditPack;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminCreditPackController extends Controller
{
    public function index(): View
    {
        return view('admin.packs.index', [
            'packs' => CreditPack::orderBy('sort_order')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.packs.form', ['pack' => new CreditPack(['currency' => 'SAR', 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        CreditPack::create($this->validated($request));

        return redirect()->route('admin.packs.index')->with('status', __('أُنشئت الحزمة.'));
    }

    public function edit(CreditPack $pack): View
    {
        return view('admin.packs.form', ['pack' => $pack]);
    }

    public function update(Request $request, CreditPack $pack): RedirectResponse
    {
        $pack->update($this->validated($request));

        return redirect()->route('admin.packs.index')->with('status', __('حُدّثت الحزمة.'));
    }

    public function destroy(CreditPack $pack): RedirectResponse
    {
        $pack->delete();

        return redirect()->route('admin.packs.index')->with('status', __('حُذفت الحزمة.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'credits' => 'required|integer|min:1',
            'price' => 'required|integer|min:0',
            'currency' => 'required|string|size:3',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
