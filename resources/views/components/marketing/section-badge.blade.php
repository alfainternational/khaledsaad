@props(['text'])
<div {!! $attributes->merge(['class' => 'section-badge']) !!}>
    <span class="section-dot"></span>
    <span class="section-badge-text">{{ $text }}</span>
</div>
