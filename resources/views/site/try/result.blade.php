@extends('layouts.public')
@section('layout', 'report')

@section('title', 'نتيجتك الأولية | خالد سعد')

@section('content')
    @include('partials.site-header')

    <main id="main-content" class="try-shell">
        <div class="container try-layout layout-page layout-page--form">
            <header class="try-head">
                <p class="eyebrow">{{ $run['tool']['title'] }}</p>
                {{-- عنوان يصف الحالة لا يبشّر بها: النتيجة الكاملة لم تُحسب بعد. --}}
                <h1>اكتملت إجاباتك</h1>
                <p class="page-hero__lead">
                    راجع ما كتبته أدناه. أنشئ حسابك ليبدأ التحليل، وتُحفظ النتيجة
                    والمهام داخل مشروعك.
                </p>
            </header>

            @if ($preview !== null)
                {{--
                    المستوى ٠: الدرجة والفجوات بالاسم دون الحل.
                    لا يُضاف هنا سببٌ ولا علاج ولا توصية — الحدّ قرار إيراد
                    محسوم في المواصفة §٦، ويحرسه اختبار لا انضباط تحرير.
                --}}
                <section class="card try-preview">
                    <h2 class="section-title">درجتك الأولية</h2>
                    <p class="try-preview__score">
                        <strong>{{ $preview['score'] }}</strong><span>/100</span>
                    </p>
                    <p class="muted">
                        {{ $preview['band'] }} — محسوبة من {{ $preview['basis_count'] }} بندًا تنطبق على نشاطك.
                    </p>

                    @if ($peers !== null)
                        <p @class(['try-peers', 'try-peers--above' => $preview['score'] >= $peers['average'], 'try-peers--below' => $preview['score'] < $peers['average']])>
                            {{-- الرقم ومعه أساسه دائمًا (§١٣): «من كم تشخيصًا» جزء من المقارنة لا زينة. --}}
                            @if ($preview['score'] >= $peers['average'])
                                متوسط من أكمل هذا التشخيص {{ $peers['average'] }} من 100، محسوبًا من {{ $peers['count'] }} تشخيصًا. أنت فوقه.
                            @else
                                متوسط من أكمل هذا التشخيص {{ $peers['average'] }} من 100، محسوبًا من {{ $peers['count'] }} تشخيصًا. أنت دونه، والفجوات أدناه تريك أين يذهب الفرق.
                            @endif
                        </p>
                    @endif

                    @if ($preview['gaps'] !== [])
                        {{-- النص مرساة حارس المستوى ٠ (LevelZeroBoundaryTest): تغييره
                             يُسقط الحارس الذي يمنع تسرّب الحلّ إلى صفحة الزائر. --}}
                        <h3 class="review-step">أكبر ثلاث فجوات عندك</h3>
                        <ul class="check-list check-list--gaps">
                            @foreach ($preview['gaps'] as $gap)
                                <li><span aria-hidden="true">•</span> {{ $gap['label'] }}</li>
                            @endforeach
                        </ul>
                        <p class="muted">
                            التحليل الكامل يشرح سبب كل فجوة وكيف تُغلق، ويصلك بعد إنشاء حسابك.
                        </p>
                    @endif
                </section>
            @endif

            <section class="try-cta">
                <div>
                    {{-- سؤال يسأله الزائر لنفسه، وجوابه ثلاث فوائد ملموسة لا وعود. --}}
                    <h2>لماذا نطلب الحساب هنا تحديدًا؟</h2>
                    <ul class="check-list">
                        <li><span>✓</span> يصلك إشعار عند اكتمال التحليل</li>
                        <li><span>✓</span> تبقى نتيجتك ومهامك محفوظة، فتعود إليها وتقارن تقدّمك</li>
                        <li><span>✓</span> ما كتبته الآن ينتقل معك كما هو — لن نسألك عنه من جديد</li>
                    </ul>
                </div>
                <div class="try-cta__actions">
                    <a class="button button--primary button--large" href="{{ route('register', ['tool' => $tool->key]) }}">
                        أنشئ حسابك واحفظ نتيجتك <span aria-hidden="true">←</span>
                    </a>
                    <a class="text-link" href="{{ route('login', ['tool' => $tool->key]) }}">لدي حساب — سجّل دخولي ←</a>
                </div>
            </section>

            <section class="card">
                <h2 class="section-title">ما كتبته</h2>
                @foreach ($run['steps'] as $step)
                    <h3 class="review-step">{{ $step['title'] }}</h3>
                    <ul class="kv">
                        @foreach ($step['fields'] as $field)
                            <li>
                                <span>{{ $field['label'] }}</span>
                                <strong>
                                    @if (is_array($field['value']))
                                        {{ $field['value'] === [] ? '—' : implode('، ', $field['value']) }}
                                    @else
                                        {{ $field['value'] === null || $field['value'] === '' ? '—' : $field['value'] }}
                                    @endif
                                </strong>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('try.step', [$run['uuid'], $step['step']]) }}" class="btn btn--ghost btn--sm">عدّل هذه الخطوة</a>
                @endforeach
            </section>

            @if ($preflight['missing'] !== [])
                {{-- النقص يُعلن باسمه (§٤.٣) ولا يُملأ بتقدير صامت. --}}
                <p class="alert alert--info" role="status">
                    ما زال ناقصًا: {{ implode('، ', $preflight['missing']) }}. أكمله لتدقّ نتيجتك أكثر.
                </p>
            @endif
        </div>
    </main>

    @include('partials.site-footer', ['brand' => config('brand')])
@endsection
