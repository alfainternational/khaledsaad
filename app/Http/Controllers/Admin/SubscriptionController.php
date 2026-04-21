<?php

namespace App\Http\Controllers\Admin;

use App\Application\Admin\Accounts\UpdateAccountSubscriptionAction;
use App\Application\Admin\Billing\ExtendSubscriptionPeriodAction;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExtendSubscriptionRequest;
use App\Http\Requests\Admin\UpdateAccountSubscriptionRequest;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $subscriptions = Subscription::query()
            ->with('account.owner', 'plan')
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->integer('plan_id') > 0, fn ($query) => $query->where('plan_id', $request->integer('plan_id')))
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->value();
                $query->whereHas('account', function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('billing_email', 'like', "%{$search}%")
                        ->orWhereHas('owner', function ($oq) use ($search): void {
                            $oq->where('email', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'plans' => Plan::query()->orderBy('monthly_price')->get(),
            'subscriptionStatuses' => ['trialing', 'active', 'past_due', 'canceled', 'pending_payment'],
        ]);
    }

    public function show(Subscription $subscription): View
    {
        $subscription->load('account.owner', 'plan');

        return view('admin.subscriptions.show', [
            'subscription' => $subscription,
            'plans' => Plan::query()->orderBy('monthly_price')->get(),
            'subscriptionStatuses' => ['trialing', 'active', 'past_due', 'canceled', 'pending_payment'],
        ]);
    }

    public function update(
        UpdateAccountSubscriptionRequest $request,
        Subscription $subscription,
        UpdateAccountSubscriptionAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse {
        $account = $subscription->account()->firstOrFail();
        $action->handle($account, $request->validated(), $request->user());

        return redirect()
            ->route('admin.subscriptions.show', $subscription->refresh())
            ->with('status', $flash->subscriptionUpdated());
    }

    public function extend(
        ExtendSubscriptionRequest $request,
        Subscription $subscription,
        ExtendSubscriptionPeriodAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse {
        $action->handle($subscription, (int) $request->validated('days'), $request->user());

        return back()->with('status', $flash->updated('فترة الاشتراك'));
    }
}
