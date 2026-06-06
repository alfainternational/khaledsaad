@extends('layouts.marketing', ['title' => $title, 'description' => $description])

@section('content')
@php
    $readingTime = $post->reading_time;
    $publishedDate = optional($post->published_at)->translatedFormat('d F Y');
    $publishedDateISO = optional($post->published_at)->toIso8601String();
    $authorName = $post->author_name;
    $authorTitle = $post->author_title;
    $tags = is_array($post->tags) ? $post->tags : [];
    $category = $post->category;
    $canonicalUrl = $post->canonical_url;
    $ogImageUrl = $post->og_image_url;
    $postDescription = $post->meta_description ?? $post->excerpt;
    $structuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $post->title,
        'description' => $postDescription,
        'datePublished' => $publishedDateISO,
        'dateModified' => optional($post->updated_at)->toIso8601String(),
        'author' => [
            '@type' => 'Person',
            'name' => $authorName,
            'url' => 'https://www.linkedin.com/in/khaledaasaad/',
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'منصة خالد سعد للتسويق الاستراتيجي',
            'url' => url('/'),
        ],
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => $canonicalUrl,
        ],
    ];

    if ($ogImageUrl) {
        $structuredData['image'] = $ogImageUrl;
    }
@endphp

{{-- ═══ Breadcrumbs ═══ --}}
<nav class="blog-breadcrumbs" aria-label="مسار التنقل">
    <div class="site-container">
        <ol class="blog-breadcrumbs-list" itemscope itemtype="https://schema.org/BreadcrumbList">
            @foreach($breadcrumbs as $i => $crumb)
            <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                @if($crumb['url'])
                    <a href="{{ $crumb['url'] }}" class="blog-bc-link" itemprop="item">
                        <span itemprop="name">{{ $crumb['label'] }}</span>
                    </a>
                    <span class="blog-bc-sep" aria-hidden="true">/</span>
                @else
                    <span class="blog-bc-current" itemprop="name" aria-current="page">{{ \Illuminate\Support\Str::limit($crumb['label'], 50) }}</span>
                @endif
                <meta itemprop="position" content="{{ $i + 1 }}">
            </li>
            @endforeach
        </ol>
    </div>
</nav>

{{-- ═══ Hero المقال ═══ --}}
<header class="blog-post-hero">
    <div class="site-container max-w-4xl">

        {{-- التصنيف --}}
        @if($category)
        <div class="blog-post-category-wrap mb-4">
            <span class="blog-post-category-pill">{{ $category }}</span>
        </div>
        @endif

        {{-- العنوان --}}
        <h1 class="blog-post-headline">{{ $post->title }}</h1>

        {{-- المقتطف --}}
        @if($post->excerpt)
        <p class="blog-post-excerpt">{{ $post->excerpt }}</p>
        @endif

        {{-- Meta bar: كاتب + تاريخ + وقت القراءة + مشاهدات --}}
        <div class="blog-post-meta-bar">
            <div class="blog-post-author-info">
                <div class="blog-post-author-avatar" aria-hidden="true">خ</div>
                <div>
                    <p class="blog-post-author-name">{{ $authorName }}</p>
                    <p class="blog-post-author-title">{{ $authorTitle }}</p>
                </div>
            </div>
            <div class="blog-post-meta-right">
                <span class="blog-post-meta-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <time datetime="{{ $publishedDateISO }}">{{ $publishedDate }}</time>
                </span>
                <span class="blog-post-meta-sep" aria-hidden="true">·</span>
                <span class="blog-post-meta-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    {{ $readingTime }} دقيقة قراءة
                </span>
                @if($post->view_count > 0)
                <span class="blog-post-meta-sep" aria-hidden="true">·</span>
                <span class="blog-post-meta-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    {{ number_format($post->view_count) }} مشاهدة
                </span>
                @endif
            </div>
        </div>
    </div>
</header>

{{-- ═══ الصورة البارزة ═══ --}}
@if($post->featured_image)
<div class="blog-post-cover">
    <div class="site-container max-w-4xl">
        <figure class="blog-post-cover-figure">
            <img
                src="{{ asset('storage/' . $post->featured_image) }}"
                alt="{{ $post->featured_image_alt ?: $post->title }}"
                class="blog-post-cover-img"
                loading="eager"
                fetchpriority="high"
            >
        </figure>
    </div>
</div>
@endif

{{-- ═══ جسم المقال + Sidebar ═══ --}}
<div class="blog-post-layout site-container max-w-6xl">

    {{-- المحتوى الرئيسي --}}
    <main class="blog-post-main" id="post-content">
        <article class="blog-post-body marketing-cms-body" itemscope itemtype="https://schema.org/Article">
            <meta itemprop="headline" content="{{ $post->title }}">
            <meta itemprop="datePublished" content="{{ $publishedDateISO }}">
            <meta itemprop="author" content="{{ $authorName }}">
            @if($ogImageUrl)<meta itemprop="image" content="{{ $ogImageUrl }}">@endif
            {!! $post->body_html !!}
        </article>

        {{-- الوسوم --}}
        @if(!empty($tags))
        <div class="blog-post-tags">
            <span class="blog-post-tags-label">الوسوم:</span>
            @foreach($tags as $tag)
            <span class="blog-post-tag">{{ $tag }}</span>
            @endforeach
        </div>
        @endif

        {{-- فاصل + مشاركة --}}
        <div class="blog-post-share-bar">
            <span class="blog-share-label">شارك المقال:</span>
            <div class="blog-share-buttons">
                <a href="https://twitter.com/intent/tweet?text={{ urlencode($post->title) }}&url={{ urlencode($canonicalUrl) }}"
                   class="blog-share-btn blog-share-x" target="_blank" rel="noopener noreferrer" aria-label="شارك على X">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.748l7.73-8.835L1.254 2.25H8.08l4.254 5.622 5.91-5.622Zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    إكس
                </a>
                <a href="https://www.linkedin.com/shareArticle?mini=true&url={{ urlencode($canonicalUrl) }}&title={{ urlencode($post->title) }}"
                   class="blog-share-btn blog-share-linkedin" target="_blank" rel="noopener noreferrer" aria-label="شارك على لينكد إن">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6zM2 9h4v12H2z M4 6a2 2 0 100-4 2 2 0 000 4z"/></svg>
                    لينكد إن
                </a>
                <a href="https://wa.me/?text={{ urlencode($post->title . ' ' . $canonicalUrl) }}"
                   class="blog-share-btn blog-share-wa" target="_blank" rel="noopener noreferrer" aria-label="شارك عبر واتساب">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                    واتساب
                </a>
                <button class="blog-share-btn blog-share-copy" onclick="blogCopyLink('{{ $canonicalUrl }}')" aria-label="نسخ الرابط">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                    نسخ الرابط
                </button>
            </div>
        </div>

        {{-- بطاقة الكاتب --}}
        <div class="blog-author-card">
            <div class="blog-author-avatar-lg" aria-hidden="true">خ</div>
            <div class="blog-author-info">
                <p class="blog-author-card-name">{{ $authorName }}</p>
                <p class="blog-author-card-title">{{ $authorTitle }}</p>
                <p class="blog-author-card-bio">
                    مدير تسويق بخبرة تتجاوز {{ (int)(now()->year - 2011) }} عاماً في التسويق الرقمي والاستراتيجي. مؤسس منصة خالد سعد للنمو التي تقود المشاريع من الفكرة إلى التنفيذ بمنهجية مهندسة.
                </p>
                <div class="blog-author-card-links">
                    <a href="https://www.linkedin.com/in/khaledaasaad/" target="_blank" rel="noopener noreferrer" class="blog-author-social">لينكد إن</a>
                    <a href="https://x.com/KhaledAASaad" target="_blank" rel="noopener noreferrer" class="blog-author-social">إكس</a>
                </div>
            </div>
        </div>

        {{-- التنقل بين المقالات --}}
        <nav class="blog-post-nav" aria-label="التنقل بين المقالات">
            <a href="{{ route('blog.index') }}" class="blog-post-nav-btn">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5M12 5l-7 7 7 7"/></svg>
                العودة إلى المدونة
            </a>
        </nav>
    </main>

    {{-- Sidebar --}}
    <aside class="blog-post-sidebar" aria-label="الشريط الجانبي">

        {{-- جدول المحتويات — يُبنى بـ JS --}}
        <div class="blog-toc-card" id="blog-toc" aria-label="جدول المحتويات">
            <p class="blog-toc-title">جدول المحتويات</p>
            <nav id="blog-toc-nav" class="blog-toc-nav"></nav>
        </div>

        {{-- بطاقة CTA --}}
        <div class="blog-sidebar-cta">
            <div class="blog-sidebar-cta-glow" aria-hidden="true"></div>
            <p class="blog-sidebar-cta-eyebrow">طبّق ما قرأت</p>
            <h3 class="blog-sidebar-cta-title">ابدأ على منصة خالد سعد</h3>
            <p class="blog-sidebar-cta-body">المنصة تحوّل ما تعلمته في المقال إلى خطوات تنفيذية مباشرة على مشروعك.</p>
            <a href="{{ route('register') }}" class="btn btn-primary btn-md w-full text-center">ابدأ مجاناً</a>
        </div>

        {{-- نشرة لينكد إن --}}
        <div class="blog-sidebar-newsletter">
            <p class="blog-sidebar-newsletter-label">نشرة «يلا نفهم تسويق»</p>
            <p class="blog-sidebar-newsletter-body">تحليلات وأدوات أسبوعية مباشرة على لينكد إن.</p>
            <a href="https://www.linkedin.com/in/khaledaasaad/" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-md w-full text-center">تابع على لينكد إن</a>
        </div>
    </aside>
</div>

{{-- ═══ مقالات ذات صلة ═══ --}}
@if($related->isNotEmpty())
<section class="section-lg section-band bg-2">
    <div class="site-container">
        <div class="section-header reveal mb-8">
            <p class="text-eyebrow mb-2 text-p">استمر في القراءة</p>
            <h2 class="heading-lg">مقالات <span class="text-gradient">ذات صلة</span></h2>
        </div>
        <div class="blog-grid">
            @foreach($related as $i => $rel)
            <article class="blog-card reveal d-{{ $i + 1 }}">
                @if($rel->featured_image)
                <a href="{{ route('blog.show', $rel->slug) }}" class="blog-card-image">
                    <img src="{{ asset('storage/' . $rel->featured_image) }}" alt="{{ $rel->featured_image_alt ?: $rel->title }}" loading="lazy">
                </a>
                @endif
                <div class="blog-card-body">
                    <p class="blog-card-date">{{ optional($rel->published_at)->translatedFormat('d F Y') }}</p>
                    <h3 class="blog-card-title">
                        <a href="{{ route('blog.show', $rel->slug) }}">{{ $rel->title }}</a>
                    </h3>
                    @if($rel->excerpt)
                    <p class="blog-card-excerpt">{{ \Illuminate\Support\Str::limit($rel->excerpt, 140) }}</p>
                    @endif
                    <a href="{{ route('blog.show', $rel->slug) }}" class="blog-card-link">
                        اقرأ المزيد
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </a>
                </div>
            </article>
            @endforeach
        </div>
    </div>
</section>
@endif
@endsection

{{-- ═══ SEO & Structured Data ═══ --}}
@push('head')
<link rel="canonical" href="{{ $canonicalUrl }}">
<meta property="og:type" content="article">
<meta property="og:title" content="{{ $post->title }}">
<meta property="og:description" content="{{ $postDescription }}">
<meta property="og:url" content="{{ $canonicalUrl }}">
@if($ogImageUrl)
<meta property="og:image" content="{{ $ogImageUrl }}">
@endif
<meta property="article:published_time" content="{{ $publishedDateISO }}">
<meta property="article:author" content="{{ $authorName }}">
@if($category)
<meta property="article:section" content="{{ $category }}">
@endif
@foreach($tags as $tag)
<meta property="article:tag" content="{{ $tag }}">
@endforeach
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $post->title }}">
<meta name="twitter:description" content="{{ $postDescription }}">
@if($ogImageUrl)
<meta name="twitter:image" content="{{ $ogImageUrl }}">
@endif
<meta name="twitter:creator" content="{{ '@KhaledAASaad' }}">

<script type="application/ld+json">
{!! json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>

<style>
    /* — Breadcrumbs — */
    .blog-breadcrumbs {
        padding: var(--sp-4) 0;
        border-bottom: 1px solid var(--border);
    }
    .blog-breadcrumbs-list {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: var(--sp-2);
        list-style: none;
        padding: 0; margin: 0;
        font-size: var(--fs-sm);
    }
    .blog-bc-link {
        color: var(--text-muted);
        text-decoration: none;
        font-weight: 600;
        transition: color var(--dur-base) var(--ease);
    }
    .blog-bc-link:hover { color: var(--p); }
    .blog-bc-sep { color: var(--border-2); font-weight: 400; }
    .blog-bc-current { color: var(--text); font-weight: 700; }

    /* — Hero المقال — */
    .blog-post-hero {
        padding: var(--sp-12) 0 var(--sp-8);
    }
    .blog-post-category-pill {
        display: inline-block;
        padding: 4px 14px;
        border-radius: var(--r-full);
        background: color-mix(in srgb, var(--p) 12%, transparent);
        color: var(--p);
        font-size: var(--fs-xs);
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }
    .blog-post-headline {
        font-size: clamp(1.75rem, 4vw, 3rem);
        font-weight: 900;
        line-height: 1.25;
        color: var(--text);
        margin-bottom: var(--sp-5);
    }
    .blog-post-excerpt {
        font-size: var(--fs-lg);
        color: var(--text-muted);
        line-height: 1.8;
        margin-bottom: var(--sp-6);
        max-width: 68ch;
    }
    .blog-post-meta-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: var(--sp-4);
        padding: var(--sp-4) 0;
        border-top: 1px solid var(--border);
        border-bottom: 1px solid var(--border);
    }
    .blog-post-author-info {
        display: flex;
        align-items: center;
        gap: var(--sp-3);
    }
    .blog-post-author-avatar {
        width: 44px; height: 44px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--p), var(--teal));
        color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 1rem;
        flex-shrink: 0;
    }
    .blog-post-author-name {
        font-weight: 800;
        font-size: var(--fs-sm);
        color: var(--text);
        margin: 0 0 2px;
    }
    .blog-post-author-title {
        font-size: var(--fs-xs);
        color: var(--text-muted);
        margin: 0;
    }
    .blog-post-meta-right {
        display: flex;
        align-items: center;
        gap: var(--sp-2);
        flex-wrap: wrap;
    }
    .blog-post-meta-item {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: var(--fs-xs);
        color: var(--text-muted);
        font-weight: 600;
    }
    .blog-post-meta-sep { color: var(--border-2); }

    /* — الصورة البارزة — */
    .blog-post-cover { padding: var(--sp-2) 0 var(--sp-8); }
    .blog-post-cover-figure { margin: 0; border-radius: var(--r-xl); overflow: hidden; border: 1px solid var(--border); }
    .blog-post-cover-img { width: 100%; max-height: 520px; object-fit: cover; display: block; }

    /* — تخطيط المقال — */
    .blog-post-layout {
        display: grid;
        grid-template-columns: 1fr 320px;
        gap: var(--sp-10);
        align-items: start;
        padding-top: var(--sp-8);
        padding-bottom: var(--sp-12);
    }
    .blog-post-main { min-width: 0; }

    /* — جسم المقال — */
    .blog-post-body { margin-bottom: var(--sp-8); }

    /* — الوسوم — */
    .blog-post-tags {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: var(--sp-2);
        padding: var(--sp-5) 0;
        border-top: 1px dashed var(--border);
        margin-bottom: var(--sp-6);
    }
    .blog-post-tags-label {
        font-size: var(--fs-xs);
        font-weight: 800;
        color: var(--text-muted);
        letter-spacing: 0.04em;
        flex-shrink: 0;
    }
    .blog-post-tag {
        padding: 4px 12px;
        border-radius: var(--r-full);
        border: 1px solid var(--border);
        background: var(--surface-3);
        font-size: var(--fs-xs);
        font-weight: 600;
        color: var(--text);
    }

    /* — شريط المشاركة — */
    .blog-post-share-bar {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: var(--sp-3);
        padding: var(--sp-5) var(--sp-6);
        border-radius: var(--r-lg);
        border: 1px solid var(--border);
        background: var(--surface-2);
        margin-bottom: var(--sp-8);
    }
    .blog-share-label {
        font-size: var(--fs-sm);
        font-weight: 800;
        color: var(--text);
        flex-shrink: 0;
    }
    .blog-share-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: var(--sp-2);
    }
    .blog-share-btn {
        display: inline-flex;
        align-items: center;
        gap: var(--sp-2);
        padding: 7px 14px;
        border-radius: var(--r-md);
        font-size: var(--fs-xs);
        font-weight: 700;
        text-decoration: none;
        border: 1px solid var(--border);
        background: var(--surface-3);
        color: var(--text);
        cursor: pointer;
        transition: all var(--dur-base) var(--ease);
    }
    .blog-share-btn:hover { border-color: var(--border-2); transform: translateY(-1px); }
    .blog-share-x:hover { border-color: #000; color: #000; }
    .blog-share-linkedin:hover { border-color: #0077b5; color: #0077b5; }
    .blog-share-wa:hover { border-color: #25d366; color: #25d366; }

    /* — بطاقة الكاتب — */
    .blog-author-card {
        display: flex;
        gap: var(--sp-5);
        padding: var(--sp-7);
        border-radius: var(--r-xl);
        border: 1px solid var(--border);
        background: var(--surface-2);
        margin-bottom: var(--sp-8);
    }
    .blog-author-avatar-lg {
        width: 72px; height: 72px; flex-shrink: 0;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--p), var(--teal));
        color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-weight: 900; font-size: 1.5rem;
        box-shadow: var(--shadow-md);
    }
    .blog-author-info { flex: 1; min-width: 0; }
    .blog-author-card-name { font-size: var(--fs-lg); font-weight: 900; color: var(--text); margin: 0 0 var(--sp-1); }
    .blog-author-card-title { font-size: var(--fs-sm); color: var(--p); font-weight: 700; margin: 0 0 var(--sp-3); }
    .blog-author-card-bio { font-size: var(--fs-sm); color: var(--text-muted); line-height: 1.8; margin: 0 0 var(--sp-4); }
    .blog-author-card-links { display: flex; gap: var(--sp-3); }
    .blog-author-social {
        font-size: var(--fs-xs); font-weight: 700;
        color: var(--p); text-decoration: none;
        padding: 5px 12px;
        border-radius: var(--r-full);
        border: 1px solid var(--p);
        transition: all var(--dur-base) var(--ease);
    }
    .blog-author-social:hover { background: var(--p); color: #fff; }

    /* — التنقل — */
    .blog-post-nav { display: flex; gap: var(--sp-3); margin-bottom: var(--sp-8); }
    .blog-post-nav-btn {
        display: inline-flex; align-items: center; gap: var(--sp-2);
        padding: var(--sp-3) var(--sp-5);
        border-radius: var(--r-md);
        border: 1px solid var(--border);
        background: var(--surface-2);
        color: var(--text);
        font-size: var(--fs-sm); font-weight: 700;
        text-decoration: none;
        transition: border-color var(--dur-base) var(--ease), transform var(--dur-base) var(--ease);
    }
    .blog-post-nav-btn:hover { border-color: var(--p); transform: translateY(-1px); }

    /* — Sidebar — */
    .blog-post-sidebar {
        position: sticky;
        top: var(--sp-8);
        display: flex;
        flex-direction: column;
        gap: var(--sp-5);
    }

    /* — جدول المحتويات — */
    .blog-toc-card {
        padding: var(--sp-5) var(--sp-6);
        border-radius: var(--r-lg);
        border: 1px solid var(--border);
        background: var(--surface-2);
    }
    .blog-toc-title {
        font-size: var(--fs-sm); font-weight: 800;
        color: var(--text); margin-bottom: var(--sp-4);
        padding-bottom: var(--sp-3);
        border-bottom: 1px solid var(--border);
    }
    .blog-toc-nav { display: flex; flex-direction: column; gap: var(--sp-1); }
    .blog-toc-nav a {
        font-size: var(--fs-xs); font-weight: 600;
        color: var(--text-muted); text-decoration: none;
        padding: var(--sp-1) var(--sp-2);
        border-radius: var(--r-sm);
        border-inline-start: 2px solid transparent;
        transition: all var(--dur-base) var(--ease);
        line-height: 1.5;
    }
    .blog-toc-nav a:hover,
    .blog-toc-nav a.active {
        color: var(--p);
        border-inline-start-color: var(--p);
        background: color-mix(in srgb, var(--p) 6%, transparent);
    }
    .blog-toc-nav .toc-h3 { padding-inline-start: var(--sp-4); }

    /* — CTA Sidebar — */
    .blog-sidebar-cta {
        position: relative;
        padding: var(--sp-6);
        border-radius: var(--r-lg);
        border: 1px solid var(--border);
        background: linear-gradient(135deg, color-mix(in srgb, var(--p) 8%, var(--surface-2)), var(--surface-2));
        overflow: hidden;
    }
    .blog-sidebar-cta-glow {
        position: absolute; inset: 0;
        background: radial-gradient(ellipse at 50% 0%, color-mix(in srgb, var(--p) 15%, transparent), transparent 70%);
        pointer-events: none;
    }
    .blog-sidebar-cta-eyebrow { font-size: var(--fs-xs); font-weight: 800; color: var(--p); letter-spacing: 0.04em; margin-bottom: var(--sp-2); position: relative; }
    .blog-sidebar-cta-title { font-size: var(--fs-lg); font-weight: 900; color: var(--text); margin-bottom: var(--sp-3); position: relative; }
    .blog-sidebar-cta-body { font-size: var(--fs-sm); color: var(--text-muted); line-height: 1.7; margin-bottom: var(--sp-5); position: relative; }

    /* — Newsletter Sidebar — */
    .blog-sidebar-newsletter {
        padding: var(--sp-5) var(--sp-6);
        border-radius: var(--r-lg);
        border: 1px solid var(--border);
        background: var(--surface-2);
    }
    .blog-sidebar-newsletter-label { font-size: var(--fs-sm); font-weight: 800; color: var(--text); margin-bottom: var(--sp-2); }
    .blog-sidebar-newsletter-body { font-size: var(--fs-xs); color: var(--text-muted); line-height: 1.7; margin-bottom: var(--sp-4); }

    .w-full { width: 100%; }

    /* — تجاوب — */
    @media (max-width: 1024px) {
        .blog-post-layout { grid-template-columns: 1fr; }
        .blog-post-sidebar { position: static; }
        .blog-toc-card { display: none; }
    }
    @media (max-width: 640px) {
        .blog-post-hero { padding: var(--sp-8) 0 var(--sp-6); }
        .blog-post-meta-bar { flex-direction: column; align-items: flex-start; }
        .blog-post-share-bar { flex-direction: column; align-items: flex-start; }
        .blog-author-card { flex-direction: column; }
    }
</style>

<script>
// جدول المحتويات التلقائي
document.addEventListener('DOMContentLoaded', function () {
    const content = document.getElementById('post-content');
    const tocNav  = document.getElementById('blog-toc-nav');
    const tocCard = document.getElementById('blog-toc');
    if (!content || !tocNav) return;

    const headings = content.querySelectorAll('h2, h3');
    if (headings.length < 2) { if (tocCard) tocCard.style.display = 'none'; return; }

    headings.forEach(function (h, i) {
        if (!h.id) h.id = 'heading-' + i;
        const a = document.createElement('a');
        a.href = '#' + h.id;
        a.textContent = h.textContent;
        if (h.tagName === 'H3') a.classList.add('toc-h3');
        a.addEventListener('click', function (e) {
            e.preventDefault();
            document.getElementById(h.id).scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
        tocNav.appendChild(a);
    });

    // Active state on scroll
    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            const id = entry.target.id;
            const link = tocNav.querySelector('a[href="#' + id + '"]');
            if (!link) return;
            if (entry.isIntersecting) {
                tocNav.querySelectorAll('a').forEach(function (a) { a.classList.remove('active'); });
                link.classList.add('active');
            }
        });
    }, { rootMargin: '-10% 0px -80% 0px' });

    headings.forEach(function (h) { observer.observe(h); });
});

// نسخ الرابط
function blogCopyLink(url) {
    navigator.clipboard.writeText(url).then(function () {
        const btn = document.querySelector('.blog-share-copy');
        if (btn) {
            const orig = btn.innerHTML;
            btn.innerHTML = '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> تم النسخ';
            btn.style.borderColor = 'var(--teal)';
            btn.style.color = 'var(--teal)';
            setTimeout(function () { btn.innerHTML = orig; btn.style.borderColor = ''; btn.style.color = ''; }, 2000);
        }
    });
}
</script>
@endpush
