@extends('layouts.app')

@section('title', 'راجع إجاباتك')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">{{ $run['tool']['title'] }} · {{ $run['project']['name'] }}</p>
            <h1>راجع إجاباتك قبل ما نبدأ</h1>
            <p class="muted">لو في إجابة تحتاج تعديلًا، عدّلها الآن — كل ما يأتي بعدها مبني عليها.</p>
        </div>
    </header>

    <section class="split">
        <article class="card">
            <p class="eyebrow">كم أكملت</p>
            <p class="score-big">{{ $preflight['percent'] }}<small>%</small></p>

            @if ($preflight['missing'] === [])
                <p class="muted">كل شيء مكتمل. تقدر تبدأ.</p>
            @else
                <p class="field__error">ناقص عليك:</p>
                <ul class="bullets">
                    @foreach ($preflight['missing'] as $missing)
                        <li>{{ $missing }}</li>
                    @endforeach
                </ul>
            @endif
        </article>

        <article class="card">
            <p class="eyebrow">أشياء سنفترضها</p>
            @if ($preflight['assumptions'] === [])
                <p class="muted">لا شيء. كل ما كتبته سنعتمده كما هو منك.</p>
            @else
                <ul class="bullets">
                    @foreach ($preflight['assumptions'] as $assumption)
                        <li>{{ $assumption }}</li>
                    @endforeach
                </ul>
            @endif
        </article>
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
            <a href="{{ route('app.runs.step', [$run['uuid'], $step['step']]) }}" class="btn btn--ghost btn--sm">عدّل هذه الخطوة</a>
        @endforeach
    </section>

    <section class="card">
        <h2 class="section-title">أرفق أدلة (اختياري)</h2>
        <p class="muted">ملفات تدعم إجاباتك: بروفايل، تقرير، جدول أسعار. نقرأها ونضيفها للتحليل. المدعوم: PDF، Word، Excel، صور، نصوص.</p>

        @if ($run['files'] !== [])
            <div class="attachments">
                @foreach ($run['files'] as $file)
                    <div class="attachment">
                        <span>{{ $file['name'] }}</span>
                        <div class="attachment__meta">
                            <span>{{ $file['size_kb'] }} ك.ب</span>
                            <span class="attachment__status--{{ $file['status'] }}">{{ $file['status_label'] }}</span>
                            <form method="POST" action="{{ route('app.runs.files.destroy', [$run['uuid'], $file['id']]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn--ghost btn--sm">حذف</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('app.runs.files.store', $run['uuid']) }}" enctype="multipart/form-data" class="upload-zone">
            @csrf
            <input type="file" name="file" required aria-label="اختر ملفًا">
            <button type="submit" class="btn btn--ghost btn--sm">أرفق</button>
        </form>
    </section>

    <form method="POST" action="{{ route('app.runs.queue', $run['uuid']) }}">
        @csrf
        <button type="submit" class="btn btn--primary" @disabled($preflight['missing'] !== [])>
            ابدأ، جهّز لي النتيجة
        </button>
    </form>
@endsection
