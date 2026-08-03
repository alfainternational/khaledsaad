@extends('layouts.public')
@section('layout', 'marketing')
@section('title', ($content->seo_title ?: $content->title).' | خالد سعد')
@section('description', $content->seo_description ?: $content->excerpt)
@if ($content->cover_image_path)
    @section('og_image', str_starts_with($content->cover_image_path, 'http') || str_starts_with($content->cover_image_path, '/') ? $content->cover_image_path : \Illuminate\Support\Facades\Storage::disk('public')->url($content->cover_image_path))
@endif

@php($typeLabels = ['article' => 'مقال', 'lesson' => 'درس', 'lecture' => 'محاضرة', 'course' => 'دورة'])

@section('content')
    @include('partials.site-header')

    <main id="main-content">
        <article class="content-page">
            <header class="content-page__hero">
                <div class="container">
                    <nav class="content-breadcrumbs" aria-label="مسار الصفحة">
                        <a href="{{ route('content.index') }}">المكتبة</a><span>←</span>
                        @if ($content->category)
                            <a href="{{ route('content.index', ['category' => $content->category->slug]) }}">{{ $content->category->name }}</a><span>←</span>
                        @endif
                        <span>{{ $typeLabels[$content->type] }}</span>
                    </nav>
                    <div class="content-page__hero-grid">
                        <div class="content-page__hero-copy">
                            <div class="content-page__meta">
                                <span>{{ $typeLabels[$content->type] }}</span>
                                @if ($content->category)<span>{{ $content->category->name }}</span>@endif
                                @if ($content->isSubscriberOnly())<span>بعد تسجيل البريد</span>@else<span>مجاني</span>@endif
                            </div>
                            <h1>{{ $content->title }}</h1>
                            @if ($content->excerpt) <p>{{ $content->excerpt }}</p> @endif
                            <div class="content-page__byline">
                                <strong>{{ config('brand.name') }}</strong>
                                <span>{{ $content->published_at?->translatedFormat('d F Y') }}</span>
                                @if ($content->duration_minutes)<span>{{ $content->duration_minutes }} دقيقة قراءة</span>@endif
                            </div>
                        </div>
                        @include('site.content._visual', ['item' => $content, 'class' => 'content-page__hero-visual', 'eager' => true])
                    </div>
                </div>
            </header>

            <div class="container content-page__body">
                <div class="content-reading-grid">
                    <div class="content-reading-main">
                        @if ($unlocked)
                            <div class="content-prose">
                                @if ($content->video_url)
                                    <div class="content-video"><a href="{{ $content->video_url }}" target="_blank" rel="noopener noreferrer">مشاهدة الفيديو التعليمي <span>↗</span></a></div>
                                @endif

                                {!! $content->body_html !!}

                                @if ($content->type === 'course')
                                    @include('site.content._curriculum')
                                @endif

                                @if ($content->resources->isNotEmpty())
                                    <aside class="content-downloads" aria-labelledby="content-downloads-title">
                                        <div class="content-downloads__head">
                                            <p class="eyebrow">للتطبيق والمتابعة</p>
                                            <h2 id="content-downloads-title">المواد المصاحبة</h2>
                                        </div>
                                        <ul class="content-downloads__list">
                                            @foreach ($content->resources as $resource)
                                                <li>
                                                    @if ($resource->type === 'file' && $resource->media)
                                                        <a href="{{ route('content.resources.download', [$content, $resource]) }}" class="content-downloads__item">
                                                            <span><strong>{{ $resource->title }}</strong><small>{{ $resource->media->original_name }} · {{ number_format($resource->media->size_bytes / 1024, 1) }} KB</small></span>
                                                            <span aria-hidden="true">تنزيل ↓</span>
                                                        </a>
                                                    @elseif ($resource->type === 'link')
                                                        <a href="{{ $resource->url }}" target="_blank" rel="noopener noreferrer" class="content-downloads__item">
                                                            <span><strong>{{ $resource->title }}</strong><small>{{ parse_url($resource->url, PHP_URL_HOST) }}</small></span>
                                                            <span aria-hidden="true">فتح ↗</span>
                                                        </a>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </aside>
                                @endif
                            </div>
                        @else
                            @include('site.content._gate')
                        @endif
                    </div>

                    <aside class="content-reading-sidebar">
                        <section class="content-info-card">
                            <p class="eyebrow">عن هذه المادة</p>
                            <h2>تفاصيل المادة</h2>
                            <dl>
                                <div><dt>النوع</dt><dd>{{ $typeLabels[$content->type] }}</dd></div>
                                @if ($content->category)<div><dt>القسم</dt><dd><a href="{{ route('content.index', ['category' => $content->category->slug]) }}">{{ $content->category->name }}</a></dd></div>@endif
                                @if ($content->duration_minutes)<div><dt>المدة</dt><dd>{{ $content->duration_minutes }} دقيقة</dd></div>@endif
                                <div><dt>الوصول</dt><dd>{{ $content->isSubscriberOnly() ? 'بعد تسجيل البريد' : 'مجاني' }}</dd></div>
                            </dl>
                        </section>

                        @if ($relatedContents->isNotEmpty())
                            <section class="content-related">
                                <p class="eyebrow">واصل التعلّم</p>
                                <h2>قد يعجبك أيضًا</h2>
                                <div class="content-related__list">
                                    @foreach ($relatedContents as $related)
                                        <a href="{{ route('content.show', $related) }}">
                                            <span class="content-related__icon">@include('site.content._icon', ['name' => $related->type])</span>
                                            <span><small>{{ $typeLabels[$related->type] }}</small><strong>{{ $related->title }}</strong></span>
                                        </a>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    </aside>
                </div>
            </div>
        </article>
    </main>

    @include('partials.site-footer')
@endsection
