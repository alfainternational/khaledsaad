@extends('layouts.app')
@section('layout', 'detail')

@section('title', 'استوديو الرسائل — '.$project->name)

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">رسالة لكل شخصية · {{ $project->name }}</p>
            <h1>استوديو الرسائل</h1>
            <p class="muted">
                لكل شخصية في جمهورك تبويبها ورسالتها هي — تُقترح أو تكتبها،
                تختبرها وحدها أو مع البقية، وتعتمد الإصدار الذي فاز.
            </p>
        </div>
        <div class="page-head__actions">
            <form method="POST" action="{{ route('app.messages.panel', $project) }}">
                @csrf
                <button type="submit" class="btn {{ $panel === null ? 'btn--primary' : 'btn--ghost' }}">
                    {{ $panel === null ? 'ابنِ لوحة جمهورك' : 'أعد بناء اللوحة' }}
                </button>
            </form>
        </div>
    </header>

    @if ($errors->has('studio'))
        <p class="alert alert--error">{{ $errors->first('studio') }}</p>
    @endif

    @if ($source)
        {{-- ما يُمرَّر من التقرير يُسمّى صراحةً: المستخدم يعرف ما يعرفه النموذج عنه. --}}
        <p class="alert alert--info">
            الاقتراحات ستُبنى على تقرير <strong>{{ $source['report']->title }}</strong>.
            @if ($source['context'])
                يُمرَّر منه ما له دليل فقط ({{ count($source['context']['evidence'] ?? []) }} نتيجة مؤكدة)
                — والاستنتاجات لا تُمرَّر.
            @else
                لا نتيجة مدعومة بدليل فيه بعد، فلن تُمرَّر منه حقائق.
            @endif
        </p>
    @endif

    @if ($panel === null)
        <section class="empty">
            <h2>لوحة جمهورك لم تُبنَ بعد</h2>
            <p class="muted">
                نبني 3–4 شخصيات بتفاصيل استهداف كاملة — العمر والجنس والمدن
                والاهتمامات والمنصات ومستوى الإنفاق — ثم تكتب لكل واحدة رسالتها.
            </p>
        </section>
    @else
        {{-- الهدف والقناة يحكمان كل ما بعدهما: الحد والنبرة والاقتراح. --}}
        <section class="card" aria-labelledby="scope-heading">
            <h2 id="scope-heading" class="section-title">القناة والهدف</h2>
            <form method="GET" action="{{ route('app.messages.studio', $project) }}" class="field-row">
                <label class="field">
                    <span class="field__label">القناة</span>
                    <select name="channel" onchange="this.form.submit()">
                        @foreach ($channels as $value => $label)
                            <option value="{{ $value }}" @selected($channel->value === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">
                    <span class="field__label">الهدف</span>
                    <select name="objective" onchange="this.form.submit()">
                        @foreach ($objectives as $value => $label)
                            <option value="{{ $value }}" @selected($objective->value === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <noscript><button type="submit" class="btn btn--ghost btn--sm">طبّق</button></noscript>
            </form>
            <p class="field__help">{{ $channel->hint() }} الحد {{ $channel->maxLength() }} محرفًا · {{ $objective->instruction() }}</p>

            <div class="studio-actions">
                <form method="POST" action="{{ route('app.messages.suggest', $project) }}">
                    @csrf
                    <input type="hidden" name="channel" value="{{ $channel->value }}">
                    <input type="hidden" name="objective" value="{{ $objective->value }}">
                    @if ($source)
                        <input type="hidden" name="source" value="report">
                        <input type="hidden" name="source_id" value="{{ $source['report']->id }}">
                    @endif
                    <button type="submit" class="btn btn--primary btn--sm" data-once>اقترح رسائل للجميع</button>
                </form>

                @php($ready = collect($personas)->filter(fn ($tab) => $tab['current'] !== null)->count())
                <form method="POST" action="{{ route('app.messages.test', $project) }}">
                    @csrf
                    <input type="hidden" name="channel" value="{{ $channel->value }}">
                    <input type="hidden" name="objective" value="{{ $objective->value }}">
                    <button type="submit" class="btn btn--ghost btn--sm" data-once
                        @disabled($ready < count($personas) || $personas === [])>
                        اختبر جميع الرسائل ({{ $ready }}/{{ count($personas) }})
                    </button>
                </form>
            </div>
            @if ($ready < count($personas))
                <p class="field__help">الاختبار الجماعي يحتاج رسالة صالحة لكل شخصية — لا يُقارن ما لم يُكتب.</p>
            @endif
        </section>

        <section aria-labelledby="personas-heading">
            <h2 id="personas-heading" class="section-title">الشخصيات</h2>
            {{-- الفرضية تُعلن مرة في رأس القسم وعلى كل بطاقة، لا مرة واحدة يمر عليها البصر. --}}
            <p class="alert alert--info">
                @include('app.partials.evidence-badge', [
                    'level' => $panel->evidenceLevel(),
                    'note' => 'هؤلاء شخصيات مبنية على وصف مشروعك، لا عملاء حقيقيين.
                        درجاتهم ترتّب الصياغات بينها ولا تتنبأ بأداء إعلان.',
                ])
            </p>

            <div class="studio-tabs" role="tablist">
                @foreach ($personas as $index => $tab)
                    <button type="button" role="tab" class="studio-tab @if ($index === 0) is-active @endif"
                        data-studio-tab="{{ $tab['key'] }}"
                        aria-selected="{{ $index === 0 ? 'true' : 'false' }}">
                        {{ \App\Support\Messaging\PersonaName::display($tab['persona']['name'] ?? null) }}
                        <span class="badge">{{ \App\Support\Messaging\MessageStatus::label($tab['current']?->status) }}</span>
                    </button>
                @endforeach
            </div>

            @foreach ($personas as $index => $tab)
                <div class="studio-panel @if ($index !== 0) is-hidden @endif" data-studio-panel="{{ $tab['key'] }}">
                    @include('app.messages.partials.persona-workspace', [
                        'project' => $project,
                        'tab' => $tab,
                        'channel' => $channel,
                        'objective' => $objective,
                    ])
                </div>
            @endforeach
        </section>

        @if ($batches->isNotEmpty())
            <section aria-labelledby="batches-heading">
                <h2 id="batches-heading" class="section-title">نتائج الاختبارات</h2>
                @foreach ($batches as $batch)
                    <article class="card">
                        <p class="eyebrow">
                            {{ $batch->created_at->translatedFormat('j F Y — H:i') }} ·
                            {{ $batch->mode === 'batch' ? 'اختبار جماعي' : 'اختبار فردي' }}
                        </p>

                        <ul class="pulse-items">
                            @foreach ($batch->results as $result)
                                <li class="pulse-item">
                                    <strong>
                                        {{ \App\Support\Messaging\PersonaName::display(collect($personas)->firstWhere('key', $result->persona_key)['persona']['name'] ?? null) }}
                                        <span class="score-chip">{{ $result->score }}/100</span>
                                        @include('app.partials.evidence-badge', ['level' => $result->evidenceLevel()])
                                    </strong>
                                    <p class="muted">{{ $result->reaction }}</p>
                                    @if ($result->strength)
                                        <p class="muted"><strong>ما نجح:</strong> {{ $result->strength }}</p>
                                    @endif
                                    @if ($result->objection)
                                        <p class="persona-objection">ما بقي: {{ $result->objection }}</p>
                                    @endif
                                    @if ($result->revised_content)
                                        <div class="persona-message">
                                            <p class="eyebrow">تعديل مقترح لها وحدها</p>
                                            <blockquote>{{ $result->revised_content }}</blockquote>
                                            <form method="POST" action="{{ route('app.messages.revise', [$project, $result]) }}">
                                                @csrf
                                                <button type="submit" class="btn btn--ghost btn--sm">
                                                    أنشئ إصدارًا من هذا التعديل
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        @if (! empty($batch->summary['comparison']))
                            <div class="next-step">
                                <p class="eyebrow">المقارنة</p>
                                <p>{{ $batch->summary['comparison'] }}</p>
                                @if (! empty($batch->summary['next_experiment']))
                                    <p class="muted"><strong>التجربة التالية:</strong> {{ $batch->summary['next_experiment'] }}</p>
                                @endif
                                @if (! empty($batch->summary['incomplete']))
                                    <p class="persona-objection">
                                        لم تكتمل نتائج {{ count($batch->summary['incomplete']) }} شخصية — لم تُختلق لها نتيجة.
                                    </p>
                                @endif
                            </div>
                        @endif
                    </article>
                @endforeach
            </section>
        @endif

        @if ($legacyTests->isNotEmpty())
            <section aria-labelledby="legacy-heading">
                <h2 id="legacy-heading" class="section-title">اختبارات سابقة</h2>
                <p class="muted">
                    من مختبر الجمهور قبل الاستوديو. تُعرض كما حُفظت ولا تُحوَّل تلقائيًّا،
                    لأن تحويل رسالة عامة إلى رسائل شخصيات يخترع نية لم تحددها.
                </p>
                @foreach ($legacyTests as $legacy)
                    <article class="card">
                        <p class="eyebrow">
                            {{ $legacy->created_at->translatedFormat('j F Y') }}
                            <span class="badge badge--assumption">رسالة عامة قديمة</span>
                        </p>
                        <blockquote class="persona-test__message">{{ $legacy->message }}</blockquote>
                    </article>
                @endforeach
                <a href="{{ route('app.audience.show', $project) }}" class="btn btn--ghost btn--sm">افتح مختبر الجمهور</a>
            </section>
        @endif

        <script>
            (function () {
                var tabs = document.querySelectorAll('[data-studio-tab]');

                tabs.forEach(function (tab) {
                    tab.addEventListener('click', function () {
                        tabs.forEach(function (other) {
                            other.classList.toggle('is-active', other === tab);
                            other.setAttribute('aria-selected', other === tab ? 'true' : 'false');
                        });

                        document.querySelectorAll('[data-studio-panel]').forEach(function (panel) {
                            panel.classList.toggle('is-hidden', panel.dataset.studioPanel !== tab.dataset.studioTab);
                        });
                    });
                });

                // منع النقر المتكرر أثناء الطلب: نقرتان تعنيان دفعتي استعلام.
                document.querySelectorAll('form').forEach(function (form) {
                    form.addEventListener('submit', function () {
                        form.querySelectorAll('[data-once]').forEach(function (button) {
                            button.disabled = true;
                            button.textContent = 'جارٍ التنفيذ…';
                        });
                    });
                });

                document.querySelectorAll('[data-copy-message]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        var source = document.getElementById(button.dataset.copyMessage);

                        if (! source) {
                            return;
                        }

                        navigator.clipboard.writeText(source.textContent.trim()).then(function () {
                            var original = button.textContent;
                            button.textContent = 'نُسخت';
                            setTimeout(function () { button.textContent = original; }, 2000);
                        });
                    });
                });
            })();
        </script>
    @endif
@endsection
