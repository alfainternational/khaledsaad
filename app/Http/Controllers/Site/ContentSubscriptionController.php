<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Services\Content\ContentAccessService;
use App\Services\Content\ContentSubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContentSubscriptionController extends Controller
{
    public function __construct(private readonly ContentSubscriptionService $subscriptions) {}

    public function store(Request $request, Content $content): RedirectResponse
    {
        abort_unless($content->isPublished(), 404);

        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'consent' => ['accepted'],
        ], [
            'consent.accepted' => 'وافق على حفظ بريدك لفتح المحتوى.',
        ]);

        $result = $this->subscriptions->subscribe($data['email'], true);
        $request->session()->put(ContentAccessService::SESSION_KEY, $result['token']);

        return redirect()->route('content.show', $content)->with('success', 'تم فتح المحتوى على هذا الجهاز.');
    }
}
