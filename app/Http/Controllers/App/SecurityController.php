<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * أمان الحساب (بند ٢٣): خطوة التحقق الثانية + الأجهزة المتصلة.
 *
 * قائمة الجلسات من جدول sessions نفسه (SESSION_DRIVER=database)، فتعكس
 * الواقع لا سجلًّا موازيًا. «خروج البقية» يمر بكلمة المرور ثم يبطل
 * الجلسات الأخرى عبر logoutOtherDevices + حذف صفوفها.
 */
class SecurityController extends Controller
{
    public function index(Request $request): View
    {
        $sessions = DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($session) => [
                'id' => $session->id,
                'current' => $session->id === $request->session()->getId(),
                'ip' => $session->ip_address,
                'agent' => $this->readableAgent((string) $session->user_agent),
                'last_active' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
            ]);

        return view('app.security', [
            'sessions' => $sessions,
            'otpEnabled' => (bool) $request->user()->two_factor_email_enabled,
        ]);
    }

    public function toggleOtp(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'two_factor_email_enabled' => ! $request->user()->two_factor_email_enabled,
        ])->save();

        return back()->with('status', $request->user()->two_factor_email_enabled
            ? 'فُعّلت خطوة التحقق بالبريد — سيصلك رمز مع كل دخول.'
            : 'أُلغيت خطوة التحقق بالبريد.');
    }

    public function logoutOthers(Request $request): RedirectResponse
    {
        $request->validate(['password' => 'required|string']);

        if (! Hash::check($request->string('password'), $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'كلمة المرور غير صحيحة.']);
        }

        Auth::logoutOtherDevices($request->string('password'));

        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return back()->with('status', 'سُجّل خروجك من كل الأجهزة الأخرى.');
    }

    /** وصف مقروء بدل سلسلة الـ user agent الخام. */
    private function readableAgent(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg') => 'متصفح Edge',
            str_contains($agent, 'Chrome') => 'متصفح Chrome',
            str_contains($agent, 'Safari') && ! str_contains($agent, 'Chrome') => 'متصفح Safari',
            str_contains($agent, 'Firefox') => 'متصفح Firefox',
            default => 'متصفح غير معروف',
        };

        $device = match (true) {
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'جهاز iOS',
            str_contains($agent, 'Android') => 'جهاز أندرويد',
            str_contains($agent, 'Windows') => 'جهاز ويندوز',
            str_contains($agent, 'Macintosh') => 'جهاز ماك',
            default => 'جهاز غير معروف',
        };

        return $browser.' على '.$device;
    }
}
