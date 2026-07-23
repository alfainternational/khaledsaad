<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPlanController extends Controller
{
    public function index(): View
    {
        return view('admin.plans.index', [
            'plans' => Plan::orderBy('sort_order')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.plans.form', ['plan' => new Plan(['interval' => 'monthly', 'is_public' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Plan::create($this->validated($request));

        return redirect()->route('admin.plans.index')->with('status', 'أُنشئت الخطة.');
    }

    public function edit(Plan $plan): View
    {
        return view('admin.plans.form', ['plan' => $plan]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->validated($request, $plan));

        return redirect()->route('admin.plans.index')->with('status', 'حُدّثت الخطة.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        if ($plan->subscriptions()->exists()) {
            return back()->withErrors(['plan' => 'لا يمكن حذف خطة مشترك بها أحد. أخفِها بدل الحذف.']);
        }

        $plan->delete();

        return redirect()->route('admin.plans.index')->with('status', 'حُذفت الخطة.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Plan $plan = null): array
    {
        $data = $request->validate([
            'key' => 'required|string|max:60|alpha_dash|unique:plans,key'.($plan ? ",{$plan->id}" : ''),
            'name' => 'required|string|max:120',
            'interval' => 'required|in:monthly,yearly',
            'price' => 'required|integer|min:0',
            'monthly_credits' => 'required|integer|min:0',
            'project_limit' => 'required|integer|min:1|max:1000',
            'features' => 'nullable|string',
            'is_public' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // الميزات تُدخَل سطرًا لكل ميزة وتُحفَظ مصفوفة.
        $data['features'] = collect(explode("\n", $data['features'] ?? ''))
            ->map(fn ($line) => trim($line))->filter()->values()->all();
        $data['is_public'] = $request->boolean('is_public');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
