@extends('layouts.marketing', ['title' => $title, 'description' => $description])

@section('content')
@php
    $topics = [
        ['title' => 'الاستراتيجية والتموضع', 'desc' => 'كيف تصمم رسالة لا تُنسى في سوق مزدحم.'],
        ['title' => 'الحملات المدفوعة', 'desc' => 'تحليل الأداء، تقليل الهدر، ورفع ROI.'],
        ['title' => 'الذكاء الاصطناعي التسويقي', 'desc' => 'توظيف LLMs في القرارات والمخرجات.'],
        ['title' => 'بناء الفرق والمنهجية', 'desc' => 'قيادة فرق تسويق تعمل بمنظومة واضحة.'],
    ];

    // نسلط الضوء على أول مقال كـ Featured، والباقي في شبكة
    $featured = $posts->first();
    $rest = $posts->slice(1);
@endphp

{{-- ═══ Hero ═══ --}}
<section class="section-lg internal-page-hero">
    <div class="site-container">
        <div class="section-header reveal max-w-3xl mx-auto">
            <div class="section-badge section-badge-center mb-4">
                <span class="section-dot"></span>
                <span class="section-badge-text">المدونة</span>
            </div>
            <h1 class="heading-lg mb-4">
                مقالات تحوّل المعرفة <span class="text-gradient">إلى خطوات عملية</span>
            </h1>
            <p class="text-body-lg max-w-2xl mx-auto">
                تحليلات معمّقة، دراسات حالة مصغّرة، ومنهجيات مُختبَرة في التسويق الاستراتيجي والأداء الرقمي — مكتوبة لتُطبَّق، لا لتُخزَّن.
            </p>
        </div>

        @if($featured && $featured->featured_image)
        <article class="blog-featured-card reveal mt-10">
            <a href="{{ route('blog.show', $featured->slug) }}" class="blog-featured-link">
                <div class="blog-featured-image">
                    <img src="{{ asset('storage/'.$featured->featured_image) }}" alt="" loading="lazy">
                </div>
                <div class="blog-featured-body">
                    <p class="text-eyebrow text-p mb-3">المقال المميّز</p>
                    <h2 class="blog-featured-title">{{ $featured->title }}</h2>
                    @if($featured->excerpt)
                        <p class="blog-featured-excerpt">{{ \Illuminate\Support\Str::limit($featured->excerpt, 220) }}</p>
                    @endif
                    <div class="blog-featured-meta">
                        <span>{{ optional($featured->published_at)->translatedFormat('d F Y') }}</span>
                        <span class="blog-featured-cta">اقرأ المقال
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </span>
                    </div>
                </div>
            </a>
        </article>
        @elseif($featured)
            @php $rest = $posts; @endphp
        @endif
    </div>
</section>

{{-- ═══ محاور المحتوى ═══ --}}
<section class="section-band bg-2">
    <div class="site-container">
        <div class="section-header reveal mb-8">
            <p class="text-eyebrow mb-3 text-p">محاور المحتوى</p>
            <h2 class="heading-lg mb-4">أربعة <span class="text-gradient">مسارات معرفية</span> في المدونة</h2>
        </div>
        <div class="four-col">
            @foreach($topics as $i => $topic)
            <article class="blog-topic-card reveal d-{{ ($i % 3) + 1 }}">
                <h3 class="blog-topic-title">{{ $topic['title'] }}</h3>
                <p class="blog-topic-desc">{{ $topic['desc'] }}</p>
            </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══ شبكة المقالات ═══ --}}
<section class="section-lg">
    <div class="site-container">
        <div class="section-header reveal mb-8">
            <p class="text-eyebrow mb-3 text-p">آخر المقالات</p>
            <h2 class="heading-lg">أحدث ما نشرناه في <span class="text-gradient">المدونة</span></h2>
        </div>

        @if($rest->isEmpty() && !$featured)
            <div class="page-summary-card text-center max-w-2xl mx-auto">
                <p class="text-body">لا توجد مقالات منشورة بعد. سنبدأ النشر قريباً.</p>
            </div>
        @else
            <div class="blog-grid">
                @foreach ($rest as $i => $post)
                    <article class="blog-card reveal d-{{ ($i % 3) + 1 }}">
                        @if($post->featured_image)
                            <a href="{{ route('blog.show', $post->slug) }}" class="blog-card-image">
                                <img src="{{ asset('storage/'.$post->featured_image) }}" alt="" loading="lazy">
                            </a>
                        @endif
                        <div class="blog-card-body">
                            <p class="blog-card-date">{{ optional($post->published_at)->translatedFormat('d F Y') }}</p>
                            <h3 class="blog-card-title">
                                <a href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a>
                            </h3>
                            @if($post->excerpt)
                                <p class="blog-card-excerpt">{{ \Illuminate\Support\Str::limit($post->excerpt, 160) }}</p>
                            @endif
                            <a href="{{ route('blog.show', $post->slug) }}" class="blog-card-link">
                                اقرأ المزيد
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            @if($posts->hasPages())
                <div class="admin-pagination mt-10">
                    {{ $posts->links() }}
                </div>
            @endif
        @endif
    </div>
</section>

{{-- ═══ CTA الختامي ═══ --}}
<section class="section-lg section-band bg-2">
    <div class="site-container">
        <div class="cta-section reveal">
            <div class="cta-section-grid"></div>
            <div class="cta-section-glow"></div>
            <div class="cta-section-content">
                <div class="section-badge mb-5 internal-cta-badge">
                    <span class="section-badge-text internal-cta-badge-text">طبّق ما قرأت</span>
                </div>
                <h2 class="cta-headline">المعرفة بلا تطبيق لا تُثمر</h2>
                <p class="cta-body">انتقل من المقال إلى الفعل: جرّب أدوات المنصة على مشروعك الحقيقي وشاهد الفرق.</p>
                <div class="cta-actions">
                    <a href="{{ route('register') }}" class="btn btn-primary btn-xl">ابدأ مجاناً</a>
                    <a href="{{ route('paths.index') }}" class="btn btn-ghost btn-xl">استكشف المسارات</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('head')
<style>
    /* — بطاقة المقال المميّز — */
    .blog-featured-card {
        border-radius: var(--r-xl);
        border: 1px solid var(--border);
        background: var(--surface-2);
        overflow: hidden;
        transition: border-color var(--dur-base) var(--ease), transform var(--dur-base) var(--ease);
    }
    .blog-featured-card:hover {
        border-color: var(--p);
        transform: translateY(-2px);
    }
    .blog-featured-link {
        display: grid;
        grid-template-columns: 1.1fr 1fr;
        gap: 0;
        color: inherit;
        text-decoration: none;
    }
    .blog-featured-image {
        overflow: hidden;
        min-height: 320px;
    }
    .blog-featured-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform var(--dur-slow) var(--ease);
    }
    .blog-featured-card:hover .blog-featured-image img {
        transform: scale(1.04);
    }
    .blog-featured-body {
        padding: var(--sp-8);
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .blog-featured-title {
        font-size: var(--fs-2xl);
        font-weight: 900;
        color: var(--text);
        margin-bottom: var(--sp-4);
        line-height: 1.3;
    }
    .blog-featured-excerpt {
        font-size: var(--fs-base);
        color: var(--text-muted);
        line-height: 1.8;
        margin-bottom: var(--sp-5);
    }
    .blog-featured-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: var(--fs-sm);
        color: var(--text-muted);
    }
    .blog-featured-cta {
        display: inline-flex;
        align-items: center;
        gap: var(--sp-2);
        color: var(--p);
        font-weight: 800;
    }

    /* — محاور المحتوى — */
    .blog-topic-card {
        padding: var(--sp-5);
        border-radius: var(--r-md);
        border: 1px solid var(--border);
        background: var(--surface);
        transition: border-color var(--dur-base) var(--ease), transform var(--dur-base) var(--ease);
    }
    .blog-topic-card:hover {
        border-color: var(--p);
        transform: translateY(-2px);
    }
    .blog-topic-title {
        font-size: var(--fs-md);
        font-weight: 800;
        color: var(--text);
        margin-bottom: var(--sp-2);
    }
    .blog-topic-desc {
        font-size: var(--fs-sm);
        color: var(--text-muted);
        line-height: 1.7;
        margin: 0;
    }

    /* — شبكة المقالات — */
    .blog-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: var(--sp-5);
    }
    .blog-card {
        display: flex;
        flex-direction: column;
        border-radius: var(--r-lg);
        border: 1px solid var(--border);
        background: var(--surface-2);
        overflow: hidden;
        transition: border-color var(--dur-base) var(--ease), transform var(--dur-base) var(--ease);
    }
    .blog-card:hover {
        border-color: var(--p);
        transform: translateY(-3px);
    }
    .blog-card-image {
        display: block;
        aspect-ratio: 16 / 9;
        overflow: hidden;
    }
    .blog-card-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform var(--dur-slow) var(--ease);
    }
    .blog-card:hover .blog-card-image img {
        transform: scale(1.05);
    }
    .blog-card-body {
        padding: var(--sp-5) var(--sp-6);
        display: flex;
        flex-direction: column;
        flex: 1;
    }
    .blog-card-date {
        font-size: var(--fs-xs);
        font-weight: 700;
        color: var(--text-muted);
        margin-bottom: var(--sp-2);
        letter-spacing: 0.02em;
    }
    .blog-card-title {
        font-size: var(--fs-lg);
        font-weight: 800;
        line-height: 1.4;
        margin-bottom: var(--sp-3);
    }
    .blog-card-title a {
        color: var(--text);
        text-decoration: none;
        transition: color var(--dur-base) var(--ease);
    }
    .blog-card-title a:hover { color: var(--p); }
    .blog-card-excerpt {
        font-size: var(--fs-sm);
        color: var(--text-muted);
        line-height: 1.7;
        margin-bottom: var(--sp-4);
        flex: 1;
    }
    .blog-card-link {
        display: inline-flex;
        align-items: center;
        gap: var(--sp-2);
        font-size: var(--fs-sm);
        font-weight: 800;
        color: var(--p);
        text-decoration: none;
        margin-top: auto;
    }
    .blog-card-link:hover { gap: var(--sp-3); }

    @media (max-width: 768px) {
        .blog-featured-link { grid-template-columns: 1fr; }
        .blog-featured-image { min-height: 220px; }
        .blog-featured-body { padding: var(--sp-6); }
        .blog-featured-title { font-size: var(--fs-xl); }
    }
</style>
@endpush
