@props([
    'eyebrow',
    'title',
    'description' => null,
    'align' => 'center',
])

<header @class(['section-heading', 'section-heading--start' => $align === 'start'])>
    <p class="eyebrow">{{ $eyebrow }}</p>
    <h2>{{ $title }}</h2>
    @if ($description)
        <p>{{ $description }}</p>
    @endif
</header>
