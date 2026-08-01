@php
    // دليل التنفيذ: كيف ومتى وأين وماذا تقدّم، مع أمثلة تُنسخ.
    // جزئية واحدة تُستدعى من اللوحة ومن أي سطح يعرض مهمة، فلا تنجرف نسختان.
    $guide = $task['guide'] ?? null;
    $status = $task['guide_status'] ?? 'none';
    $facets = [
        'how' => 'كيف تُنفَّذ',
        'when' => 'متى',
        'where' => 'أين',
        'deliverable' => 'ماذا تخرج به',
    ];
@endphp

@if ($status === 'pending')
    <p class="task__guide-state muted" data-guide-pending>
        يُطوَّر دليل التنفيذ الآن. حدّث الصفحة بعد دقيقة.
    </p>
@elseif ($guide)
    <details class="task-guide">
        <summary>
            دليل التنفيذ
            {{-- الدليل المبدئي يُعلن أنه مبدئي: إخفاء الفرق بين قالب مأمون
                 وصياغة على حالة النشاط يمنح المستخدم ثقة لم تُكتسب (§٤.١). --}}
            @if ($status === 'fallback')
                <span class="badge badge--warn">مبدئي</span>
            @endif
        </summary>

        <dl class="task-guide__facets">
            @foreach ($facets as $key => $label)
                @if (! empty($guide[$key]))
                    <dt>{{ $label }}</dt>
                    <dd>{{ $guide[$key] }}</dd>
                @endif
            @endforeach
        </dl>

        @if (! empty($guide['checkpoints']))
            <p class="eyebrow">كيف تعرف أنك ماشٍ صح</p>
            <ul class="bullets">
                @foreach ($guide['checkpoints'] as $checkpoint)
                    <li>{{ $checkpoint }}</li>
                @endforeach
            </ul>
        @endif

        @if (! empty($guide['pitfalls']))
            <p class="eyebrow">أخطاء شائعة هنا</p>
            <ul class="bullets">
                @foreach ($guide['pitfalls'] as $pitfall)
                    <li>{{ $pitfall }}</li>
                @endforeach
            </ul>
        @endif

        @foreach ($guide['examples'] ?? [] as $example)
            <x-worked-example :example="$example" :open="$loop->first" />
        @endforeach

        <form method="POST" action="{{ route('app.tasks.develop', $task['id']) }}">
            @csrf
            <button type="submit" class="btn btn--ghost btn--sm">أعد تطوير الدليل</button>
        </form>
    </details>
@else
    @if ($task['worked_example'] ?? null)
        <x-worked-example :example="$task['worked_example']" />
    @endif

    <form method="POST" action="{{ route('app.tasks.develop', $task['id']) }}">
        @csrf
        <button type="submit" class="btn btn--ghost btn--sm">
            طوّر هذه المهمة: كيف ومتى وأين وأمثلة جاهزة
        </button>
    </form>
@endif
