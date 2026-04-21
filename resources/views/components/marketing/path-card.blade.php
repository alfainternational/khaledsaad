@props(['name', 'summary', 'href', 'toneClass', 'index'])
<a href="{{ $href }}" {!! $attributes->merge(['class' => 'path-card ' . $toneClass]) !!}>
    <div class="mb-5 path-card-mode">المسار {{ $index + 1 }}</div>
    <h3 class="path-card-title">{{ $name }}</h3>
    <p class="path-card-body">{{ $summary }}</p>
    <div class="path-card-cta">
        بدء المسار
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
    </div>
</a>
