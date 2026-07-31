@props(['updatedAt' => null, 'coverage' => null, 'basis' => null])

@php
    // دستور §13: كل شاشة برقم متغيّر تعرض آخر تحديث ونسبة التغطية.
    // الفجوة تُعلن ولا تُخفى (§4.3): تغطية ناقصة تُقال صراحة لا تُجمَّل.
    $stamp = $updatedAt ? \Illuminate\Support\Carbon::parse($updatedAt) : null;
@endphp

<p {{ $attributes->merge(['class' => 'freshness-line']) }} role="note">
    @if ($stamp)
        <span>آخر تحديث: <time datetime="{{ $stamp->toIso8601String() }}">{{ $stamp->translatedFormat('j F Y — H:i') }}</time></span>
    @else
        <span>لم يُحدَّث بعد</span>
    @endif

    @if ($coverage !== null)
        <span class="freshness-line__sep" aria-hidden="true">·</span>
        <span>
            التغطية: {{ \App\Support\Presentation\Num::pct($coverage) }}
            @if ($coverage < 100)
                <span class="freshness-line__gap">— بيانات ناقصة، والدرجة محسوبة على المتوفر فقط</span>
            @endif
        </span>
    @endif

    @if ($basis)
        <span class="freshness-line__sep" aria-hidden="true">·</span>
        <span>{{ $basis }}</span>
    @endif
</p>
