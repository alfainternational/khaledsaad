@php
    $coverPath = ($variant ?? null) === 'card'
        ? ($item->learning_meta['cover']['card'] ?? $item->cover_image_path)
        : $item->cover_image_path;
    $coverUrl = $coverPath
        ? (str_starts_with($coverPath, 'http') || str_starts_with($coverPath, '/')
            ? $coverPath
            : \Illuminate\Support\Facades\Storage::disk('public')->url($coverPath))
        : null;
    $altText = $alt ?? $item->learning_meta['cover']['alt'] ?? $item->title;
@endphp
<div @class(['content-visual', $class ?? null]) data-content-type="{{ $item->type }}">
    @if ($coverUrl)
        <img src="{{ $coverUrl }}" alt="{{ $altText }}" loading="{{ ($eager ?? false) ? 'eager' : 'lazy' }}">
    @else
        <div class="content-visual__fallback">
            <span class="content-visual__mark">@include('site.content._icon', ['name' => $item->type])</span>
            <span>{{ ['article' => 'مقال', 'lesson' => 'درس', 'lecture' => 'محاضرة', 'course' => 'دورة'][$item->type] }}</span>
        </div>
    @endif
</div>
