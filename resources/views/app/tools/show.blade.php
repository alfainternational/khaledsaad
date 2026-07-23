@extends('layouts.app')

@section('title', $tool['title'])

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">{{ $tool['category'] }}</p>
            <h1>{{ $tool['title'] }}</h1>
            <p class="muted">{{ $tool['description'] }}</p>
        </div>
    </header>

    <section class="split">
        <article class="card">
            <p class="eyebrow">ماذا نطلب منك</p>
            <ul class="bullets">
                @foreach ($tool['inputs'] as $input)
                    <li>{{ $input }}</li>
                @endforeach
            </ul>
            <p class="muted">{{ $tool['step_count'] }} خطوات، تُحفظ أولًا بأول. وما كتبته من قبل لن نسألك عنه مجددًا.</p>
        </article>

        <article class="card">
            <p class="eyebrow">ماذا تخرج به</p>
            <ul class="bullets">
                <li>رقم واضح يقول لك أين أنت الآن</li>
                @foreach ($tool['outputs'] as $output)
                    <li>{{ $output }}</li>
                @endforeach
                <li>نفرّق بين ما هو مؤكد من كلامك وما يحتاج تأكيدًا</li>
                <li>مهام لها مواعيد تتابعها بنفسك</li>
            </ul>
        </article>
    </section>

    @if ($tool['is_runnable'] && ($engagement['state'] ?? 'new') !== 'new')
        {{-- استئناف قبل أي شيء: لا نطلب منه البدء من الصفر وعنده عمل قائم. --}}
        <section class="card card--resume">
            <p class="eyebrow">لديك عمل قائم هنا</p>
            <p>{{ $engagement['hint'] }}</p>

            @if ($engagement['state'] === 'draft' && $engagement['percent'] > 0)
                <div class="progress__bar progress__bar--slim">
                    <span style="inline-size: {{ $engagement['percent'] }}%"></span>
                </div>
                <p class="muted">أكملت {{ $engagement['percent'] }}%</p>
            @endif

            <div class="card__actions">
                <a href="{{ $engagement['url'] }}" class="btn btn--primary">{{ $engagement['label'] }}</a>
            </div>
        </section>
    @endif

    @if (! $tool['is_runnable'])
        <p class="alert alert--info">هذه لم تفتح بعد، ونعمل عليها الآن.</p>
    @elseif ($projects === [])
        <section class="empty">
            <h2>عرّفنا على مشروعك أولًا</h2>
            <p>نحفظ كل شيء داخل مشروعك حتى تقدر ترجع له وتقارن تقدمك بعد شهر.</p>
            <a href="{{ route('app.projects.create') }}" class="btn btn--primary">عرّفنا على مشروعك</a>
        </section>
    @else
        <section class="card">
            <h2 class="section-title">
                {{ ($engagement['state'] ?? 'new') === 'new' ? 'على أي مشروع نبدأ؟' : 'أو ابدأ من جديد على مشروع' }}
            </h2>
            <div class="run-launcher">
                @foreach ($projects as $project)
                    <form method="POST" action="{{ route('app.runs.start', [$project['slug'], $tool['key']]) }}">
                        @csrf
                        <button type="submit" class="btn btn--primary btn--sm">{{ $project['name'] }}</button>
                    </form>
                @endforeach
            </div>
        </section>
    @endif
@endsection
