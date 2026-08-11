@php
    $toolIcon = match ($tool['key']) {
        'marketing-score' => 'score',
        'brand-clarity' => 'reason',
        'audience-map' => 'account',
        'competitor-lens' => 'search',
        'offer-builder' => 'decision',
        'content-engine' => 'article',
        'channel-fit' => 'compass',
        'seo-compass' => 'target',
        'campaign-planner' => 'calendar',
        'funnel-audit' => 'timeline',
        'agency-brief' => 'briefcase',
        default => 'compass',
    };
@endphp

<a href="{{ route('tools.show', $tool['key']) }}"
    @class([
        'catalog-card',
        'catalog-card--featured' => $featured ?? false,
        'catalog-card--soon' => ! $tool['is_runnable'],
    ])>
    <div class="catalog-card__head">
        <span class="catalog-card__icon"><x-section-icon :name="$toolIcon" /></span>
        <span class="catalog-card__category">{{ $tool['category'] }}</span>
        @unless ($tool['is_runnable'])
            <span class="pill pill--soon">قريبًا</span>
        @endunless
    </div>

    @if ($tool['pain'])
        <p class="catalog-card__pain">«{{ $tool['pain'] }}»</p>
    @endif

    <h3>{{ $tool['title'] }}</h3>
    <p class="catalog-card__desc">{{ $tool['promise'] ?: $tool['description'] }}</p>

    <span class="catalog-card__link">
        اعرف التفاصيل وابدأ <b aria-hidden="true">←</b>
        @if ($tool['duration_minutes'])
            <em>{{ $tool['duration_minutes'] }} دقائق تقريبًا</em>
        @endif
    </span>
</a>
