@extends('layouts.app')
@section('layout', 'wizard')

@section('title', 'راجع إجاباتك')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">{{ $run['tool']['title'] }} · {{ $run['project']['name'] }}</p>
            <h1>راجع إجاباتك قبل بدء التحليل</h1>
            <p class="muted">عدّل أي إجابة تحتاج إلى تصحيح، لأن التقرير سيعتمد على المعلومات المعروضة هنا.</p>
        </div>
    </header>

    <section class="split">
        <article class="card">
            <p class="eyebrow">اكتمال المعلومات</p>
            <p class="score-big">{{ $preflight['percent'] }}<small>%</small></p>

            @if ($preflight['missing'] === [])
                <p class="muted">كل شيء مكتمل. يمكنك البدء.</p>
            @else
                <p class="field__error">أكمل المعلومات التالية:</p>
                <ul class="bullets">
                    @foreach ($preflight['missing'] as $missing)
                        <li>{{ $missing }}</li>
                    @endforeach
                </ul>
            @endif
        </article>

        <article class="card">
            <p class="eyebrow">معلومات تحتاج إلى تأكيد</p>
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
        <p class="muted">أرفق ما يدعم إجاباتك، مثل ملف تعريفي أو تقرير أو قائمة أسعار. يمكنك رفع PDF أو Word أو Excel أو صورة أو ملف نصي.</p>

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

    <section aria-labelledby="delivery-heading" class="delivery-choice">
        <h2 id="delivery-heading" class="section-title">كيف تريد نتيجتك؟</h2>
        <p class="muted">اختر بين نتيجة آلية أسرع أو مراجعة بشرية تستغرق وقتًا أطول.</p>

        <div class="delivery-options">
            <article class="delivery-option">
                <h3>تحليل آلي</h3>
                <p class="muted">تُحلل إجاباتك وتُجهز النتيجة عادة خلال دقائق.</p>
                <ul class="bullets">
                    <li>فوري — لا انتظار</li>
                    <li>يمكنك إعادة الطلب متى شئت</li>
                </ul>
                <form method="POST" action="{{ route('app.runs.queue', $run['uuid']) }}">
                    @csrf
                    <button type="submit" class="btn btn--primary" @disabled($preflight['missing'] !== [])>
                        ابدأ التحليل الآلي
                    </button>
                </form>
            </article>

            <article class="delivery-option delivery-option--manual">
                <h3>يراجعها خالد بنفسه</h3>
                <p class="muted">تُرسَل إجاباتك لمراجعة بشرية كاملة، وتصلك النتيجة موثّقة أنها دُقّقت يدويًا.</p>
                <ul class="bullets">
                    <li>مراجعة وتدقيق كامل بعين خبير</li>
                    <li>تستغرق وقتًا أطول — نُشعرك فور جاهزيتها</li>
                </ul>
                <form method="POST" action="{{ route('app.runs.manual', $run['uuid']) }}">
                    @csrf
                    <button type="submit" class="btn btn--ghost" @disabled($preflight['missing'] !== [])>
                        أرسلها لمراجعة يدوية
                    </button>
                </form>
            </article>
        </div>
    </section>
@endsection
