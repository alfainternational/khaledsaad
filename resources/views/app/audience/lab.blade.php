@extends('layouts.app')

@section('title', 'مختبر الجمهور — '.$project->name)

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">اختبار الرسائل · {{ $project->name }}</p>
            <h1>مختبر الجمهور</h1>
            <p class="muted">
                اختبر رسالتك على جمهور اصطناعي مبني من بيانات مشروعك —
                قبل أن تدفع ريالًا واحدًا في إعلانها.
            </p>
        </div>
        <div class="page-head__actions">
            <form method="POST" action="{{ route('app.audience.panel', $project) }}">
                @csrf
                <button type="submit" class="btn {{ $panel === null ? 'btn--primary' : 'btn--ghost' }}">
                    {{ $panel === null ? 'ابنِ لوحة جمهورك' : 'أعد بناء اللوحة' }}
                </button>
            </form>
        </div>
    </header>

    @if ($panel === null)
        <section class="empty">
            <h2>جمهورك الاصطناعي لم يُبنَ بعد</h2>
            <p class="muted">
                نبني من ملف مشروعك وشرائح جمهورك 3–4 شخصيات واقعية: المتحمس،
                المتردد، الحساس للسعر — ثم تختبر عليهم أي رسالة قبل نشرها.
            </p>
        </section>
    @else
        <section aria-labelledby="panel-heading">
            <h2 id="panel-heading" class="section-title">لوحة جمهورك</h2>
            <div class="card-grid">
                @foreach ($panel->personas as $persona)
                    <article class="card persona-card">
                        <p class="eyebrow">{{ $persona['age_range'] ?? '' }}</p>
                        <h3>{{ $persona['name'] }}</h3>
                        <p class="muted">{{ $persona['role'] }}</p>

                        @if (! empty($persona['quote']))
                            <blockquote class="persona-quote">«{{ $persona['quote'] }}»</blockquote>
                        @endif

                        @if (! empty($persona['pains']))
                            <ul class="bullets">
                                @foreach (array_slice((array) $persona['pains'], 0, 3) as $pain)
                                    <li>{{ $pain }}</li>
                                @endforeach
                            </ul>
                        @endif

                        @if (! empty($persona['buying_style']))
                            <p class="muted"><strong>أسلوب الشراء:</strong> {{ $persona['buying_style'] }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <section class="card" aria-labelledby="test-heading">
            <h2 id="test-heading" class="section-title">اختبر رسالة</h2>
            <form method="POST" action="{{ route('app.audience.test', $project) }}" class="stack">
                @csrf
                <textarea name="message" rows="4" required minlength="10" maxlength="1000"
                    placeholder="الصق نص إعلانك أو منشورك أو رسالتك كما ستنشرها فعلًا…"
                    aria-label="الرسالة المطلوب اختبارها">{{ old('message') }}</textarea>
                <button type="submit" class="btn btn--primary">اعرضها على الجمهور</button>
                <span class="field__help">الاختبار يستغرق نحو دقيقة — كل شخصية تعطيك درجة واعتراضها الصريح.</span>
            </form>
        </section>

        @foreach ($tests as $test)
            <article class="card persona-test">
                <p class="eyebrow">{{ $test->created_at->translatedFormat('j F Y — H:i') }}</p>
                <blockquote class="persona-test__message">{{ $test->message }}</blockquote>

                <ul class="pulse-items">
                    @foreach ($test->results['reactions'] ?? [] as $reaction)
                        <li class="pulse-item">
                            <strong>
                                {{ $reaction['persona'] }}
                                <span class="score-chip">{{ $reaction['score'] }}/100</span>
                            </strong>
                            <p class="muted">{{ $reaction['reaction'] }}</p>
                            @if (! empty($reaction['objection']))
                                <p class="persona-objection">الاعتراض: {{ $reaction['objection'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>

                @if (! empty($test->results['overall']))
                    <div class="next-step">
                        <p class="eyebrow">الخلاصة</p>
                        <p>{{ $test->results['overall']['verdict'] }}</p>
                        @if (! empty($test->results['overall']['biggest_risk']))
                            <p class="muted"><strong>أكبر خطر:</strong> {{ $test->results['overall']['biggest_risk'] }}</p>
                        @endif
                        @if (! empty($test->results['overall']['improved_version']))
                            <p><strong>نسخة محسّنة:</strong> {{ $test->results['overall']['improved_version'] }}</p>
                        @endif
                    </div>
                @endif
            </article>
        @endforeach
    @endif
@endsection
