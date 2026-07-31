<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * انتحال مستخدم لخدمة العملاء (بند ٢١): «شاشتي لا تعمل» تُشخَّص بدقيقة
 * بدل سلسلة مراسلات. بشارة تحذير دائمة، وخروج سريع، وسطر تدقيق للدخول
 * والخروج — ولا انتحال لآدمن آخر أبدًا.
 */
class ImpersonationController extends Controller
{
    public function start(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isAdmin(), 403, 'لا انتحال لحساب إداري.');
        abort_if($request->session()->has('impersonator_id'), 403, 'أنهِ الانتحال الحالي أولًا.');

        AuditLog::write('impersonation.start', $user);

        $adminId = $request->user()->id;
        auth()->login($user);
        $request->session()->put('impersonator_id', $adminId);

        return redirect()->route('app.dashboard');
    }

    public function stop(Request $request): RedirectResponse
    {
        $adminId = $request->session()->pull('impersonator_id');

        abort_if($adminId === null, 403);

        $admin = User::where('id', $adminId)->where('is_admin', true)->firstOrFail();

        AuditLog::write('impersonation.stop', auth()->user());

        auth()->login($admin);
        $request->session()->regenerate();
        $request->session()->forget('impersonator_id');

        return redirect()->route('admin.users.index')->with('status', 'انتهى الانتحال وعدت لحسابك.');
    }
}
