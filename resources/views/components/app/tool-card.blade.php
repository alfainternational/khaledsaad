@props(['name', 'description', 'status', 'actionText', 'href', 'latestSummary' => null])

@php
    $plainDesc = trim(strip_tags((string) $description));
@endphp

<article
    class="card card-nested"
    data-tool-card
    data-tool-state="{{ $status }}"
    data-tool-title="{{ mb_strtolower($name) }}"
    data-tool-body="{{ mb_strtolower($plainDesc) }}"
>
    <h3 class="heading-sm mb-2">{{ $name }}</h3>
    <p class="text-body mb-4">{{ $description ?: 'لا يوجد وصف بعد.' }}</p>
    @if ($latestSummary)
        <p class="text-caption mb-4">آخر ناتج: {{ $latestSummary }}</p>
    @endif

    <div class="app-inline-actions">
        @if ($status !== 'locked')
            <a href="{{ $href }}" class="btn btn-primary btn-sm">{{ $actionText }}</a>
        @else
            <span class="btn btn-ghost btn-sm" disabled>مغلقة</span>
        @endif
    </div>
</article>
