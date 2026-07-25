@extends('layouts.app')

@section('title', 'جارٍ التحليل')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">{{ $run['tool']['title'] }} · {{ $run['project']['name'] }}</p>
            <h1 id="run-status-title">{{ $run['status_label'] }}</h1>
            <p class="muted">
                إجاباتك محفوظة. يمكنك إغلاق الصفحة الآن —
                ستجد هذا التحليل في «أكمل ما بدأته» داخل لوحتك، وسنكمله في الخلفية.
            </p>
        </div>

        {{-- الوعد بالعودة يحتاج طريقًا فعليًا، لا طمأنة فقط. --}}
        <div class="page-head__actions">
            <a href="{{ route('app.dashboard') }}" class="btn btn--ghost">لوحتي</a>
            <a href="{{ route('app.projects.show', $run['project']['slug']) }}" class="btn btn--ghost">
                {{ $run['project']['name'] }}
            </a>
        </div>
    </header>

    <div class="progress">
        <div class="progress__bar">
            <span id="run-progress-bar" style="inline-size: {{ $run['progress_percent'] }}%"></span>
        </div>
        <p class="muted"><span id="run-progress-value">{{ $run['progress_percent'] }}</span>% مكتمل</p>
    </div>

    <ol class="stages" id="run-stages"
        data-progress-url="{{ route('app.runs.progress', $run['uuid']) }}"
        data-terminal="{{ $run['is_terminal'] ? '1' : '0' }}">
        @foreach ($run['stages'] as $stage)
            <li class="stage stage--{{ $stage['status'] }}" data-stage="{{ $stage['key'] }}">
                <span class="stage__label">{{ $stage['label'] }}</span>
                <span class="stage__status">{{ $stage['status_label'] }}</span>
                @if ($stage['error'])
                    <span class="stage__error">{{ $stage['error'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>

    @if ($run['failure_reason'] || ($run['is_stale'] ?? false))
        <div @class(['alert', 'alert--error' => $run['failure_reason'], 'alert--info' => ! $run['failure_reason']])>
            <p>{{ $run['failure_reason'] ?: $run['stale_hint'] }}</p>
        </div>

        <form method="POST" action="{{ route('app.runs.retry', $run['uuid']) }}">
            @csrf
            <button type="submit" class="btn btn--primary">أعد المحاولة</button>
        </form>
    @endif
@endsection

@push('scripts')
    <script>
        // استطلاع كل ثلاث ثوانٍ: الاستضافة المشتركة لا تدعم WebSockets موثوقًا،
        // والقرار موثق في ADR-001.
        (function () {
            const container = document.getElementById('run-stages');
            if (!container || container.dataset.terminal === '1') return;

            const labels = {
                pending: 'بانتظار الدور',
                running: 'جارية',
                completed: 'اكتملت',
                failed: 'تعذرت',
            };

            const poll = async () => {
                try {
                    const response = await fetch(container.dataset.progressUrl, {
                        headers: { Accept: 'application/json' },
                    });
                    if (!response.ok) return;

                    const { data } = await response.json();

                    document.getElementById('run-status-title').textContent = data.status_label;
                    document.getElementById('run-progress-bar').style.inlineSize = data.progress_percent + '%';
                    document.getElementById('run-progress-value').textContent = data.progress_percent;

                    data.stages.forEach((stage) => {
                        const node = container.querySelector('[data-stage="' + stage.key + '"]');
                        if (!node) return;
                        node.className = 'stage stage--' + stage.status;
                        node.querySelector('.stage__status').textContent = labels[stage.status] ?? stage.status;
                    });

                    if (data.is_terminal) {
                        clearInterval(timer);
                        window.location.reload();
                    }
                } catch (error) {
                    // الشبكة قد تنقطع؛ المحاولة التالية تكفي دون إزعاج المستخدم.
                }
            };

            const timer = setInterval(poll, 3000);
            poll();
        })();
    </script>
@endpush
