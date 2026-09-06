<?php

declare(strict_types=1);

namespace App\Support\Preflight;

enum PreflightOutcome: string
{
    case Ready = 'ready';
    case InsufficientCredits = 'insufficient_credits';
    case PlanLimited = 'plan_limited';

    /** يكفي لبعض أدوات الحزمة لا كلّها — يبدأ بالأعلى أثرًا. */
    case PartialBudget = 'partial_budget';

    /**
     * لا مزوّد يخدم، أو بلغ الإنفاق سقفه.
     *
     * منفصلة عن `InsufficientCredits` عمدًا: هذه حالتنا لا حالته، ولا
     * يُطلب منه حيالها شيء (INV-8). دمجُهما هو ما أنتج «رصيدك ٠» فوق
     * عطلٍ في اشتراكنا نحن.
     */
    case ProviderUnavailable = 'provider_unavailable';

    case Unavailable = 'unavailable';
}
