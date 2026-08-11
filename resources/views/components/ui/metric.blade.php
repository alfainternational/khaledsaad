@props(['value', 'label', 'basis' => null, 'coverage' => null])

<article {{ $attributes->class('ui-metric') }}>
    <strong class="ui-metric__value">{{ $value }}</strong>
    <span class="ui-metric__label">{{ $label }}</span>
    @if ($basis || $coverage)
        <small class="ui-metric__basis">
            {{ $basis }}@if ($basis && $coverage) · @endif{{ $coverage }}
        </small>
    @endif
</article>
