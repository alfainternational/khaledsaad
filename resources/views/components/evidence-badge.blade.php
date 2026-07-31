@props(['level' => 'inferred', 'compact' => false])

@php
    // مفردة الدليل الواحدة (دستور §4.1): measured / derived / inferred — لا رابع لها.
    // أي مخرج inferred يحمل كلمة «فرضية» نصًا لا لونًا فقط، حتى تصل لقارئ
    // لا يميز الألوان ولقارئ الشاشة معًا.
    $meta = match ($level) {
        'measured' => ['label' => 'مُقاس', 'hint' => 'رُصد فعليًا من مصدر'],
        'derived' => ['label' => 'محسوب', 'hint' => 'حُسب من بيانات مرصودة'],
        default => ['label' => 'فرضية', 'hint' => 'استنتاج منهجي يحتاج قياسًا ليتأكد'],
    };
    $levelClass = in_array($level, ['measured', 'derived'], true) ? $level : 'inferred';
@endphp

<span {{ $attributes->merge(['class' => 'evidence-badge evidence-badge--'.$levelClass]) }}
    title="{{ $meta['hint'] }}">
    <span class="evidence-badge__dot" aria-hidden="true"></span>
    {{ $meta['label'] }}
    @unless ($compact)
        <span class="sr-only">— {{ $meta['hint'] }}</span>
    @endunless
</span>
