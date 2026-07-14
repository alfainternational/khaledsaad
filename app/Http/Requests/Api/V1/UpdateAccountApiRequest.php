<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Dashboard\AwarenessCatalog;
use App\Support\Dashboard\ContentLocaleCatalog;
use App\Support\Dashboard\GoalCatalog;
use App\Support\Dashboard\PathCatalog;
use App\Support\Dashboard\PersonaCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * تحديث الحساب من واجهة الـ API (الموبايل).
 *
 * يختلف عن نسخة الويب الصارمة: الهوية الأساسية والمساحة تبقى مطلوبة (تُعرض
 * دائماً ومملوءة مسبقاً)، بينما حقول الملف التسويقي اختيارية (`nullable`) حتى
 * يستطيع مستخدم لم يكمل الإعداد الأولي حفظ تغيير بسيط (كاسمه) دون أن يُجبر على
 * ملء الملف التسويقي كاملاً. التحديث في الـ controller يدمج جزئياً ولا يمسح
 * القيم غير المُرسلة.
 */
class UpdateAccountApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // الهوية الأساسية والمساحة — مطلوبة (منخفضة الاحتكاك، مملوءة مسبقاً).
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['required', 'string', Rule::in(['ar', 'en'])],
            'account_name' => ['required', 'string', 'max:255'],
            'billing_email' => ['required', 'email', 'max:255'],
            'workspace_name' => ['required', 'string', 'max:255'],
            'workspace_type' => ['required', 'string', Rule::in(['personal', 'team', 'agency'])],

            // الملف التسويقي — اختياري: يُتحقّق فقط عند الإرسال، ولا يُمسح عند غيابه.
            'persona' => ['nullable', 'string', Rule::in(array_keys(PersonaCatalog::all()))],
            'awareness_level' => ['nullable', 'string', Rule::in(array_keys(AwarenessCatalog::all()))],
            'primary_goal' => ['nullable', 'string', Rule::in(array_keys(GoalCatalog::all()))],
            'recommended_path' => ['nullable', 'string', Rule::in(array_keys(PathCatalog::all()))],
            'audience' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:120'],
            'content_locale' => ['nullable', 'string', Rule::in(array_keys(ContentLocaleCatalog::all()))],
            'current_challenge' => ['nullable', 'string', 'max:255'],
        ];
    }
}
