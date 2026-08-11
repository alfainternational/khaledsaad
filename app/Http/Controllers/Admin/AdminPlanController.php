<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Support\Billing\FeatureKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        return view('admin.plans.form', [
            'plan' => new Plan(['interval' => 'monthly', 'is_public' => true]),
            'features' => $this->catalogue(),
            'selection' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $plan = Plan::create($this->validated($request));
        $this->syncFeatures($request, $plan);

        return redirect()->route('admin.plans.edit', $plan)
            ->with('status', __('أُنشئت الخطة. راجع عناصر ميزاتها.'));
    }

    public function edit(Plan $plan): View
    {
        return view('admin.plans.form', [
            'plan' => $plan,
            'features' => $this->catalogue(),
            'selection' => $plan->planFeatures()->get()->keyBy('feature_id'),
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->validated($request, $plan));
        $this->syncFeatures($request, $plan);

        return redirect()->route('admin.plans.index')->with('status', __('حُدّثت الخطة وعناصر ميزاتها.'));
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        if ($plan->subscriptions()->exists()) {
            return back()->withErrors(['plan' => __('لا يمكن حذف خطة مشترك بها أحد. أخفِها بدل الحذف.')]);
        }

        $plan->delete();

        return redirect()->route('admin.plans.index')->with('status', __('حُذفت الخطة.'));
    }

    /**
     * @return Collection<int, Feature>
     */
    private function catalogue()
    {
        return Feature::active()->orderBy('group')->orderBy('sort_order')->get();
    }

    /**
     * مزامنة عناصر الميزات المختارة.
     *
     * الشكل القادم من النموذج: features[<feature_id>][enabled|value|note].
     * غير المختار يُحذف صفّه فيسقط للافتراضي — لا يبقى معلّقًا في القاعدة.
     */
    private function syncFeatures(Request $request, Plan $plan): void
    {
        $input = $request->input('features', []);

        if (! is_array($input)) {
            return;
        }

        $features = Feature::active()->get()->keyBy('id');
        $keep = [];

        foreach ($input as $featureId => $row) {
            $feature = $features->get((int) $featureId);

            if ($feature === null || empty($row['enabled'])) {
                continue;
            }

            $value = null;

            if ($feature->isNumeric()) {
                $raw = $row['value'] ?? '';
                // الفراغ = بلا حد. الصفر قيمة صريحة تعني المنع.
                $value = ($raw === '' || $raw === null) ? null : max(0, (int) $raw);
            }

            PlanFeature::updateOrCreate(
                ['plan_id' => $plan->id, 'feature_id' => $feature->id],
                [
                    'enabled' => true,
                    'value' => $value,
                    'note' => is_string($row['note'] ?? null) && $row['note'] !== '' ? mb_substr($row['note'], 0, 190) : null,
                    'sort_order' => $feature->sort_order,
                ],
            );

            $keep[] = $feature->id;

            // حد المشاريع له عمود قديم يقرأه غير مكان: نبقيه متسقًا.
            if ($feature->key === FeatureKey::PROJECTS_LIMIT && $value !== null && $value > 0) {
                $plan->forceFill(['project_limit' => $value])->save();
            }
        }

        $plan->planFeatures()->whereNotIn('feature_id', $keep ?: [0])->delete();
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
            'features_text' => 'nullable|string',
            'is_public' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // العمود القديم صار احتياطًا للعرض فقط: عناصر الميزات هي ما يحكم.
        $data['features'] = collect(explode("\n", $data['features_text'] ?? ''))
            ->map(fn ($line) => trim($line))->filter()->values()->all();
        unset($data['features_text']);
        $data['is_public'] = $request->boolean('is_public');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
