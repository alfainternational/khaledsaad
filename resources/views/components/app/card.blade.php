@props(['title' => null])

<article {{ $attributes->merge(['class' => 'card']) }}>
    @if ($title)
        <div class="app-section-head">
            <h3 class="heading-sm">{{ $title }}</h3>
        </div>
    @endif

    {{ $slot }}
</article>
