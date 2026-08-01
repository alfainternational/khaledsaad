@extends('layouts.public')
@section('layout', 'marketing')

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
                    {{-- اسم المسار هو اسم الرابط في القائمة العلوية: مسمّى واحد
                         للصفحة الواحدة، وإلا ظنّها الزائر صفحتين. --}}
                    <a href="{{ route('tools.index') }}">التشخيصات</a>
                    <span aria-hidden="true">/</span>
                    <b>{{ $tool['title'] }}</b>
                </nav>

                <div class="tool-hero public-tool-hero">
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
                                <li><strong>ابدأ مباشرة</strong><span>بلا حساب وبلا بطاقة دفع</span></li>
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
                            {{-- الحالة تُقال كما هي بلا موعد لم يُحدَّد، ومعها مخرج
                                 يمشي به الزائر الآن بدل زر ميت. --}}
                            <div class="notice">
                                <strong>هذا التشخيص غير متاح حاليًا.</strong>
                                <p>لم يُفتح بعد. اختر تشخيصًا متاحًا الآن وابدأ من التحدي الأقرب إلى مشروعك.</p>
                                <a class="button button--primary" href="{{ route('tools.index') }}">اعرض التشخيصات المتاحة</a>
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
                                    <li><span>✓</span> مهام لها مواعيد تتابعها من لوحتك</li>
                                </ul>
                            </div>
                            <div class="panel-block">
                                <h2>ماذا نطلب منك؟</h2>
                                {{-- ما نطلبه يُقال قبل البدء لا بعده، وحق ترك الفراغ
                                     يُعلَن هنا لأن النقص المعلن أصدق من تقدير صامت. --}}
                                <ul class="check-list">
                                    <li><span>✓</span> إجابات من واقع مشروعك، بلا تجهيز ملف مسبق</li>
                                    <li><span>✓</span> اترك ما لا تعرفه فارغًا — نعرض لك ما نقص وكيف تستكمله</li>
                                    <li><span>✓</span> ما تكتبه يُحفظ في ملف مشروعك، ويقلّل أسئلة التشخيص التالي</li>
                                </ul>
                            </div>
                        </aside>
                    @endif
                </div>
            </div>
        </section>

        @if ($sample !== null)
            <section class="section sample-section" aria-labelledby="sample-title">
                <div class="container">
                    <x-section-heading
                        eyebrow="مثال توضيحي — ليس نتيجة عميل"
                        title="كيف تصلك النتيجة؟"
                        description="مقطع بصيغة التقرير نفسها: خلاصة، ونتيجة معها دليلها، وفرضية موسومة بأنها فرضية."
                        id="sample-title"
                    />

                    <div class="sample-report">
                        <p class="sample-report__summary">{{ $sample['summary'] }}</p>

                        @if ($sample['finding'])
                            <article class="sample-report__finding">
                                <header>
                                    <h3>{{ $sample['finding']['title'] }}</h3>
                                    <x-evidence-badge level="measured" compact />
                                </header>
                                <p>{{ $sample['finding']['description'] }}</p>
                                <p class="sample-report__evidence">الدليل: {{ $sample['finding']['evidence'] }}</p>
                            </article>
                        @endif

                        @if ($sample['assumption'])
                            <article class="sample-report__finding">
                                <header>
                                    <h3>{{ $sample['assumption']['title'] }}</h3>
                                    <x-evidence-badge level="inferred" compact />
                                </header>
                                <p>{{ $sample['assumption']['description'] }}</p>
                            </article>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        @if ($tool['is_runnable'] && $tool['steps'] !== [])
            <section class="section steps-section">
                <div class="container">
                    <x-section-heading
                        eyebrow="قبل أن تبدأ"
                        title="المعلومات التي ستحتاج إليها"
                        description="راجعها الآن. وبجانب كل سؤال داخل التشخيص سبب طرحه وأثره في دقة النتيجة."
                    />
                    <div class="steps-grid public-step-grid">
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
                        <x-section-heading eyebrow="تكمل بعضها" title="تشخيصات قريبة منه" align="start" />
                        <a class="text-link" href="{{ route('tools.index') }}">كل التشخيصات <span aria-hidden="true">←</span></a>
                    </div>
                    <div class="catalog-grid catalog-grid--three public-card-grid">
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
