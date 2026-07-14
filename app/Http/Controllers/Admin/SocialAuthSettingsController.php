<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Services\AuditLogger;
use App\Http\Controllers\Controller;
use App\Support\Settings\SettingsStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * إعدادات تسجيل الدخول الاجتماعي (Socialite) من لوحة الآدمن.
 *
 * تُخزَّن القيم في SettingsStore فوق config() (الدستور §32) فتعمل فوراً دون لمس .env
 * ولا إعادة نشر. client_secret سرّ: «اتركه فارغاً للإبقاء» ولا يُسجَّل في التدقيق.
 */
class SocialAuthSettingsController extends Controller
{
    /** المزوّدون: مفتاح عام → [التسمية, سائق Socialite في config]. */
    private const PROVIDERS = [
        'google' => ['label' => 'Google', 'driver' => 'google'],
        'facebook' => ['label' => 'Facebook', 'driver' => 'facebook'],
        'twitter' => ['label' => 'X (Twitter)', 'driver' => 'twitter-oauth-2'],
        'linkedin' => ['label' => 'LinkedIn', 'driver' => 'linkedin-openid'],
    ];

    public function __construct(
        private readonly SettingsStore $settings,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(): View
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $providers = [];

        foreach (self::PROVIDERS as $key => $meta) {
            $driver = $meta['driver'];
            $clientId = (string) config("services.{$driver}.client_id");
            $secret = (string) config("services.{$driver}.client_secret");

            $providers[$key] = [
                'label' => $meta['label'],
                'client_id' => $clientId,
                'secret_hint' => $this->maskKey($secret),
                'callback' => $appUrl.'/api/v1/auth/social/'.$key.'/callback',
                'ready' => $clientId !== '' && $secret !== '',
            ];
        }

        return view('admin.social-auth.index', ['providers' => $providers]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (array_keys(self::PROVIDERS) as $key) {
            $rules[$key.'_client_id'] = 'nullable|string|max:255';
            $rules[$key.'_client_secret'] = 'nullable|string|max:400';
        }
        $validated = $request->validate($rules);

        $set = [];
        $secretsChanged = [];

        foreach (self::PROVIDERS as $key => $meta) {
            $driver = $meta['driver'];

            // client_id ليس سرّاً — يُكتب دائماً من الحقل المعبّأ (يشمل الإفراغ للإلغاء).
            if ($request->has($key.'_client_id')) {
                $set["services.{$driver}.client_id"] = trim((string) $validated[$key.'_client_id']);
            }

            // client_secret سرّ — يُكتب فقط عند إدخال قيمة جديدة.
            if (filled($validated[$key.'_client_secret'] ?? null)) {
                $set["services.{$driver}.client_secret"] = trim((string) $validated[$key.'_client_secret']);
                $secretsChanged[] = $key;
            }
        }

        if ($set !== []) {
            $this->settings->setMany($set);
        }

        // تدقيق بلا كشف الأسرار: نسجّل أي مزوّد تغيّر سرّه، لا القيمة.
        $this->auditLogger->record(
            action: 'admin.social_auth.updated',
            targetType: 'social_auth_settings',
            actor: $request->user(),
            meta: ['secrets_changed' => $secretsChanged],
        );

        return back()->with('success', 'تم تحديث إعدادات تسجيل الدخول الاجتماعي. تعمل فوراً.');
    }

    /** إخفاء السرّ للعرض. */
    private function maskKey(string $key): string
    {
        if ($key === '') {
            return 'غير مضبوط';
        }

        return mb_strlen($key) <= 4 ? '••••' : '••••'.mb_substr($key, -4);
    }
}
