@props(['page', 'eyebrow' => 'المنصة'])

@if($page)
    <div class="section-badge mb-4">
        <span class="section-dot"></span>
        <span class="section-badge-text">{{ $eyebrow }}</span>
    </div>
    <h1 class="heading-lg mb-3">{{ $page->title }}</h1>
    @if($page->subtitle)
        <p class="text-body-lg text-muted mb-6">{{ $page->subtitle }}</p>
    @endif
@endif
