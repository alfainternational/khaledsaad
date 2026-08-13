@extends('layouts.public')
@section('layout', 'marketing')
@section('title', 'المقالات والدروس والمحاضرات والدورات | خالد سعد')
@section('description', 'مكتبة عربية عملية تجمع المقالات والدروس والمحاضرات والدورات.')

@php
    $typeLabels = ['article' => 'المقالات', 'lesson' => 'الدروس', 'lecture' => 'المحاضرات', 'course' => 'الدورات'];
    $queryBase = array_filter(['q' => $search ?: null, 'category' => $category?->slug]);
    $featured = $contents->first();
@endphp

@section('content')
    @include('partials.site-header')

    <main id="main-content" class="content-library-page">
        <section class="content-library-hero">
            <div class="container content-library-hero__inner">
                <div>
                    <p class="eyebrow">اقرأ · تعلّم · طبّق</p>
                    <h1>محتوى يساعدك على الفهم والتطبيق</h1>
                    <p>محتوى عملي يساعدك على فهم المشكلة واتخاذ خطوة أوضح في التسويق والنمو.</p>
                </div>
                <form method="GET" action="{{ route('content.index') }}" class="content-search" role="search">
                    @if ($type) <input type="hidden" name="type" value="{{ $type }}"> @endif
                    @if ($category) <input type="hidden" name="category" value="{{ $category->slug }}"> @endif
                    <span class="content-search__icon">@include('site.content._icon', ['name' => 'search'])</span>
                    <input type="search" name="q" value="{{ $search }}" placeholder="ابحث في المكتبة..." aria-label="ابحث في المكتبة">
                    <button class="button button--primary">بحث</button>
                </form>
            </div>
        </section>

        <section class="content-discovery section">
            <div class="container">
                {{-- المكتبة عربية بالكامل اليوم؛ القارئ بلغة أخرى يعرف ذلك قبل أن يفتح مادة. --}}
                <x-content-language :locale="$appLocales->source()" kind="library" />
                <nav class="content-type-tabs" data-content-type-tabs aria-label="أنواع المحتوى">
                    <a href="{{ route('content.index', $queryBase) }}" @class(['is-active' => $type === null])>
                        <span class="content-type-tabs__icon">@include('site.content._icon', ['name' => 'folder'])</span>
                        <span>الكل</span><small>{{ $totalCount }}</small>
                    </a>
                    @foreach ($typeLabels as $value => $label)
                        <a href="{{ route('content.index', $queryBase + ['type' => $value]) }}" @class(['is-active' => $type === $value])>
                            <span class="content-type-tabs__icon">@include('site.content._icon', ['name' => $value])</span>
                            <span>{{ $label }}</span><small>{{ $typeCounts->get($value, 0) }}</small>
                        </a>
                    @endforeach
                </nav>

                <div class="content-discovery__layout">
                    <div class="content-discovery__main">
                        @if ($featured)
                            <article class="content-library-featured">
                                <a class="content-library-featured__visual" href="{{ route('content.show', $featured) }}">
                                    @include('site.content._visual', ['item' => $featured, 'eager' => true, 'variant' => 'card'])
                                </a>
                                <div class="content-library-featured__body">
                                    <div class="content-card__meta">
                                        @if ($featured->learning_order)<span class="content-card__lesson-order">الدرس {{ $featured->learning_order }}</span>@endif
                                        <span>{{ $typeLabels[$featured->type] }}</span>
                                        @if ($featured->category)<span style="--category-color: {{ $featured->category->color }}">{{ $featured->category->name }}</span>@endif
                                        @if ($featured->duration_minutes)<span>{{ $featured->duration_minutes }} دقيقة</span>@endif
                                    </div>
                                    <p class="eyebrow">اختيار مميز</p>
                                    <h2><a href="{{ route('content.show', $featured) }}">{{ $featured->title }}</a></h2>
                                    <p>{{ $featured->excerpt }}</p>
                                    <a class="content-read-link" href="{{ route('content.show', $featured) }}">ابدأ القراءة <span>←</span></a>
                                </div>
                            </article>
                        @endif

                        <div class="content-results-head">
                            <div>
                                <p class="eyebrow">أحدث المواد</p>
                                <h2>{{ $category?->name ?: ($type ? $typeLabels[$type] : 'كل المحتوى') }}</h2>
                            </div>
                            <span>{{ $contents->total() }} مادة</span>
                        </div>

                        <div class="content-library-grid">
                            @forelse ($contents->skip(1) as $item)
                                <article class="content-library-card">
                                    <a href="{{ route('content.show', $item) }}">@include('site.content._visual', ['item' => $item, 'variant' => 'card'])</a>
                                    <div class="content-library-card__body">
                                        <div class="content-card__meta">
                                            @if ($item->learning_order)<span class="content-card__lesson-order">الدرس {{ $item->learning_order }}</span>@endif
                                            <span>{{ $typeLabels[$item->type] }}</span>
                                            @if ($item->category)<span style="--category-color: {{ $item->category->color }}">{{ $item->category->name }}</span>@endif
                                            @if ($item->duration_minutes)<span>{{ $item->duration_minutes }} دقيقة</span>@endif
                                        </div>
                                        <h2><a href="{{ route('content.show', $item) }}">{{ $item->title }}</a></h2>
                                        <p>{{ $item->excerpt }}</p>
                                        <div class="content-card__footer">
                                            <time datetime="{{ $item->published_at?->toDateString() }}">{{ $item->published_at?->translatedFormat('d M Y') }}</time>
                                            <a href="{{ route('content.show', $item) }}" aria-label="{{ __('اقرأ :title', ['title' => $item->title]) }}">اقرأ <span>←</span></a>
                                        </div>
                                    </div>
                                </article>
                            @empty
                                @if (! $featured)
                                    <div class="empty-state content-library-empty">
                                        <h2>لا توجد نتائج مطابقة</h2>
                                        <p>جرّب كلمة بحث أخرى أو استعرض كل مواد المكتبة.</p>
                                        <a class="button button--primary" href="{{ route('content.index') }}">عرض كل المحتوى</a>
                                        <a class="text-link" href="{{ route('tools.index') }}">ابدأ التشخيص المجاني <span>←</span></a>
                                    </div>
                                @endif
                            @endforelse
                        </div>

                        {{ $contents->links() }}
                    </div>

                    <aside class="content-category-nav" data-content-category-nav aria-labelledby="content-categories-title">
                        <div class="content-category-nav__head">
                            <span class="content-category-nav__head-icon">@include('site.content._icon', ['name' => 'folder'])</span>
                            <div><p class="eyebrow">استكشف حسب الموضوع</p><h2 id="content-categories-title">أقسام المكتبة</h2></div>
                        </div>
                        <nav>
                            <a href="{{ route('content.index', array_filter(['q' => $search ?: null, 'type' => $type])) }}" @class(['is-active' => $category === null])>
                                <span>كل الأقسام</span><small>{{ $totalCount }}</small>
                            </a>
                            @foreach ($categories as $itemCategory)
                                <a href="{{ route('content.index', array_filter(['q' => $search ?: null, 'type' => $type, 'category' => $itemCategory->slug])) }}" @class(['is-active' => $category?->is($itemCategory)])>
                                    <span><i style="--category-color: {{ $itemCategory->color }}">@include('site.content._icon', ['name' => $itemCategory->icon])</i>{{ $itemCategory->name }}</span>
                                    <small>{{ $itemCategory->published_contents_count }}</small>
                                </a>
                            @endforeach
                        </nav>
                    </aside>
                </div>
            </div>
        </section>
    </main>

    @include('partials.site-footer')
@endsection
