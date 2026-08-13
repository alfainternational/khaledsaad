@props(['equation'])
<div {{ $attributes->class(['score-equation']) }} dir="ltr" aria-label="معادلة الدرجة">
    <span>{{ number_format((float) ($equation['raw'] ?? 0), 1) }}</span>
    <span>÷</span>
    <span>{{ number_format((float) ($equation['max'] ?? 0), 1) }}</span>
    <span>× 100 =</span>
    <strong>{{ number_format((float) ($equation['value'] ?? 0), 0) }}</strong>
</div>
