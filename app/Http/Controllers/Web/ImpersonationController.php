<?php

namespace App\Http\Controllers\Web;

use App\Application\Admin\Users\StopImpersonationAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function stop(Request $request, StopImpersonationAction $action): RedirectResponse
    {
        $action->handle($request);

        return redirect()
            ->route('admin.dashboard')
            ->with('status', 'تمت العودة إلى حساب الإدارة وإنهاء وضع الانتحال.');
    }
}
