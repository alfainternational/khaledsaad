@extends('layouts.public')
@section('layout', 'marketing')
@section('title', ($content->seo_title ?: $content->title).' | خالد سعد')
@section('description', $content->seo_description ?: $content->excerpt)
@if ($content->cover_image_path)
    @section('og_image', str_starts_with($content->cover_image_path, 'http') || str_starts_with($content->cover_image_path, '/') ? $content->cover_image_path : \Illuminate\Support\Facades\Storage::disk('public')->url($content->cover_image_path))
@endif

@section('content')
    @include('partials.site-header')

    <main id="main-content">
        <article class="content-page">
            <header class="content-page__hero">
                <div class="container content-page__hero-inner">
                    <a class="text-link" href="{{ route('content.index') }}">العودة إلى المكتبة</a>
                    <div class="content-page__meta">
                        <span>{{ ['article' => 'مقال', 'lesson' => 'درس', 'lecture' => 'محاضرة', 'course' => 'دورة'][$content->type] }}</span>
                        @if ($content->duration_minutes) <span>{{ $content->duration_minutes }} دقيقة</span> @endif
                        <span>{{ $content->published_at?->translatedFormat('d F Y') }}</span>
                    </div>
                    <h1>{{ $content->title }}</h1>
                    @if ($content->excerpt) <p>{{ $content->excerpt }}</p> @endif
                </div>
            </header>

            @if ($content->cover_image_path)
                <div class="container content-page__cover">
                    <img src="{{ str_starts_with($content->cover_image_path, 'http') || str_starts_with($content->cover_image_path, '/') ? $content->cover_image_path : \Illuminate\Support\Facades\Storage::disk('public')->url($content->cover_image_path) }}" alt="{{ $content->title }}">
                </div>
            @endif

            <div class="container content-page__layout">
                @if ($unlocked)
                    <div class="content-prose">
                        @if ($content->video_url)
                            <div class="content-video"><a href="{{ $content->video_url }}" target="_blank" rel="noopener noreferrer">مشاهدة الفيديو التعليمي</a></div>
                        @endif

                        {!! $content->body_html !!}

                        @if ($content->type === 'course')
                            @include('site.content._curriculum')
                        @endif
                    </div>
                @else
                    @include('site.content._gate')
                @endif
            </div>
        </article>
    </main>

    @include('partials.site-footer')
@endsection
