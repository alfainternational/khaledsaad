@extends('layouts.marketing', ['title' => $title, 'description' => $description])

@section('content')
@php
    $isPrivacy = $page->slug === 'privacy';
    $isTerms = $page->slug === 'terms';
    $eyebrow = $isPrivacy ? 'سياسة الخصوصية' : ($isTerms ? 'الشروط والأحكام' : 'وثيقة قانونية');

    // روابط سريعة بين الوثيقتين
    $siblingLinks = [];
    if ($isPrivacy) {
        $siblingLinks[] = ['label' => 'الشروط والأحكام', 'route' => 'terms'];
    } elseif ($isTerms) {
        $siblingLinks[] = ['label' => 'سياسة الخصوصية', 'route' => 'privacy'];
    }
@endphp

{{-- ═══ Hero ═══ --}}
<section class="section-lg internal-page-hero">
    <div class="site-container">
        <div class="section-header reveal max-w-3xl mx-auto">
            <div class="section-badge section-badge-center mb-4">
                <span class="section-dot"></span>
                <span class="section-badge-text">{{ $eyebrow }}</span>
            </div>
            <h1 class="heading-lg mb-4">{{ $page->title }}</h1>
            @if($page->subtitle)
                <p class="text-body-lg max-w-2xl mx-auto">{{ $page->subtitle }}</p>
            @endif

            <div class="legal-meta mt-6">
                <span class="legal-meta-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    <span>آخر تحديث: {{ $page->updated_at?->translatedFormat('d F Y') }}</span>
                </span>

                @foreach($siblingLinks as $link)
                    <a href="{{ route($link['route']) }}" class="legal-meta-link">
                        {{ $link['label'] }}
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"/>
                            <polyline points="12 5 19 12 12 19"/>
                        </svg>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ═══ محتوى الوثيقة ═══ --}}
<section class="section-lg">
    <div class="site-container">
        <div class="legal-layout max-w-4xl mx-auto reveal">
            <article class="legal-document">
                <div class="marketing-cms-body legal-body">
                    {!! $page->body_html !!}
                </div>

                <footer class="legal-footer">
                    <p class="legal-footer-note">
                        إذا كان لديك سؤال حول {{ $eyebrow }}، يمكنك التواصل معنا مباشرة.
                    </p>
                    <a href="{{ route('contact') }}" class="btn btn-secondary btn-md">راسلنا</a>
                </footer>
            </article>
        </div>
    </div>
</section>

{{-- ═══ روابط ذات صلة ═══ --}}
<section class="section-band bg-2">
    <div class="site-container">
        <div class="section-header reveal mb-8">
            <p class="text-eyebrow mb-3 text-p">وثائق ذات صلة</p>
            <h2 class="heading-md">تصفّح باقي <span class="text-gradient">الوثائق القانونية</span></h2>
        </div>

        <div class="legal-links-grid max-w-3xl mx-auto">
            @if(!$isPrivacy)
                <a href="{{ route('privacy') }}" class="legal-link-card">
                    <div class="legal-link-icon" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                    </div>
                    <div class="legal-link-body">
                        <h3 class="legal-link-title">سياسة الخصوصية</h3>
                        <p class="legal-link-desc">كيف نجمع بياناتك ونحميها، وما حقوقك عليها.</p>
                    </div>
                </a>
            @endif

            @if(!$isTerms)
                <a href="{{ route('terms') }}" class="legal-link-card">
                    <div class="legal-link-icon" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                        </svg>
                    </div>
                    <div class="legal-link-body">
                        <h3 class="legal-link-title">الشروط والأحكام</h3>
                        <p class="legal-link-desc">القواعد التي تحكم استخدامك للمنصة.</p>
                    </div>
                </a>
            @endif

            <a href="{{ route('contact') }}" class="legal-link-card">
                <div class="legal-link-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/>
                    </svg>
                </div>
                <div class="legal-link-body">
                    <h3 class="legal-link-title">تواصل معنا</h3>
                    <p class="legal-link-desc">أسئلة أو استفسارات قانونية؟ اكتب لنا مباشرة.</p>
                </div>
            </a>
        </div>
    </div>
</section>
@endsection

@push('head')
<style>
    /* — شريط بيانات الوثيقة — */
    .legal-meta {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-wrap: wrap;
        gap: var(--sp-4);
        padding: var(--sp-3) var(--sp-5);
        border-radius: var(--r-full);
        border: 1px solid var(--border);
        background: var(--surface-2);
        font-size: var(--fs-sm);
    }
    .legal-meta-item {
        display: inline-flex;
        align-items: center;
        gap: var(--sp-2);
        color: var(--text-muted);
        font-weight: 700;
    }
    .legal-meta-link {
        display: inline-flex;
        align-items: center;
        gap: var(--sp-2);
        color: var(--p);
        font-weight: 800;
        text-decoration: none;
        padding-inline-start: var(--sp-4);
        border-inline-start: 1px solid var(--border);
    }
    .legal-meta-link:hover { gap: var(--sp-3); }

    /* — تخطيط الوثيقة — */
    .legal-document {
        padding: var(--sp-8);
        border-radius: var(--r-xl);
        border: 1px solid var(--border);
        background: var(--surface);
    }

    /* — تنسيق محتوى الوثيقة — */
    .legal-body {
        color: var(--text);
        line-height: 1.9;
        font-size: var(--fs-base);
    }
    .legal-body h1,
    .legal-body h2,
    .legal-body h3,
    .legal-body h4 {
        color: var(--text);
        font-weight: 800;
        line-height: 1.4;
        margin-top: var(--sp-8);
        margin-bottom: var(--sp-4);
    }
    .legal-body h1 { font-size: var(--fs-2xl); }
    .legal-body h2 {
        font-size: var(--fs-xl);
        padding-bottom: var(--sp-2);
        border-bottom: 2px solid var(--border);
    }
    .legal-body h3 { font-size: var(--fs-lg); }
    .legal-body h4 { font-size: var(--fs-md); }
    .legal-body h1:first-child,
    .legal-body h2:first-child,
    .legal-body h3:first-child {
        margin-top: 0;
    }
    .legal-body p {
        margin-bottom: var(--sp-4);
        color: var(--text);
    }
    .legal-body ul,
    .legal-body ol {
        padding-inline-start: 1.5rem;
        margin-bottom: var(--sp-4);
    }
    .legal-body li {
        margin-bottom: var(--sp-2);
        line-height: 1.9;
    }
    .legal-body a {
        color: var(--p);
        font-weight: 700;
        text-decoration: underline;
        text-underline-offset: 3px;
    }
    .legal-body a:hover { color: var(--p2); }
    .legal-body strong { font-weight: 800; color: var(--text); }
    .legal-body blockquote {
        padding: var(--sp-4) var(--sp-5);
        border-inline-start: 4px solid var(--p);
        background: var(--surface-2);
        border-radius: var(--r-md);
        margin: var(--sp-5) 0;
        color: var(--text-muted);
    }
    .legal-body code {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 0.92em;
        padding: 2px 6px;
        border-radius: var(--r-sm);
        background: color-mix(in srgb, var(--p) 10%, transparent);
        color: var(--p);
    }
    .legal-body table {
        width: 100%;
        border-collapse: collapse;
        margin: var(--sp-5) 0;
        font-size: var(--fs-sm);
    }
    .legal-body th,
    .legal-body td {
        padding: var(--sp-3) var(--sp-4);
        border: 1px solid var(--border);
        text-align: start;
    }
    .legal-body th {
        background: var(--surface-2);
        font-weight: 800;
        color: var(--text);
    }
    .legal-body hr {
        border: 0;
        border-top: 1px solid var(--border);
        margin: var(--sp-8) 0;
    }

    /* — تذييل الوثيقة — */
    .legal-footer {
        margin-top: var(--sp-10);
        padding-top: var(--sp-6);
        border-top: 1px dashed var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: var(--sp-4);
        flex-wrap: wrap;
    }
    .legal-footer-note {
        font-size: var(--fs-sm);
        color: var(--text-muted);
        margin: 0;
        flex: 1;
        min-width: 240px;
    }

    /* — بطاقات الروابط ذات الصلة — */
    .legal-links-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: var(--sp-4);
    }
    .legal-link-card {
        display: flex;
        align-items: flex-start;
        gap: var(--sp-4);
        padding: var(--sp-5);
        border-radius: var(--r-lg);
        border: 1px solid var(--border);
        background: var(--surface);
        text-decoration: none;
        transition: border-color var(--dur-base) var(--ease), transform var(--dur-base) var(--ease);
    }
    .legal-link-card:hover {
        border-color: var(--p);
        transform: translateY(-2px);
    }
    .legal-link-icon {
        width: 44px; height: 44px;
        flex-shrink: 0;
        border-radius: var(--r-md);
        display: flex; align-items: center; justify-content: center;
        background: color-mix(in srgb, var(--p) 12%, transparent);
        color: var(--p);
    }
    .legal-link-title {
        font-size: var(--fs-md);
        font-weight: 800;
        color: var(--text);
        margin: 0 0 4px;
    }
    .legal-link-desc {
        font-size: var(--fs-sm);
        color: var(--text-muted);
        margin: 0;
        line-height: 1.7;
    }

    @media (max-width: 640px) {
        .legal-document { padding: var(--sp-6); }
        .legal-meta { padding: var(--sp-3) var(--sp-4); }
        .legal-meta-link {
            padding-inline-start: 0;
            border-inline-start: 0;
        }
    }
</style>
@endpush
