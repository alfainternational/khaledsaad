@extends('layouts.public')

@section('title', 'نتيجتك الأولية | خالد سعد')

@section('content')
    @include('partials.site-header')

    <main id="main-content" class="try-shell">
        <div class="container try-layout">
            <header class="try-head">
                <p class="eyebrow">{{ $run['tool']['title'] }}</p>
                <h1>جاهز. إجاباتك مكتملة.</h1>
                <p class="page-hero__lead">
                    راجع ما كتبته أدناه. الخطوة الأخيرة أن تنشئ حسابًا مجانيًا في دقيقة،
                    فنبدأ التحليل ونحفظ لك النتيجة والمهام في مشروعك.
                </p>
            </header>

            <section class="try-cta">
                <div>
                    <h2>لماذا نطلب الحساب هنا تحديدًا؟</h2>
                    <ul class="check-list">
                        <li><span>✓</span> التحليل يأخذ دقيقة أو دقيقتين، والحساب هو ما يخبرك حين يجهز</li>
                        <li><span>✓</span> نتيجتك ومهامك تبقى محفوظة، وترجع لها بعد شهر لتقارن</li>
                        <li><span>✓</span> ما كتبته الآن ينتقل معك كما هو — لن نسألك عنه من جديد</li>
                    </ul>
                </div>
                <div class="try-cta__actions">
                    <a class="button button--primary button--large" href="{{ route('register', ['tool' => $tool->key]) }}">
                        أنشئ حسابك واحفظ نتيجتك <span aria-hidden="true">←</span>
                    </a>
                    <a class="text-link" href="{{ route('login', ['tool' => $tool->key]) }}">عندي حساب بالفعل ←</a>
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
                <p class="alert alert--info" role="status">
                    ناقص عليك: {{ implode('، ', $preflight['missing']) }}
                </p>
            @endif
        </div>
    </main>

    @include('partials.site-footer', ['brand' => config('brand')])
@endsection
