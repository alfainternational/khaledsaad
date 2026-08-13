<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Modules\Shared\I18n\TranslatedConfig;
use Illuminate\View\View;

/**
 * صفحة الأسعار العامة (بند ٢٤ من خطة الواجهات).
 *
 * كانت المستويات تظهر فقط داخل فوترة اللوحة بعد التسجيل — فالزائر المقارن
 * لا يجد ماذا يكلف أي شيء. المصدر هو جدول الخطط نفسه الذي تحكم به
 * الصلاحيات فعليًا، لا نصوص تسويقية منفصلة تنجرف عنه.
 */
class PricingController extends Controller
{
    public function index(): View
    {
        $plans = Plan::where('is_public', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Plan $plan) => [
                'key' => $plan->key,
                'name' => $plan->name,
                'price' => (int) $plan->price,
                'interval' => $plan->interval,
                'monthly_credits' => (int) $plan->monthly_credits,
                'project_limit' => (int) $plan->project_limit,
                'features' => $plan->isGoverned()
                    ? $plan->featureItems()->orderBy('plan_features.sort_order')->get()
                        ->filter(fn ($feature) => (bool) $feature->pivot->enabled)
                        ->map(fn ($feature) => trim($feature->pivot->note ?: $feature->name))
                        ->values()->all()
                    : array_values(array_filter((array) $plan->features)),
            ])
            ->values();

        return view('site.pricing', ['plans' => $plans, 'brand' => TranslatedConfig::get('brand')]);
    }
}
