@extends('layouts.public')
@section('layout', 'marketing')
@section('title', $content->seo_title ?: ($content->title.' | خالد سعد'))
@section('description', $content->seo_description ?: $content->excerpt)
@section('og_type', 'article')
@section('twitter_card', 'summary_large_image')
@section('og_image_width', '1200')
@section('og_image_height', '630')
@php
    $coverUrl = $content->cover_image_path
        ? (str_starts_with($content->cover_image_path, 'http') || str_starts_with($content->cover_image_path, '/')
            ? $content->cover_image_path
            : \Illuminate\Support\Facades\Storage::disk('public')->url($content->cover_image_path))
        : null;
    $ogImagePath = $content->learning_meta['cover']['og'] ?? $content->cover_image_path;
    $ogImageUrl = $ogImagePath
        ? (str_starts_with($ogImagePath, 'http') ? $ogImagePath : url($ogImagePath))
        : null;
@endphp
@if ($ogImageUrl)
    @section('og_image', $ogImageUrl)
@endif
@push('head')
    @if ($content->published_at)<meta property="article:published_time" content="{{ $content->published_at->toAtomString() }}">@endif
    @if ($content->updated_at)<meta property="article:modified_time" content="{{ $content->updated_at->toAtomString() }}">@endif
    @if ($content->category)<meta property="article:section" content="{{ $content->category->name }}">@endif
@endpush

@php($typeLabels = ['article' => 'مقال', 'lesson' => 'درس', 'lecture' => 'محاضرة', 'course' => 'دورة'])

@section('content')
    @include('partials.site-header')

    <main id="main-content">
        @if ($learning['enabled'])
            <div class="learning-progress" data-reading-progress aria-hidden="true"><span></span></div>
        @endif
        <article @class([
            'content-page',
            'content-page--learning' => $learning['enabled'],
            'content-page--course-gallery' => $learningGallery['enabled'] ?? false,
        ])
            @if ($learning['enabled']) data-learning-article data-progress-key="{{ $learning['progress_key'] }}" @endif>
            <header class="content-page__hero">
                @if ($coverUrl)
                    <img class="content-page__hero-backdrop" src="{{ $coverUrl }}" alt="" aria-hidden="true" loading="eager">
                @endif
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
                                @if ($learning['enabled'])<span>الدرس {{ $learning['order'] }} من {{ $learning['total'] }}</span>@endif
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
                            @include('site.content._learning-outline', ['variant' => 'mobile'])
                            @if ($learning['enabled'])
                                <div class="learning-tools" aria-label="أدوات القراءة">
                                    <button type="button" data-learning-save><span aria-hidden="true">✓</span> حفظ التقدم</button>
                                    <button type="button" data-learning-print><span aria-hidden="true">▣</span> طباعة</button>
                                    <button type="button" data-learning-copy><span aria-hidden="true">⌁</span> نسخ رابط الصفحة</button>
                                    <span data-learning-feedback role="status" aria-live="polite"></span>
                                </div>
                            @endif
                            @include('site.content._marketing-course-gallery')
                            <div class="content-prose">
                                @if ($content->video_url)
                                    <div class="content-video"><a href="{{ $content->video_url }}" target="_blank" rel="noopener noreferrer">مشاهدة الفيديو التعليمي <span>↗</span></a></div>
                                @endif

                                @inject('localeRegistry', 'App\Modules\Shared\I18n\LocaleRegistry')

                                {{--
                                    الفجوة تُعلن ولا تُخفى: درس بلا ترجمة يبقى
                                    بالعربية، ويقول ذلك بدل أن يبدو عطلًا في
                                    مبدّل اللغة.
                                --}}
                                @if ($content->displayLocale() !== app()->getLocale())
                                    <p class="content-locale-note" role="note">
                                        هذا الدرس متاح بلغته الأصلية فقط حتى الآن.
                                    </p>
                                @elseif ($content->hasStaleTranslation())
                                    <p class="content-locale-note" role="note">
                                        تُرجم هذا الدرس قبل آخر تحديث على نصّه الأصلي.
                                    </p>
                                @endif

                                {{--
                                    لغة الكتلة واتجاهها يتبعان لغة المحتوى لا لغة
                                    الصفحة: نصّ عربي داخل صفحة إنجليزية يجب أن
                                    يُعرض RTL ويقرأه قارئ الشاشة بلفظ عربي.
                                --}}
                                <div lang="{{ $localeRegistry->htmlLang($content->displayLocale()) }}"
                                     dir="{{ $localeRegistry->direction($content->displayLocale()) }}">
                                    @if ($learningGallery['enabled'] ?? false)
                                        <details class="marketing-workshop-reference" data-workshop-reference>
                                            <summary>
                                                <span>
                                                    <small>المحتوى المرجعي الكامل</small>
                                                    <strong>الورشة التطبيقية الكاملة</strong>
                                                </span>
                                                <span aria-hidden="true">＋</span>
                                            </summary>
                                            <div class="marketing-workshop-reference__body">
                                                {!! $content->body_html !!}
                                            </div>
                                        </details>
                                    @else
                                        {!! $content->body_html !!}
                                    @endif
                                </div>

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
                                                            <span><strong>{{ $resource->title }}</strong><small>{{ $resource->media->original_name }} · {{ $resource->media->humanReadableSize() }}</small></span>
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
                            @unless ($learningGallery['enabled'] ?? false)
                                @include('site.content._learning-applications')
                            @endunless
                            @include('site.content._learning-navigation')
                        @else
                            @include('site.content._gate')
                        @endif
                    </div>

                    @unless ($learningGallery['enabled'] ?? false)
                    <aside class="content-reading-sidebar">
                        @include('site.content._learning-outline')
                        <section class="content-info-card">
                            <p class="eyebrow">عن هذه المادة</p>
                            <h2>تفاصيل المادة</h2>
                            <dl>
                                <div><dt>النوع</dt><dd>{{ $typeLabels[$content->type] }}</dd></div>
                                @if ($content->category)<div><dt>القسم</dt><dd><a href="{{ route('content.index', ['category' => $content->category->slug]) }}">{{ $content->category->name }}</a></dd></div>@endif
                                @if ($content->duration_minutes)<div><dt>المدة</dt><dd>{{ $content->duration_minutes }} دقيقة</dd></div>@endif
                                @if ($learning['enabled'])<div><dt>التقدّم</dt><dd><span data-learning-percent>0%</span></dd></div>@endif
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
                    @endunless
                </div>
            </div>
        </article>
    </main>

    @include('partials.site-footer')
@endsection
