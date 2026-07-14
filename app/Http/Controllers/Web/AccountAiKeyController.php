<?php

namespace App\Http\Controllers\Web;

use App\Domain\Account\Models\Account;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * نظير الويب لإدارة مفتاح الذكاء الخاص بالحساب (BYOK).
 * المالك فقط يديره. المفتاح لا يُعرض أبداً — يُخزَّن مشفَّراً.
 * ربط المفتاح يجعل توليدات الحساب تعمل على مفتاحه بدل رصيد المنصة.
 */
class AccountAiKeyController extends Controller
{
    use InteractsWithWorkspaceContext;

    /** المزوّدون المتوافقون مع OpenAI المسموح ربطهم (openrouter يمنح Claude و ChatGPT بمفتاح واحد). */
    public const PROVIDERS = ['openrouter', 'groq', 'cerebras'];

    public function update(Request $request): RedirectResponse
    {
        $account = $this->ownedAccount($request);

        $data = $request->validate([
            'provider' => ['required', Rule::in(self::PROVIDERS)],
            'key' => ['required', 'string', 'min:20', 'max:400'],
        ], [
            'provider.required' => 'اختر المزوّد.',
            'provider.in' => 'المزوّد غير مدعوم.',
            'key.required' => 'أدخل المفتاح.',
            'key.min' => 'المفتاح غير صالح.',
        ]);

        $account->update([
            'ai_provider' => $data['provider'],
            'ai_provider_key' => trim($data['key']),
        ]);

        return back()->with('status', 'تم ربط مفتاح الذكاء الخاص بك.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $account = $this->ownedAccount($request);

        $account->update([
            'ai_provider' => null,
            'ai_provider_key' => null,
        ]);

        return back()->with('status', 'أُلغي ربط المفتاح. عادت التوليدات لرصيد المنصة.');
    }

    /** يحل الحساب الحالي ويتأكد أن الطالب هو مالكه. */
    private function ownedAccount(Request $request): Account
    {
        $account = $this->currentWorkspace($request)->account()->firstOrFail();

        abort_unless(
            $request->user()->id === $account->owner_user_id,
            403,
            'إدارة مفتاح الذكاء متاحة لمالك الحساب فقط.'
        );

        return $account;
    }
}
