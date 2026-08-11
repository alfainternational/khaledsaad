@props(['title', 'description' => null, 'eyebrow' => null])

<header {{ $attributes->class('ui-page-header') }}>
    <div class="ui-page-header__copy">
        @if ($eyebrow)<p class="eyebrow">{{ $eyebrow }}</p>@endif
        <h1>{{ $title }}</h1>
        @if ($description)<p class="ui-page-header__description">{{ $description }}</p>@endif
        {{ $meta ?? '' }}
    </div>
    @isset($actions)
        <div class="page-head__actions">{{ $actions }}</div>
    @endisset
</header>
