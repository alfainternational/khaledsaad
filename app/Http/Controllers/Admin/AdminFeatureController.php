<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Support\Billing\FeatureKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * فهرس الميزات: العناصر التي تُختار في الخطط.
 *
 * ميزة مربوطة بنقطة منع في الكود (FeatureKey) لا يُحرَّر مفتاحها ولا تُحذف،
 * لأن الكود يسأل عنها بالاسم. ما عداها يضيفه الآدمن ويحذفه بحرية، لكنه
 * يُنشأ عرضيًا (display) — لا ندّعي منعًا لا نملكه.
 */
class AdminFeatureController extends Controller
{
    public function index(): View
    {
        return view('admin.features.index', [
            'features' => Feature::orderBy('group')->orderBy('sort_order')->get(),
            'wired' => FeatureKey::all(),
        ]);
    }

    public function create(): View
    {
        return view('admin.features.form', [
            'feature' => new Feature([
                'type' => Feature::TYPE_BOOLEAN,
                'group' => 'general',
                'enforcement' => Feature::ENFORCEMENT_DISPLAY,
                'is_active' => true,
            ]),
            'locked' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        // المفاتيح الجديدة عرضية دائمًا: البوابة تحتاج كودًا يطبّقها.
        $data['enforcement'] = Feature::ENFORCEMENT_DISPLAY;

        Feature::create($data);

        return redirect()->route('admin.features.index')->with('status', 'أُضيف عنصر الميزة.');
    }

    public function edit(Feature $feature): View
    {
        return view('admin.features.form', [
            'feature' => $feature,
            'locked' => $this->isWired($feature),
        ]);
    }

    public function update(Request $request, Feature $feature): RedirectResponse
    {
        $data = $this->validated($request, $feature);

        if ($this->isWired($feature)) {
            // المفتاح والنوع والتطبيق مربوطة بالكود؛ الاسم والوصف والافتراضيات لا.
            unset($data['key'], $data['type'], $data['enforcement']);
        }

        $feature->update($data);

        return redirect()->route('admin.features.index')->with('status', 'حُدّث عنصر الميزة.');
    }

    public function destroy(Feature $feature): RedirectResponse
    {
        if ($this->isWired($feature)) {
            return back()->withErrors([
                'feature' => 'هذا العنصر مربوط بنقطة منع في النظام. عطّله بدل حذفه.',
            ]);
        }

        $feature->delete();

        return redirect()->route('admin.features.index')->with('status', 'حُذف عنصر الميزة.');
    }

    private function isWired(Feature $feature): bool
    {
        return in_array($feature->key, FeatureKey::all(), true);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Feature $feature = null): array
    {
        $data = $request->validate([
            'key' => 'required|string|max:80|regex:/^[a-z0-9_.]+$/|unique:features,key'.($feature ? ",{$feature->id}" : ''),
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:255',
            'group' => 'required|string|max:40',
            'type' => 'required|in:boolean,limit,quota',
            'unit' => 'nullable|string|max:40',
            'enforcement' => 'required|in:gate,display',
            'default_value' => 'nullable|integer|min:0',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $data['default_enabled'] = $request->boolean('default_enabled');
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
