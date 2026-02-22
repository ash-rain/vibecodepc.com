<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SubscriptionTier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('billing.index', [
            'user' => $user,
            'currentTier' => $user->subscriptionTier(),
            'invoices' => $user->invoices(),
        ]);
    }

    public function subscribe(Request $request): View
    {
        $user = $request->user();
        $isSubscribed = $user->subscribed('default');

        $tiers = collect([SubscriptionTier::Starter, SubscriptionTier::Pro, SubscriptionTier::Team])
            ->map(fn (SubscriptionTier $tier) => [
                'tier' => $tier,
                'label' => $tier->label(),
                'price' => $tier->price(),
                'maxSubdomains' => $tier->maxSubdomains(),
                'bandwidthGb' => $tier->monthlyBandwidthGb(),
                'priceId' => $tier->stripePriceId(),
            ]);

        return view('billing.subscribe', [
            'user' => $user,
            'currentTier' => $user->subscriptionTier(),
            'tiers' => $tiers,
            'isSubscribed' => $isSubscribed,
            'intent' => $isSubscribed ? null : $user->createSetupIntent(),
        ]);
    }

    public function processSubscription(Request $request): RedirectResponse
    {
        $request->validate([
            'payment_method' => ['required', 'string'],
            'tier' => ['required', 'string', 'in:starter,pro,team'],
        ]);

        $tier = SubscriptionTier::from($request->input('tier'));
        $priceId = $tier->stripePriceId();

        if (! $priceId) {
            return redirect()->route('billing.index')
                ->with('error', 'Invalid subscription tier.');
        }

        $user = $request->user();

        $user->newSubscription('default', $priceId)
            ->create($request->input('payment_method'));

        return redirect()->route('billing.index')
            ->with('success', "Subscribed to {$tier->label()} plan!");
    }

    public function changePlan(Request $request): RedirectResponse
    {
        $request->validate([
            'tier' => ['required', 'string', 'in:starter,pro,team'],
        ]);

        $user = $request->user();
        $subscription = $user->subscription('default');

        if (! $subscription || $subscription->canceled()) {
            return redirect()->route('billing.subscribe')
                ->with('error', 'You need an active subscription to change plans.');
        }

        $tier = SubscriptionTier::from($request->input('tier'));
        $priceId = $tier->stripePriceId();

        if (! $priceId) {
            return redirect()->route('billing.index')
                ->with('error', 'Invalid subscription tier.');
        }

        $subscription->swap($priceId);

        return redirect()->route('billing.index')
            ->with('success', "Plan changed to {$tier->label()}!");
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->user()->subscription('default')?->cancel();

        return redirect()->route('billing.index')
            ->with('success', 'Subscription cancelled. You will retain access until the end of your billing period.');
    }

    public function resume(Request $request): RedirectResponse
    {
        $request->user()->subscription('default')?->resume();

        return redirect()->route('billing.index')
            ->with('success', 'Subscription resumed!');
    }
}
