<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Settings\SettingsConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * إدارة المفاتيح والإعدادات من اللوحة بدل ملفات .env.
 *
 * ما يُحفظ هنا يُطبَّق فوق config عند كل طلب (SettingsConfig::apply)، فيسري
 * التغيير حيًّا على بوابة الذكاء وأرقام السوق واكتشاف المنافسين فورًا.
 */
class AdminSettingsController extends Controller
{
    public function index(): View
    {
        $overrides = Setting::all()->keyBy('key');

        $groups = collect(SettingsConfig::catalog())->map(fn (array $group) => [
            'group' => $group['group'],
            'fields' => collect($group['fields'])->map(function (array $field) use ($overrides) {
                $override = $overrides->get($field['key']);

                // القيمة الفعّالة: تجاوز اللوحة إن وُجد، وإلا قيمة config (للمسارات
                // ذات النقطة). المفاتيح المسطّحة (mail_*) لا تعيش في config فتُقرأ من الإعداد.
                $effective = Setting::get($field['key']) ?? (str_contains($field['key'], '.') ? config($field['key']) : null);

                return [
                    ...$field,
                    'is_overridden' => $override !== null && $override->value !== null,
                    // للأسرار لا نكشف القيمة؛ للبقية نعرض الفعّالة.
                    'display' => $field['type'] === 'secret'
                        ? ($override && $override->value ? '•••••• محفوظ' : '')
                        : (string) ($effective ?? ''),
                    'current_bool' => (bool) ($effective ?? false),
                ];
            })->all(),
        ])->all();

        return view('admin.settings.index', ['groups' => $groups]);
    }

    public function update(Request $request): RedirectResponse
    {
        foreach (SettingsConfig::fields() as $field) {
            $key = $field['key'];
            $type = $field['type'];
            $input = $request->input($this->inputName($key));

            if ($type === 'bool') {
                Setting::put($key, $request->boolean($this->inputName($key)), 'admin', 'bool');

                continue;
            }

            if ($type === 'int') {
                blank($input)
                    ? Setting::where('key', $key)->delete()
                    : Setting::put($key, (int) $input, 'admin', 'int');

                continue;
            }

            // السر الفارغ يعني «أبقِ الحالي»: لا نمسح مفتاحًا صالحًا بحقل فارغ.
            if ($type === 'secret') {
                if (filled($input)) {
                    Setting::put($key, $input, 'admin', 'secret');
                }

                continue;
            }

            // النص الفارغ يعني «عُد إلى .env»: نحذف التجاوز.
            if (blank($input)) {
                Setting::where('key', $key)->delete();

                continue;
            }

            Setting::put($key, $input, 'admin', 'string');
        }

        return redirect()->route('admin.settings')
            ->with('status', 'حُفظت الإعدادات وسرت مفعولها فورًا في كل مكان ذي صلة.');
    }

    /**
     * اسم حقل النموذج: نقاط المسار غير صالحة في أسماء الحقول.
     */
    private function inputName(string $key): string
    {
        return str_replace('.', '__', $key);
    }
}
