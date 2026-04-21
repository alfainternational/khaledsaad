@props(['title', 'body', 'toneClass'])
<div {!! $attributes->merge(['class' => 'benefit-card ' . $toneClass]) !!}>
    <div class="benefit-dot">
        <div class="benefit-dot-inner"></div>
    </div>
    <div>
        <h3 class="benefit-title">{{ $title }}</h3>
        <p class="benefit-body">{{ $body }}</p>
    </div>
</div>
