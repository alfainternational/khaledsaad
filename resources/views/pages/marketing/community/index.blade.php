@extends('layouts.marketing', ['title' => $title, 'description' => $description])

@section('content')
@php
    $communityPrinciples = [
        [
            'tone' => 'primary',
            'title' => 'نقاش مهني فقط',
            'body' => 'مواضيع تركز على التسويق الاستراتيجي والأداء الرقمي. لا إعلانات، ولا محتوى خارج المجال.',
        ],
        [
            'tone' => 'teal',
            'title' => 'تجارب حقيقية',
            'body' => 'أعضاء يشاركون مواقف فعلية واجهوها في مشاريعهم، مع قرارات اتخذوها ونتائج حصلوا عليها.',
        ],
        [
            'tone' => 'gold',
            'title' => 'جودة قبل الكمية',
            'body' => 'نفضّل موضوعاً واحداً عميقاً على عشرة سطحية. كل مشاركة تخضع لمراجعة قبل النشر.',
        ],
    ];

    $pinnedPost = $posts->first();
    $otherPosts = $posts->count() > 1 ? $posts->slice(1) : collect();
@endphp

{{-- ═══ Hero ═══ --}}
<section class="section-lg internal-page-hero">
    <div class="site-container">
        <div class="section-header reveal max-w-3xl mx-auto">
            <div class="section-badge section-badge-center mb-4">
                <span class="section-dot"></span>
                <span class="section-badge-text">المجتمع</span>
            </div>
            <h1 class="heading-lg mb-4">
                مجتمع يفكر معك <span class="text-gradient">لا يعظك</span>
            </h1>
            <p class="text-body-lg max-w-2xl mx-auto">
                فضاء نقاشي لأصحاب المشاريع ومديري التسويق ومقدمي الخدمات — مكان لطرح تحدياتك الفعلية، والتعلم من تجارب مَن سبقوك إلى نفس المنعطف.
            </p>

            <div class="page-actions page-actions-center mt-6">
                <a href="{{ route('register') }}" class="btn btn-primary btn-lg">انضم للمجتمع</a>
                <a href="{{ route('contact') }}" class="btn btn-secondary btn-lg">اقترح موضوعاً</a>
            </div>
        </div>
    </div>
</section>

{{-- ═══ ميثاق المجتمع ═══ --}}
<section class="section-band bg-2">
    <div class="site-container">
        <div class="section-header reveal mb-8">
            <p class="text-eyebrow mb-3 text-p">الميثاق</p>
            <h2 class="heading-lg mb-4">ثلاث قواعد <span class="text-gradient">تحكم النقاش</span></h2>
            <p class="text-body max-w-2xl mx-auto">
                المجتمعات بلا ميثاق تتحول إلى ضجيج. هذه القواعد الثلاث تضمن أن يبقى النقاش ذا قيمة لكل من يدخل.
            </p>
        </div>

        <div class="three-col">
            @foreach($communityPrinciples as $i => $p)
            <article class="page-feature-card page-feature-{{ $p['tone'] }} reveal d-{{ $i + 1 }}">
                <div class="page-feature-bar" aria-hidden="true"></div>
                <span class="page-feature-index">{{ sprintf('%02d', $i + 1) }}</span>
                <h3 class="page-feature-title">{{ $p['title'] }}</h3>
                <p class="page-feature-body">{{ $p['body'] }}</p>
            </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══ المواضيع ═══ --}}
<section class="section-lg">
    <div class="site-container">
        <div class="section-header reveal mb-8">
            <p class="text-eyebrow mb-3 text-p">آخر المواضيع</p>
            <h2 class="heading-lg">نقاشات <span class="text-gradient">حديثة من المجتمع</span></h2>
        </div>

        @if($posts->isEmpty())
            <div class="page-summary-card text-center max-w-2xl mx-auto">
                <p class="text-body mb-4">لا توجد مواضيع منشورة بعد. كن أول من يبدأ النقاش.</p>
                <a href="{{ route('register') }}" class="btn btn-primary btn-lg">أنشئ حسابك</a>
            </div>
        @else
            @if($pinnedPost)
            <article class="community-pinned reveal mb-8">
                <div class="community-pinned-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="17" x2="12" y2="22"/><path d="M5 17h14v-1.76a2 2 0 00-1.11-1.79l-1.78-.9A2 2 0 0115 10.76V6h1a2 2 0 002-2V3H6v1a2 2 0 002 2h1v4.76a2 2 0 01-1.11 1.79l-1.78.9A2 2 0 005 15.24V17z"/></svg>
                    <span>موضوع مثبت</span>
                </div>
                <div class="community-pinned-body">
                    <p class="community-meta">
                        <span>{{ optional($pinnedPost->published_at)->translatedFormat('d F Y') }}</span>
                        @if($pinnedPost->author_display_name)
                            <span class="community-meta-sep">·</span>
                            <span>{{ $pinnedPost->author_display_name }}</span>
                        @endif
                    </p>
                    <h3 class="community-pinned-title">
                        <a href="{{ route('community.show', $pinnedPost->slug) }}">{{ $pinnedPost->title }}</a>
                    </h3>
                    @if($pinnedPost->excerpt)
                        <p class="community-pinned-excerpt">{{ \Illuminate\Support\Str::limit($pinnedPost->excerpt, 280) }}</p>
                    @endif
                    <a href="{{ route('community.show', $pinnedPost->slug) }}" class="btn btn-primary btn-sm">افتح الموضوع</a>
                </div>
            </article>
            @endif

            @if($otherPosts->isNotEmpty())
            <div class="community-grid">
                @foreach ($otherPosts as $i => $post)
                    <article class="community-card reveal d-{{ ($i % 3) + 1 }}">
                        <div class="community-card-head">
                            <div class="community-avatar" aria-hidden="true">
                                {{ mb_substr($post->author_display_name ?? 'م', 0, 1, 'UTF-8') }}
                            </div>
                            <div class="community-card-meta">
                                @if($post->author_display_name)
                                    <p class="community-author">{{ $post->author_display_name }}</p>
                                @endif
                                <p class="community-date">{{ optional($post->published_at)->translatedFormat('d F Y') }}</p>
                            </div>
                        </div>
                        <h3 class="community-card-title">
                            <a href="{{ route('community.show', $post->slug) }}">{{ $post->title }}</a>
                        </h3>
                        @if($post->excerpt)
                            <p class="community-card-excerpt">{{ \Illuminate\Support\Str::limit($post->excerpt, 160) }}</p>
                        @endif
                        <a href="{{ route('community.show', $post->slug) }}" class="community-card-link">
                            تابع القراءة
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </a>
                    </article>
                @endforeach
            </div>
            @endif

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
                    <span class="section-badge-text internal-cta-badge-text">كن جزءاً من النقاش</span>
                </div>
                <h2 class="cta-headline">التعلم يتضاعف حين تشاركه</h2>
                <p class="cta-body">سجّل حسابك وانضم إلى حوار مهني مع أصحاب مشاريع ومديري تسويق يتعلمون معاً كل يوم.</p>
                <div class="cta-actions">
                    <a href="{{ route('register') }}" class="btn btn-primary btn-xl">إنشاء حساب</a>
                    <a href="{{ route('paths.index') }}" class="btn btn-ghost btn-xl">استكشف المسارات</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('head')
<style>
    /* — الموضوع المثبت — */
    .community-pinned {
        position: relative;
        padding: var(--sp-8);
        border-radius: var(--r-xl);
        border: 1px solid var(--p);
        background: linear-gradient(135deg, color-mix(in srgb, var(--p) 5%, var(--surface-2)), var(--surface-2));
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--p) 10%, transparent);
    }
    .community-pinned-badge {
        display: inline-flex;
        align-items: center;
        gap: var(--sp-2);
        padding: 6px 14px;
        background: var(--p);
        color: #fff;
        border-radius: var(--r-full);
        font-size: var(--fs-xs);
        font-weight: 800;
        margin-bottom: var(--sp-4);
    }
    .community-pinned-title {
        font-size: var(--fs-xl);
        font-weight: 900;
        line-height: 1.4;
        margin-bottom: var(--sp-4);
    }
    .community-pinned-title a {
        color: var(--text);
        text-decoration: none;
    }
    .community-pinned-title a:hover { color: var(--p); }
    .community-pinned-excerpt {
        font-size: var(--fs-base);
        color: var(--text-muted);
        line-height: 1.9;
        margin-bottom: var(--sp-5);
    }

    /* — meta data — */
    .community-meta {
        display: flex;
        align-items: center;
        gap: var(--sp-2);
        font-size: var(--fs-xs);
        color: var(--text-muted);
        font-weight: 600;
        margin-bottom: var(--sp-3);
    }
    .community-meta-sep { opacity: 0.5; }

    /* — شبكة المواضيع — */
    .community-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: var(--sp-5);
    }
    .community-card {
        display: flex;
        flex-direction: column;
        padding: var(--sp-6);
        border-radius: var(--r-lg);
        border: 1px solid var(--border);
        background: var(--surface-2);
        transition: border-color var(--dur-base) var(--ease), transform var(--dur-base) var(--ease);
    }
    .community-card:hover {
        border-color: var(--p);
        transform: translateY(-3px);
    }
    .community-card-head {
        display: flex;
        align-items: center;
        gap: var(--sp-3);
        margin-bottom: var(--sp-4);
    }
    .community-avatar {
        width: 40px; height: 40px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, var(--p), var(--teal));
        color: #fff;
        font-weight: 800;
        font-size: var(--fs-md);
        flex-shrink: 0;
    }
    .community-author {
        font-size: var(--fs-sm);
        font-weight: 700;
        color: var(--text);
        margin: 0;
    }
    .community-date {
        font-size: var(--fs-xs);
        color: var(--text-muted);
        margin: 0;
    }
    .community-card-title {
        font-size: var(--fs-md);
        font-weight: 800;
        line-height: 1.4;
        margin-bottom: var(--sp-3);
    }
    .community-card-title a {
        color: var(--text);
        text-decoration: none;
    }
    .community-card-title a:hover { color: var(--p); }
    .community-card-excerpt {
        font-size: var(--fs-sm);
        color: var(--text-muted);
        line-height: 1.7;
        margin-bottom: var(--sp-4);
        flex: 1;
    }
    .community-card-link {
        display: inline-flex;
        align-items: center;
        gap: var(--sp-2);
        font-size: var(--fs-sm);
        font-weight: 800;
        color: var(--p);
        text-decoration: none;
        margin-top: auto;
    }
    .community-card-link:hover { gap: var(--sp-3); }
</style>
@endpush
