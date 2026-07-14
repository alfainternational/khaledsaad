<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * إدارة مفتاح الذكاء الخاص بالحساب (BYOK).
 * المالك فقط يديره. المفتاح لا يُعاد أبداً — يُعرض مقنّعاً فقط.
 * ربط المفتاح يجعل توليدات الحساب تعمل على مفتاحه بدل رصيد المنصة.
 */
class AccountAiKeyController extends Controller
{
    /** المزوّدون المتوافقون مع OpenAI المسموح ربطهم (openrouter يمنح Claude و ChatGPT بمفتاح واحد). */
    private const PROVIDERS = ['openrouter', 'groq', 'cerebras'];

    public function show(Request $request): JsonResponse
    {
        $account = $this->ownedAccount($request);

        return response()->json([
            'data' => [
                'connected' => $account->hasByoAi(),
                'provider' => $account->ai_provider,
                'masked_key' => $account->hasByoAi()
                    ? $this->mask($account->ai_provider_key)
                    : null,
                'available_providers' => self::PROVIDERS,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $account = $this->ownedAccount($request);

        $data = $request->validate([
            'provider' => ['required', Rule::in(self::PROVIDERS)],
            'key' => ['required', 'string', 'min:20', 'max:400'],
        ], [
            'provider.in' => 'المزوّد غير مدعوم.',
            'key.min' => 'المفتاح غير صالح.',
        ]);

        $account->update([
            'ai_provider' => $data['provider'],
            'ai_provider_key' => trim($data['key']),
        ]);

        return response()->json([
            'data' => [
                'connected' => true,
                'provider' => $account->ai_provider,
                'masked_key' => $this->mask($account->ai_provider_key),
                'message' => 'تم ربط مفتاح الذكاء الخاص بك.',
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $account = $this->ownedAccount($request);

        $account->update([
            'ai_provider' => null,
            'ai_provider_key' => null,
        ]);

        return response()->json([
            'data' => [
                'connected' => false,
                'message' => 'أُلغي ربط المفتاح. عادت التوليدات لرصيد المنصة.',
            ],
        ]);
    }

    /** يحل الحساب الحالي ويتأكد أن الطالب هو مالكه. */
    private function ownedAccount(Request $request): \App\Domain\Account\Models\Account
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $account = $workspace->account()->firstOrFail();

        abort_unless(
            $request->user()->id === $account->owner_user_id,
            403,
            'إدارة مفتاح الذكاء متاحة لمالك الحساب فقط.'
        );

        return $account;
    }

    /** يعرض آخر 4 أحرف فقط. */
    private function mask(string $key): string
    {
        $tail = substr($key, -4);

        return str_repeat('•', 8).$tail;
    }
}
