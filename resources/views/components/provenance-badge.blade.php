@props(['type' => 'automated', 'label' => null])
@php
    $labels = [
        'automated' => 'تحليل آلي بقواعد ثابتة',
        'signed' => 'تحليل موقّع من خالد سعد',
        'hybrid' => 'تحليل آلي راجعه خالد سعد',
    ];
@endphp
<span {{ $attributes->class(['provenance-badge', 'provenance-badge--'.$type]) }}>
    {{ $label ?: ($labels[$type] ?? $labels['automated']) }}
</span>
