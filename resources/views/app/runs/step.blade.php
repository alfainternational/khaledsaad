@extends('layouts.app')

@section('title', $run['tool']['title'])

@section('content')
    @php
        // ما نعرفه من قبل يُعرض مطويًا، والأسئلة الجديدة تظهر أولًا:
        // المستخدم لا يعيد كتابة ما كتبه في أداة سابقة.
        $known = array_values(array_filter($step['fields'], fn ($field) => ! empty($field['is_known'])));
        $fresh = array_values(array_filter($step['fields'], fn ($field) => empty($field['is_known'])));
    @endphp

    <header class="page-head">
        <div>
            <p class="eyebrow">{{ $run['tool']['title'] }} · {{ $run['project']['name'] }}</p>
            <h1>{{ $step['title'] }}</h1>
        </div>
    </header>

    <div class="progress" role="group" aria-label="إلى أين وصلت">
        <div class="progress__bar">
            <span style="inline-size: {{ (int) round($position / max(1, $total_steps) * 100) }}%"></span>
        </div>
        <p class="muted">الخطوة {{ $position }} من {{ $total_steps }} · إجاباتك تُحفظ تلقائيًا بعد كل خطوة</p>
    </div>

    <div class="wizard-live-layout">
        <form id="tool-run-form" method="POST" action="{{ route('app.runs.step.save', [$run['uuid'], $step_number]) }}" class="form form--wide">
            @csrf

            @if ($known !== [])
                <details class="known-block" data-reveal-invalid>
                    <summary>
                        <b>{{ count($known) }}</b>
                        @if (count($known) === 1)
                            إجابة نعرفها عنك من قبل — لن نسألك عنها مرة أخرى
                        @else
                            إجابات نعرفها عنك من قبل — لن نسألك عنها مرة أخرى
                        @endif
                        <span>افتحها إن أردت تعديلها</span>
                    </summary>

                    <div class="known-block__body">
                        @foreach ($known as $field)
                            @include('app.runs.partials.field', ['field' => $field])
                        @endforeach
                    </div>
                </details>
            @endif

            @foreach ($fresh as $field)
                @include('app.runs.partials.field', ['field' => $field])
            @endforeach

            @if ($fresh === [])
                <p class="alert alert--info" role="status">
                    كل أسئلة هذه الخطوة إجاباتها موجودة عندنا. راجعها فوق أو أكمل مباشرة.
                </p>
            @endif

            <div class="form__actions">
                @if ($previous_step !== null)
                    <a href="{{ route('app.runs.step', [$run['uuid'], $previous_step]) }}" class="btn btn--ghost">السابق</a>
                @endif
                <button type="submit" class="btn btn--primary">
                    {{ $next_step === null ? 'راجع إجاباتك' : 'التالي' }}
                </button>
            </div>
        </form>

        <aside
            id="live-insights"
            class="live-insights"
            aria-live="polite"
            data-url="{{ route('app.runs.insights', $run['uuid']) }}"
            data-step="{{ $step_number }}"
            data-ai="{{ $previous_step !== null ? '1' : '0' }}"
        >
            <article class="card live-insights__card">
                <p class="eyebrow">جاهزية بياناتك الآن</p>
                <div class="live-insights__metrics">
                    <p><strong id="insight-completeness">{{ $run['insights']['summary']['completeness_percent'] }}%</strong><span>اكتمال الأداة</span></p>
                    <p><strong id="insight-agency">{{ $run['insights']['summary']['agency_readiness_percent'] }}%</strong><span>جاهزية الوكالة</span></p>
                </div>
                <p id="insight-agency-label" class="muted">{{ $run['insights']['summary']['agency_readiness_label'] }}</p>
            </article>

            <article id="insight-signals-card" class="card live-insights__card" @if ($run['insights']['signals'] === []) hidden @endif>
                <p class="eyebrow">ما نلاحظه لحظيًا</p>
                <div id="insight-signals" class="live-insights__signals">
                    @foreach ($run['insights']['signals'] as $signal)
                        <div>
                            <strong>{{ $signal['title'] }}</strong>
                            <p>{{ $signal['description'] }}</p>
                            <small>{{ $signal['basis'] }}</small>
                        </div>
                    @endforeach
                </div>
            </article>

            <article id="insight-ai-card" class="card live-insights__card live-insights__card--ai" hidden>
                <p class="eyebrow">مؤشر أولي</p>
                <p id="insight-ai-meaning"></p>
                <p id="insight-ai-opportunity" class="muted"></p>
                <strong id="insight-ai-recommendation"></strong>
                <p id="insight-ai-question" class="live-insights__question"></p>
            </article>

            <small id="insight-status" class="muted">يتحدث تلقائيًا أثناء الكتابة، ولا يغيّر إجاباتك.</small>
        </aside>
    </div>
@endsection

@push('scripts')
    <script>
        // حقل مطلوب داخل صندوق مطويّ يمنع الإرسال بلا رسالة: نفتح الصندوق
        // قبل أن يحاول المتصفح إظهار الخطأ، فيرى المستخدم سبب التوقف.
        document.addEventListener('invalid', function (event) {
            var box = event.target.closest('[data-reveal-invalid]');

            if (box && !box.open) {
                box.open = true;
                event.target.focus({ preventScroll: false });
            }
        }, true);

        (function () {
            var form = document.getElementById('tool-run-form');
            var panel = document.getElementById('live-insights');

            if (!form || !panel) return;

            var timer;
            var status = document.getElementById('insight-status');
            var token = form.querySelector('input[name="_token"]').value;

            function answers() {
                var result = {};

                new FormData(form).forEach(function (value, rawKey) {
                    if (rawKey.charAt(0) === '_') return;

                    if (rawKey.endsWith('[]')) {
                        var key = rawKey.slice(0, -2);
                        result[key] = result[key] || [];
                        result[key].push(value);
                    } else {
                        result[rawKey] = value;
                    }
                });

                return result;
            }

            function text(id, value) {
                var element = document.getElementById(id);
                if (element) element.textContent = value || '';
            }

            function render(data) {
                text('insight-completeness', data.summary.completeness_percent + '%');
                text('insight-agency', data.summary.agency_readiness_percent + '%');
                text('insight-agency-label', data.summary.agency_readiness_label);

                var signalsCard = document.getElementById('insight-signals-card');
                var signals = document.getElementById('insight-signals');
                signals.replaceChildren();
                signalsCard.hidden = data.signals.length === 0;

                data.signals.forEach(function (signal) {
                    var box = document.createElement('div');
                    var title = document.createElement('strong');
                    var description = document.createElement('p');
                    var basis = document.createElement('small');
                    title.textContent = signal.title;
                    description.textContent = signal.description;
                    basis.textContent = signal.basis;
                    box.append(title, description, basis);
                    signals.appendChild(box);
                });

                var aiCard = document.getElementById('insight-ai-card');
                aiCard.hidden = data.preliminary.status !== 'ready';
                text('insight-ai-meaning', data.preliminary.meaning);
                text('insight-ai-opportunity', data.preliminary.risk_or_opportunity);
                text('insight-ai-recommendation', data.preliminary.recommendation);
                text('insight-ai-question', data.preliminary.deepen_question);
                status.textContent = 'مؤشرات لحظية — لا تغيّر إجاباتك ولا درجتك.';
            }

            function request(includeAi) {
                status.textContent = includeAi ? 'نقرأ الخطوة المكتملة…' : 'نحدّث المؤشرات…';

                fetch(panel.dataset.url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify({
                        answers: answers(),
                        include_ai: includeAi,
                        step: Number(panel.dataset.step)
                    })
                })
                    .then(function (response) {
                        if (!response.ok) throw new Error('insight unavailable');
                        return response.json();
                    })
                    .then(function (payload) { render(payload.data); })
                    .catch(function () {
                        status.textContent = 'تعذّر تحديث التوجيه الآن؛ يمكنك مواصلة تعبئة الأداة بصورة طبيعية.';
                    });
            }

            form.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () { request(false); }, 450);
            });
            form.addEventListener('change', function () {
                clearTimeout(timer);
                timer = setTimeout(function () { request(false); }, 150);
            });

            request(panel.dataset.ai === '1');
        })();
    </script>
@endpush
