@props(['value', 'label', 'toneClass', 'desc' => null])
<div {!! $attributes->merge(['class' => 'metric-card']) !!}>
    <div class="metric-top-bar {{ $toneClass }}"></div>
    <span class="metric-value counter" data-target="{{ preg_replace('/[^0-9\.]/', '', $value) }}" data-suffix="{{ preg_replace('/[0-9\.]+/', '', $value) }}">0</span>
    <span class="metric-label">{{ $label }}</span>
    @if($desc)
        <p class="metric-desc">{{ $desc }}</p>
    @endif
</div>
