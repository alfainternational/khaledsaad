@props(['title', 'description' => null, 'icon' => 'empty'])

<section {{ $attributes->class('ui-empty-state') }}>
    <x-ui.icon :name="$icon" />
    <h2 class="section-title">{{ $title }}</h2>
    @if ($description)<p class="muted">{{ $description }}</p>@endif
    {{ $slot }}
</section>
