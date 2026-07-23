@props([
    'compact' => false,
    'light' => false,
])

@php
    // الشعار بخلفية شفافة: يعمل فوق الأبيض والداكن والصور دون إطار أبيض حوله.
    // النسخة الفاتحة تقلب الاسم إلى أبيض وتُبقي العلامة بألوانها فوق الخلفيات الداكنة.
    $asset = match (true) {
        $compact => 'assets/brand/khaled-saad-mark.png',
        $light => 'assets/brand/khaled-saad-light.png',
        default => 'assets/brand/khaled-saad-approved.png',
    };
    $width = $compact ? 415 : 1183;
    $height = $compact ? 304 : 314;
@endphp

<span
    data-brand-logo="approved"
    role="img"
    aria-label="شعار خالد سعد"
    {{ $attributes->class(['brand-logo', 'brand-logo--compact' => $compact, 'brand-logo--light' => $light]) }}
>
    <img
        class="brand-logo__image"
        src="{{ asset($asset) }}"
        alt=""
        width="{{ $width }}"
        height="{{ $height }}"
        decoding="async"
    >
</span>
