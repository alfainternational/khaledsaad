@extends('layouts.public')

@section('title', $tool['title'].' | خالد سعد')
@section('description', $tool['promise'] ?: $tool['description'])

@section('content')
    @include('partials.site-header', ['startTool' => $tool['is_runnable'] ? ['tool' => $tool['key']] : []])

    <main id="main-content">
        <section class="page-hero page-hero--tool">
            <div class="container">
                <nav class="crumbs" aria-label="مسار التنقل">
                    <a href="{{ route('home') }}">الرئيسية</a>
                    <span aria-hidden="true">/</span>
                    <a href="{{ route('tools.index') }}">بماذا نساعدك</a>
                    <span aria-hidden="true">/</span>
                    <b>{{ $tool['title'] }}</b>
                </nav>

                <div class="tool-hero">
                    <div class="tool-hero__copy">
                        <div class="tool-hero__tags">
                            <span class="catalog-card__category">{{ $tool['category'] }}</span>
                            @unless ($tool['is_runnable'])
                                <span class="pill pill--soon">قريبًا</span>
                            @endunless
                        </div>

                        @if ($tool['pain'])
                            <p class="tool-hero__pain">«{{ $tool['pain'] }}»</p>
                        @endif

                        <h1>{{ $tool['title'] }}</h1>
                        <p class="page-hero__lead">{{ $tool['description'] }}</p>

                        @if ($tool['is_runnable'])
                            <ul class="tool-hero__meta">
                                <li><strong>{{ $tool['duration_minutes'] ?? 10 }} دقائق</strong><span>وقتك تقريبًا</span></li>
                                <li><strong>{{ $tool['step_count'] }} خطوات</strong><span>تُحفظ أولًا بأول</span></li>
                                <li><strong>ابدأ مباشرة</strong><span>من دون بطاقة دفع</span></li>
                            </ul>

                            <div class="page-hero__actions">
                                @auth
                                    <a class="button button--primary button--large" href="{{ route('app.tools.show', $tool['key']) }}">
                                        ابدأ التشخيص لهذا المشروع <span aria-hidden="true">←</span>
                                    </a>
                                @else
                                    {{-- يجرّب أولًا بلا حساب؛ وما يكتبه ينتقل معه إن سجّل. --}}
                                    <form method="POST" action="{{ route('try.start', $tool['key']) }}">
                                        @csrf
                                        <button type="submit" class="button button--primary button--large">
                                            ابدأ من دون حساب <span aria-hidden="true">←</span>
                                        </button>
                                    </form>
                                    <a class="button button--ghost button--large" href="{{ route('login', ['tool' => $tool['key']]) }}">تسجيل الدخول</a>
                                @endauth
                            </div>

                            @if ($tool['audience'])
                                <p class="tool-hero__note">{{ $tool['audience'] }}</p>
                            @endif
                        @else
                            <div class="notice">
                                <strong>هذا التشخيص غير متاح حاليًا.</strong>
                                <p>يمكنك اختيار تشخيص متاح الآن والبدء بالتحدي الأقرب إلى مشروعك.</p>
                                <a class="button button--primary" href="{{ route('tools.index') }}">استكشف التشخيصات المتاحة</a>
                            </div>
                        @endif
                    </div>

                    @if ($tool['is_runnable'])
                        <aside class="tool-hero__panel" aria-label="ماذا يحدث هنا">
                            <div class="panel-block">
                                <h2>ما الذي ستحصل عليه؟</h2>
                                <ul class="check-list">
                                    @if ($tool['promise'])
                                        <li><span>✓</span> {{ $tool['promise'] }}</li>
                                    @endif
                                    @foreach ($tool['outputs'] as $output)
                                        <li><span>✓</span> {{ $output }}</li>
                                    @endforeach
                                    <li><span>✓</span> مهام لها مواعيد تتابعها بدل توصيات عامة</li>
                                </ul>
                            </div>
                            <div class="panel-block">
                                <h2>ماذا نطلب منك؟</h2>
                                <ul class="check-list">
                                    <li><span>✓</span> إجابات من واقع مشروعك، من دون الحاجة إلى تجهيز ملف معقد</li>
                                    <li><span>✓</span> يمكنك ترك ما لا تعرفه فارغًا، وسترى كيف تستكمل المعلومة لاحقًا</li>
                                    <li><span>✓</span> ما تكتبه هنا لن نسألك عنه في أي خطوة أخرى</li>
                                </ul>
                            </div>
                        </aside>
                    @endif
                </div>
            </div>
        </section>

        @if ($tool['is_runnable'] && $tool['steps'] !== [])
            <section class="section steps-section">
                <div class="container">
                    <x-section-heading
                        eyebrow="اعرف ما ينتظرك"
                        title="المعلومات التي ستحتاج إليها"
                        description="راجعها قبل أن تبدأ. سترى بجانب كل سؤال سبب طلبه وكيف يؤثر في دقة النتيجة."
                    />
                    <div class="steps-grid">
                        @foreach ($tool['steps'] as $step)
                            <article class="step-card">
                                <span class="step-card__number">الخطوة {{ $step['step'] }}</span>
                                <h3>{{ $step['title'] }}</h3>
                                <ul>
                                    @foreach ($step['fields'] as $field)
                                        <li>
                                            {{ $field['label'] }}
                                            @if (! empty($field['why']))
                                                <em>{{ \Illuminate\Support\Str::limit($field['why'], 110) }}</em>
                                            @endif
                                            @if (! empty($field['competitor_view']))
                                                <em class="step-card__bonus">+ نافذة على إعلانات منافسيك: {{ collect($field['competitor_view'])->pluck('source')->unique()->take(3)->implode('، ') }}</em>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($related !== [])
            <section class="section catalog-section catalog-section--related">
                <div class="container">
                    <div class="split-heading">
                        <x-section-heading eyebrow="تكمل بعضها" title="مشاكل قريبة منها" align="start" />
                        <a class="text-link" href="{{ route('tools.index') }}">كل الحالات <span aria-hidden="true">←</span></a>
                    </div>
                    <div class="catalog-grid catalog-grid--three">
                        @foreach ($related as $card)
                            <a href="{{ route('tools.show', $card['key']) }}" @class(['catalog-card', 'catalog-card--soon' => ! $card['is_runnable']])>
                                <div class="catalog-card__head">
                                    <span class="catalog-card__category">{{ $card['category'] }}</span>
                                    @unless ($card['is_runnable'])
                                        <span class="pill pill--soon">قريبًا</span>
                                    @endunless
                                </div>
                                @if ($card['pain'])
                                    <p class="catalog-card__pain">«{{ $card['pain'] }}»</p>
                                @endif
                                <h2>{{ $card['title'] }}</h2>
                                <p class="catalog-card__desc">{{ $card['promise'] ?: $card['description'] }}</p>
                                <span class="catalog-card__link">التفاصيل <b aria-hidden="true">←</b></span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
    </main>

    @include('partials.site-footer')
@endsection
