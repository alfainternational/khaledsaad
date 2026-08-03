@php
    $coverUrl = $item->cover_image_path
        ? (str_starts_with($item->cover_image_path, 'http') || str_starts_with($item->cover_image_path, '/')
            ? $item->cover_image_path
            : \Illuminate\Support\Facades\Storage::disk('public')->url($item->cover_image_path))
        : null;
@endphp
<div @class(['content-visual', $class ?? null]) data-content-type="{{ $item->type }}">
    @if ($coverUrl)
        <img src="{{ $coverUrl }}" alt="{{ $item->title }}" loading="{{ ($eager ?? false) ? 'eager' : 'lazy' }}">
    @else
        <div class="content-visual__fallback">
            <span class="content-visual__mark">@include('site.content._icon', ['name' => $item->type])</span>
            <span>{{ ['article' => 'مقال', 'lesson' => 'درس', 'lecture' => 'محاضرة', 'course' => 'دورة'][$item->type] }}</span>
        </div>
    @endif
</div>
