@extends('layouts.app')
@section('layout', 'wizard')

@section('title', 'جارٍ التحليل')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">{{ $run['tool']['title'] }} · {{ $run['project']['name'] }}</p>
            <h1 id="run-status-title">{{ $run['status_label'] }}</h1>
            @unless ($run['failure'] ?? null)
                <p class="muted">
                    إجاباتك محفوظة. يمكنك إغلاق الصفحة الآن —
                    ستجد هذا التحليل في قسم «أكمل ما بدأته» داخل لوحة التحكم، ويصلك إشعار عند اكتماله.
                </p>
            @endunless
        </div>

        {{-- الوعد بالعودة يحتاج طريقًا فعليًا، لا طمأنة فقط. --}}
        <div class="page-head__actions">
            <a href="{{ route('app.dashboard') }}" class="btn btn--ghost">لوحة التحكم</a>
            <a href="{{ route('app.projects.show', $run['project']['slug']) }}" class="btn btn--ghost">
                {{ $run['project']['name'] }}
            </a>
        </div>
    </header>

    @php($failure = $run['failure'] ?? null)

    {{-- شريط تقدّم فوق رسالة فشل يجعل الشاشة تناقض نفسها: «بقيت أسئلة
         قليلة» أعلى «تعذّر التحليل». التقدّم يُخفى عند الفشل، ولا يُترك
         ليقول للمستخدم إن شيئًا لا يزال يجري. --}}
    @unless ($failure)
        <div class="progress">
            <div class="progress__bar">
                <span id="run-progress-bar" style="inline-size: {{ $run['progress_percent'] }}%"></span>
            </div>
            <p class="muted"><span id="run-progress-value">{{ $run['progress_percent'] }}</span>% مكتمل</p>
        </div>
    @endunless

    <ol class="stages" id="run-stages"
        data-progress-url="{{ route('app.runs.progress', $run['uuid']) }}"
        data-terminal="{{ $run['is_terminal'] ? '1' : '0' }}">
        @foreach ($run['stages'] as $stage)
            <li class="stage stage--{{ $stage['status'] }}" data-stage="{{ $stage['key'] }}">
                <span class="stage__label">{{ $stage['label'] }}</span>
                <span class="stage__status">{{ $stage['status_label'] }}</span>
                @if ($stage['has_error'] ?? false)
                    <span class="stage__error">{{ __('تعذّرت هذه المرحلة') }}</span>
                @endif
            </li>
        @endforeach
    </ol>

    @if ($failure)
        <x-ui.error-state
            :title="$failure['title']"
            :message="$failure['message']"
            :kind="$failure['kind']"
            {{-- المؤجَّل لا يُعرض معه زرّ إعادة: نحن نعيده تلقائيًّا، وزرٌّ
                 يطلب منه أن يفعل ما نفعله نحن يوحي بأن الأمر بيده. --}}
            :retry="($failure['is_waiting'] ?? false) ? null : route('app.runs.retry', $run['uuid'])">
            {{-- الإجراء الوحيد يظهر للحدّ الذي يملك المستخدم رفعه فقط. --}}
            @if ($failure['billing_action'])
                <a href="{{ route('app.billing') }}" class="btn btn--ghost">{{ __('الاشتراك والفوترة') }}</a>
            @endif
            <a href="{{ route('app.projects.show', $run['project']['slug']) }}" class="btn btn--ghost">
                {{ __('تقاريري السابقة') }}
            </a>
        </x-ui.error-state>
    @elseif ($run['is_stale'] ?? false)
        <x-ui.error-state
            :title="__('التحليل لم يتقدّم منذ فترة')"
            :message="$run['stale_hint']"
            kind="ours"
            :retry="route('app.runs.retry', $run['uuid'])" />
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
                pending: @js(__('بانتظار الدور')),
                running: @js(__('جارية')),
                completed: @js(__('اكتملت')),
                failed: @js(__('تعذرت')),
            };

            const poll = async () => {
                try {
                    const response = await fetch(container.dataset.progressUrl, {
                        headers: { Accept: 'application/json' },
                    });
                    if (!response.ok) return;

                    const { data } = await response.json();

                    document.getElementById('run-status-title').textContent = data.status_label;

                    // شريط التقدّم غائب من الصفحة عند الفشل — الكتابة فيه
                    // بلا فحص كانت سترمي وتوقف الاستطلاع بصمت.
                    const bar = document.getElementById('run-progress-bar');
                    const value = document.getElementById('run-progress-value');
                    if (bar) bar.style.inlineSize = data.progress_percent + '%';
                    if (value) value.textContent = data.progress_percent;

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
