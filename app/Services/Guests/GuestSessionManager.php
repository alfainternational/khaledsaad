<?php

namespace App\Services\Guests;

use App\Models\GuestSession;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * تجربة بلا حساب.
 *
 * الفكرة: صاحب المشروع لا يعرفنا بعد، وطلب بريده قبل أن يرى شيئًا يطرده.
 * لذلك نفتح له مساحة مؤقتة يجرّب فيها فعلًا، وحين يقتنع ويسجّل تنتقل
 * المساحة نفسها إلى حسابه بما فيها من إجابات ونتائج.
 */
class GuestSessionManager
{
    public const COOKIE = 'guest_token';

    private const LIFETIME_DAYS = 30;

    /**
     * جلسة الزائر الحالية إن وُجدت وكانت صالحة.
     */
    public function current(Request $request): ?GuestSession
    {
        $token = $request->cookie(self::COOKIE);

        if (! is_string($token) || $token === '') {
            return null;
        }

        return GuestSession::where('token_hash', GuestSession::hash($token))
            ->whereNull('claimed_by')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();
    }

    /**
     * جلسة زائر جديدة مع مساحة عمل ومشروع مؤقت جاهز للتجربة.
     */
    public function start(Request $request, string $projectName = 'مشروعي'): GuestSession
    {
        $existing = $this->current($request);

        if ($existing !== null) {
            return $existing;
        }

        $token = Str::random(48);

        $session = GuestSession::create([
            'token_hash' => GuestSession::hash($token),
            'expires_at' => now()->addDays(self::LIFETIME_DAYS),
        ]);

        Cookie::queue(
            Cookie::make(self::COOKIE, $token, self::LIFETIME_DAYS * 24 * 60, httpOnly: true, sameSite: 'lax'),
        );

        $this->workspaceFor($session, $projectName);

        return $session;
    }

    /**
     * المشروع المؤقت لهذه الجلسة — يُنشأ عند أول حاجة إليه.
     */
    public function project(GuestSession $session, string $projectName = 'مشروعي'): Project
    {
        $workspace = $this->workspaceFor($session, $projectName);

        return $workspace->projects()->firstOrCreate(
            ['slug' => 'guest-'.$session->id],
            ['name' => $projectName, 'stage' => 'growth'],
        );
    }

    /**
     * نقل كل ما جرّبه الزائر إلى حسابه الجديد.
     *
     * ننقل المساحة نفسها لا نسخة منها: الروابط والتقارير والمهام تبقى صالحة.
     */
    public function claim(GuestSession $session, User $user): void
    {
        if (! $session->isClaimable()) {
            return;
        }

        DB::transaction(function () use ($session, $user): void {
            Workspace::where('guest_session_id', $session->id)->update([
                'owner_id' => $user->id,
                'guest_session_id' => null,
            ]);

            $session->runs()->update(['user_id' => $user->id]);

            $session->forceFill([
                'claimed_by' => $user->id,
                'claimed_at' => now(),
            ])->save();
        });

        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    private function workspaceFor(GuestSession $session, string $projectName): Workspace
    {
        return Workspace::firstOrCreate(
            ['guest_session_id' => $session->id],
            [
                'owner_id' => null,
                'name' => $projectName,
                'slug' => 'guest-'.$session->id.'-'.Str::random(6),
                'type' => 'guest',
            ],
        );
    }
}
