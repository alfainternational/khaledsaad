@extends('layouts.app')
@section('layout', 'detail')

@section('title', 'العملاء المتوقعون — '.$project->name)

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">رسالة باسم كل عميل · {{ $project->name }}</p>
            <h1>العملاء المتوقعون</h1>
            <p class="muted">
                أضف من تريد مخاطبتهم بأسمائهم، فتُكتب لكل واحد رسالته هو —
                مبنية على ما تعرفه عنه، لا على قالب يُرسل للجميع.
            </p>
        </div>
        <div class="page-head__actions">
            <a href="{{ route('app.messages.studio', $project) }}" class="btn btn--ghost btn--sm">استوديو الرسائل</a>
        </div>
    </header>

    @if ($errors->has('prospects'))
        <p class="alert alert--error">{{ $errors->first('prospects') }}</p>
    @endif

    @if ($panel === null)
        <p class="alert alert--info">
            لم تُبنَ لوحة جمهورك بعد. الرسائل ستُكتب من بيانات كل عميل وحدها —
            وببناء اللوحة تُضاف نبرة الشخصية الأقرب واعتراضها المرجّح.
        </p>
    @endif

    <section class="card" aria-labelledby="add-heading">
        <h2 id="add-heading" class="section-title">أضف عميلًا متوقعًا</h2>
        <form method="POST" action="{{ route('app.prospects.store', $project) }}" class="stack">
            @csrf
            <div class="field-row">
                <label class="field">
                    <span class="field__label">الاسم</span>
                    <input type="text" name="name" maxlength="120" required value="{{ old('name') }}">
                </label>
                <label class="field">
                    <span class="field__label">الجهة (اختياري)</span>
                    <input type="text" name="organization" maxlength="160" value="{{ old('organization') }}">
                </label>
                <label class="field">
                    <span class="field__label">صفته (اختياري)</span>
                    <input type="text" name="role" maxlength="120" value="{{ old('role') }}">
                </label>
            </div>

            <div class="field-row">
                <label class="field">
                    <span class="field__label">المدينة</span>
                    <input type="text" name="city" maxlength="80" value="{{ old('city') }}">
                </label>
                <label class="field">
                    <span class="field__label">اهتماماته</span>
                    <input type="text" name="interests" maxlength="400" value="{{ old('interests') }}"
                        placeholder="افصل بينها بفاصلة">
                </label>
                <label class="field">
                    <span class="field__label">حرارة النية</span>
                    <select name="temperature">
                        @foreach ($temperatures as $value => $label)
                            <option value="{{ $value }}" @selected(old('temperature', 'warm') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="field-row">
                <label class="field">
                    <span class="field__label">القناة التي تصله</span>
                    <select name="preferred_channel">
                        @foreach ($channels as $value => $label)
                            <option value="{{ $value }}" @selected(old('preferred_channel', 'whatsapp') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                @if ($personas !== [])
                    <label class="field">
                        <span class="field__label">الشخصية الأقرب (تُقترح تلقائيًّا)</span>
                        <select name="persona_key">
                            <option value="">اتركها للمطابقة التلقائية</option>
                            @foreach ($personas as $key => $name)
                                <option value="{{ $key }}" @selected(old('persona_key') === $key)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
            </div>

            <label class="field">
                <span class="field__label">ما تعرفه عنه</span>
                <textarea name="notes" rows="3" maxlength="2000"
                    placeholder="{{ __('أين قابلته، ماذا قال، ما الذي يشغله الآن…') }}">{{ old('notes') }}</textarea>
                <span class="field__help">
                    هذا الحقل هو الفرق بين رسالة شخصية ورسالة جماعية مُقنَّعة —
                    وما لا تكتبه هنا لن يُخترع لك.
                </span>
            </label>

            <button type="submit" class="btn btn--primary">أضِف</button>
        </form>
    </section>

    @if ($prospects->isEmpty())
        <section class="empty">
            <h2>لا عملاء متوقعون بعد</h2>
            <p class="muted">أضف أول اسم أعلاه، ثم ولّد رسالته.</p>
        </section>
    @else
        <section class="card" aria-labelledby="generate-heading">
            <h2 id="generate-heading" class="section-title">ولّد للجميع</h2>
            <form method="POST" action="{{ route('app.prospects.generate', $project) }}" class="field-row">
                @csrf
                <label class="field">
                    <span class="field__label">القناة</span>
                    <select name="channel">
                        @foreach ($channels as $value => $label)
                            <option value="{{ $value }}" @selected($channel->value === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">
                    <span class="field__label">الهدف</span>
                    <select name="objective">
                        @foreach ($objectives as $value => $label)
                            <option value="{{ $value }}" @selected($objective->value === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="btn btn--primary btn--sm" data-once>ولّد رسالة لكل عميل</button>
            </form>
            <p class="field__help">
                حتى {{ $batchLimit }} عملاء في الدفعة الواحدة — سقف يحمي ميزانية استعلاماتك.
                عندك {{ $prospects->count() }}.
            </p>
        </section>

        <section aria-labelledby="list-heading">
            <h2 id="list-heading" class="section-title">قائمتك ({{ $prospects->count() }})</h2>

            @foreach ($prospects as $prospect)
                @php($message = $prospect->messages->firstWhere('status', '!=', 'archived'))
                <article class="card prospect-card">
                    <div class="studio-head">
                        <div>
                            <h3>{{ $prospect->name }}</h3>
                            <p class="muted">
                                {{ collect([$prospect->role, $prospect->organization, $prospect->city])
                                    ->filter()->implode(' · ') ?: 'بلا تفاصيل إضافية' }}
                            </p>
                            <p class="eyebrow">
                                {{ $prospect->temperatureLabel() }}
                                @if ($prospect->persona_key && isset($personas[$prospect->persona_key]))
                                    · نبرة {{ $personas[$prospect->persona_key] }}
                                @endif
                            </p>
                        </div>
                        <form method="POST" action="{{ route('app.prospects.generate', $project) }}">
                            @csrf
                            <input type="hidden" name="prospect_id" value="{{ $prospect->id }}">
                            <input type="hidden" name="channel" value="{{ $prospect->preferred_channel }}">
                            <input type="hidden" name="objective" value="{{ $objective->value }}">
                            <button type="submit" class="btn btn--ghost btn--sm" data-once>
                                {{ $message ? 'ولّد نسخة جديدة' : 'ولّد رسالته' }}
                            </button>
                        </form>
                    </div>

                    @if ($prospect->interests)
                        <p class="muted"><strong>اهتماماته:</strong> {{ implode('، ', $prospect->interests) }}</p>
                    @endif

                    @if ($message)
                        @php($messageId = 'prospect-msg-'.$message->id)
                        <div class="persona-message">
                            <p class="eyebrow">
                                {{ \App\Support\Messaging\MessageChannel::from($message->channel)->label() }}
                                <span class="badge">{{ $message->statusLabel() }}</span>
                            </p>
                            <blockquote id="{{ $messageId }}">{{ $message->content }}</blockquote>
                            @if ($message->why)
                                <p class="muted"><strong>لماذا هكذا:</strong> {{ $message->why }}</p>
                            @endif
                            <div class="studio-actions">
                                <button type="button" class="btn btn--ghost btn--sm" data-copy-message="{{ $messageId }}">
                                    انسخ الرسالة
                                </button>
                                @if ($message->status !== 'sent')
                                    <form method="POST" action="{{ route('app.prospects.messages.sent', [$project, $message]) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn--ghost btn--sm">سجّلها كمُرسَلة</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @else
                        <p class="muted">لا رسالة له بعد.</p>
                    @endif

                    <details>
                        <summary>اكتب رسالته بنفسك</summary>
                        <form method="POST" action="{{ route('app.prospects.messages.store', [$project, $prospect]) }}" class="stack">
                            @csrf
                            <input type="hidden" name="channel" value="{{ $prospect->preferred_channel }}">
                            <input type="hidden" name="objective" value="{{ $objective->value }}">
                            <textarea name="content" rows="3" minlength="20" required
                                maxlength="{{ \App\Support\Messaging\MessageChannel::from($prospect->preferred_channel)->maxLength() }}"
                                aria-label="{{ __('رسالة :name', ['name' => $prospect->name]) }}"></textarea>
                            <button type="submit" class="btn btn--ghost btn--sm">احفظ رسالته</button>
                        </form>
                    </details>

                    <div class="studio-actions">
                        <form method="POST" action="{{ route('app.prospects.update', [$project, $prospect]) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="won">
                            <button type="submit" class="btn btn--ghost btn--sm">صار عميلًا</button>
                        </form>
                        <form method="POST" action="{{ route('app.prospects.update', [$project, $prospect]) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="archived">
                            <button type="submit" class="btn btn--ghost btn--sm">أرشفه</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </section>

        <script>
            document.querySelectorAll('[data-copy-message]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var source = document.getElementById(button.dataset.copyMessage);

                    if (! source) {
                        return;
                    }

                    navigator.clipboard.writeText(source.textContent.trim()).then(function () {
                        var original = button.textContent;
                        button.textContent = @js(__('نُسخت'));
                        setTimeout(function () { button.textContent = original; }, 2000);
                    });
                });
            });

            document.querySelectorAll('form').forEach(function (form) {
                form.addEventListener('submit', function () {
                    form.querySelectorAll('[data-once]').forEach(function (button) {
                        button.disabled = true;
                        button.textContent = @js(__('جارٍ التوليد…'));
                    });
                });
            });
        </script>
    @endif
@endsection
