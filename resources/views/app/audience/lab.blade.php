@extends('layouts.app')
@section('layout', 'detail')

@section('title', 'مختبر الجمهور — '.$project->name)

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">اختبار الرسائل · {{ $project->name }}</p>
            <h1>مختبر الجمهور</h1>
            <p class="muted">
                اكتب رسالتك مرة، فيقرأها جمهور اصطناعي مبني من بيانات مشروعك،
                ويخرج كلٌّ منهم بنسخته هو — رسالة لكل شخصية لا رسالة واحدة للجميع.
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
                المتردد، الحساس للسعر — ثم تعرض عليهم أي رسالة، فيعيدها كلٌّ
                منهم مكتوبة بلسانه.
            </p>
        </section>
    @else
        <section aria-labelledby="panel-heading">
            <h2 id="panel-heading" class="section-title">لوحة جمهورك</h2>
            <p class="alert alert--info">
                @include('app.partials.evidence-badge', [
                    'level' => $panel->evidenceLevel(),
                    'note' => 'شخصيات مبنية على وصف مشروعك لا عملاء حقيقيين — الدرجات ترتّب الصياغات ولا تتنبأ بالأداء.',
                ])
            </p>
            <div class="card-grid">
                @foreach ($panel->personas as $persona)
                    @include('app.partials.persona-card', ['persona' => $persona])
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
                <span class="field__help">
                    الاختبار يستغرق نحو دقيقة — تخرج بدرجة واعتراض صريح
                    <strong>ورسالة مخصّصة</strong> لكل شخصية، جاهزة للنسخ.
                </span>
            </form>
        </section>

        @foreach ($tests as $test)
            <article class="card persona-test">
                <p class="eyebrow">{{ $test->created_at->translatedFormat('j F Y — H:i') }}</p>
                <blockquote class="persona-test__message">{{ $test->message }}</blockquote>

                <ul class="pulse-items">
                    @foreach ($test->results['reactions'] ?? [] as $index => $reaction)
                        <li class="pulse-item">
                            <strong>
                                {{ $reaction['persona'] }}
                                <span class="score-chip">{{ $reaction['score'] }}/100</span>
                                @include('app.partials.evidence-badge', ['level' => $test->evidenceLevel()])
                            </strong>
                            <p class="muted">{{ $reaction['reaction'] }}</p>
                            @if (! empty($reaction['objection']))
                                <p class="persona-objection">الاعتراض: {{ $reaction['objection'] }}</p>
                            @endif

                            @if (! empty($reaction['tailored_message']))
                                @php($messageId = 'msg-'.$test->id.'-'.$index)
                                <div class="persona-message">
                                    <p class="eyebrow">رسالتها هي</p>
                                    <blockquote id="{{ $messageId }}">{{ $reaction['tailored_message'] }}</blockquote>
                                    @if (! empty($reaction['angle']))
                                        <p class="muted"><strong>لماذا تناسبها:</strong> {{ $reaction['angle'] }}</p>
                                    @endif
                                    <button type="button" class="btn btn--ghost btn--sm"
                                        data-copy-message="{{ $messageId }}">انسخ رسالتها</button>
                                </div>
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
                        {{-- اختبارات سابقة كانت تنتج نصًّا موحّدًا: تُعرض كما حُفظت ولا تُعاد كتابتها. --}}
                        @if (! empty($test->results['overall']['improved_version']))
                            <p class="muted">
                                <strong>نسخة موحّدة (اختبار قديم):</strong>
                                {{ $test->results['overall']['improved_version'] }}
                            </p>
                        @endif
                    </div>
                @endif
            </article>
        @endforeach

        <script>
            document.querySelectorAll('[data-copy-message]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var source = document.getElementById(button.dataset.copyMessage);

                    if (! source) {
                        return;
                    }

                    // الزاوية التعليمية خارج الاقتباس عمدًا، فما يُنسخ نصُّ الرسالة وحده.
                    navigator.clipboard.writeText(source.textContent.trim()).then(function () {
                        var original = button.textContent;
                        button.textContent = 'نُسخت';
                        setTimeout(function () { button.textContent = original; }, 2000);
                    });
                });
            });
        </script>
    @endif
@endsection
