<?php

declare(strict_types=1);

namespace App\Support\Navigation;

use Illuminate\Support\Facades\Route as RouteFacade;

final class NavItem
{
    public function __construct(
        public readonly string $label,
        public readonly ?string $route,
        public readonly NavState $state = NavState::Available,
        /** @var array<int, string> أنماط أسماء المسارات التي تُبرز هذا العنصر. */
        public readonly array $activePatterns = [],
        public readonly ?string $badge = null,
    ) {}

    /**
     * عنصر «قريبًا»: يُعلن أنه غير جاهز بدل أن يوجّه إلى بديل يشبهه.
     *
     * الصدق أرخص من الادّعاء: زرٌّ معطّل مكتوب عليه «قريبًا» يُبقي الثقة،
     * ورابطٌ يعمل ويفتح شيئًا آخر يُنهيها.
     */
    public static function comingSoon(string $label): self
    {
        return new self($label, null, NavState::ComingSoon, badge: __('قريبًا'));
    }

    public function url(): ?string
    {
        return $this->route !== null && RouteFacade::has($this->route)
            ? route($this->route)
            : null;
    }

    public function isActive(): bool
    {
        return $this->activePatterns !== [] && request()->routeIs(...$this->activePatterns);
    }

    public function isAvailable(): bool
    {
        return $this->state === NavState::Available && $this->url() !== null;
    }
}
