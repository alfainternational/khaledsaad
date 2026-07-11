@extends('layouts.app', ['title' => 'حزمة تنفيذ', 'pageTitle' => 'حزمة تنفيذ', 'pageKicker' => $package->project?->name])

@section('content')
@php
    $statusLabels = [
        'proposed' => 'مقترحة', 'in_review' => 'قيد المراجعة', 'approved' => 'معتمدة',
        'in_progress' => 'قيد التنفيذ', 'executed' => 'منفّذة', 'measuring' => 'تحت القياس',
    ];
    $taskStatusLabels = [
        'pending' => 'لم تبدأ',
        'in_progress' => 'قيد التنفيذ',
        'done' => 'منجزة',
    ];
    $packageActions = [
        'approved' => ['status' => 'in_progress', 'label' => 'بدء التنفيذ', 'style' => 'btn-primary'],
        'in_progress' => ['status' => 'executed', 'label' => 'تأكيد التنفيذ', 'style' => 'btn-primary'],
        'executed' => ['status' => 'measuring', 'label' => 'بدء القياس', 'style' => 'btn-secondary'],
    ];
    $reportPhaseLabels = [
        'discovery' => 'اكتشاف',
        'planning' => 'تخطيط',
        'execution' => 'تنفيذ',
        'validation' => 'تحقق',
    ];
    $nextPackageAction = $packageActions[$package->status] ?? null;
    $canUpdateTasks = $package->status === 'in_progress';
    $canCreateReports = in_array($package->status, ['in_progress', 'executed', 'measuring'], true);
    $allTasksDone = $package->tasks->isNotEmpty() && $package->tasks->every(fn ($task) => $task->status === 'done');
    $taskLockedLabel = in_array($package->status, ['executed', 'measuring'], true)
        ? 'مغلقة بعد تأكيد التنفيذ'
        : 'بانتظار بدء التنفيذ';
    if ($package->status === 'in_progress' && ! $allTasksDone) {
        $nextPackageAction = null;
    }
@endphp

<section class="exec-pkg-head {{ ($brand['enabled'] ?? false) ? 'exec-pkg-head--branded' : '' }}" @if($brand['enabled'] ?? false) style="--brand: {{ $brand['color'] }}" @endif>
    @if ($brand['enabled'] ?? false)
        <div class="exec-brand">
            @if (!empty($brand['logo_url']))
                <img src="{{ $brand['logo_url'] }}" alt="{{ $brand['name'] }}" class="exec-brand-logo">
            @endif
            <span class="exec-brand-name">{{ $brand['name'] }}</span>
        </div>
    @endif
    <span class="exec-pkg-status">{{ $statusLabels[$package->status] ?? $package->status }}</span>
    <h1>{{ $package->title }}</h1>
    <p>{{ $package->project?->name }}</p>
</section>

@if ($package->problem)
    <article class="exec-section">
        <h3>المشكلة</h3>
        <p>{{ $package->problem }}</p>
    </article>
@endif
@if ($package->evidence)
    <article class="exec-section">
        <h3>الدليل</h3>
        <p>{{ $package->evidence }}</p>
    </article>
@endif
@if ($package->decision)
    <article class="exec-section">
        <h3>القرار</h3>
        <p>{{ $package->decision }}</p>
    </article>
@endif

<article class="exec-section">
    <h3>المهام</h3>
    <ul class="exec-tasks">
        @foreach ($package->tasks as $task)
            <li class="exec-task">
                <span class="exec-task-dot {{ $task->status === 'done' ? 'exec-task-dot--done' : '' }}"></span>
                <span class="exec-task-body">
                    <strong>{{ $task->title }}</strong>
                    @if ($task->description)
                        <small>{{ $task->description }}</small>
                    @endif
                    @if ($task->assignee || $task->due_date)
                        <small>
                            @if ($task->assignee)
                                المسؤول: {{ $task->assignee->name }}
                            @endif
                            @if ($task->assignee && $task->due_date)
                                ·
                            @endif
                            @if ($task->due_date)
                                الاستحقاق: {{ $task->due_date->format('Y-m-d') }}
                            @endif
                        </small>
                    @endif
                </span>
                <span class="exec-task-state">{{ $taskStatusLabels[$task->status] ?? $task->status }}</span>
                <span class="exec-task-actions">
                    @if (! $canUpdateTasks)
                        <small>{{ $taskLockedLabel }}</small>
                    @elseif ($task->status === 'pending')
                        <form method="POST" action="{{ route('execution-packages.tasks.status', [$package, $task]) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="in_progress">
                            <button type="submit" class="btn btn-secondary btn-sm">بدء</button>
                        </form>
                        <form method="POST" action="{{ route('execution-packages.tasks.status', [$package, $task]) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="done">
                            <button type="submit" class="btn btn-primary btn-sm">تم التنفيذ</button>
                        </form>
                    @elseif ($task->status === 'in_progress')
                        <form method="POST" action="{{ route('execution-packages.tasks.status', [$package, $task]) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="done">
                            <button type="submit" class="btn btn-primary btn-sm">تم التنفيذ</button>
                        </form>
                        <form method="POST" action="{{ route('execution-packages.tasks.status', [$package, $task]) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="pending">
                            <button type="submit" class="btn btn-secondary btn-sm">إرجاع</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('execution-packages.tasks.status', [$package, $task]) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="pending">
                            <button type="submit" class="btn btn-secondary btn-sm">إعادة فتح</button>
                        </form>
                    @endif
                </span>
            </li>
        @endforeach
    </ul>
</article>

@if ($package->assets->isNotEmpty())
    <article class="exec-section">
        <h3>المخرجات</h3>
        <ul class="exec-tasks">
            @foreach ($package->assets as $asset)
                <li class="exec-task"><span class="exec-task-dot"></span><span>{{ $asset->title }}</span></li>
            @endforeach
        </ul>
    </article>
@endif

@if ($package->measurement_plan)
    <article class="exec-section">
        <h3>خطة القياس</h3>
        <p>{{ $package->measurement_plan }}</p>
    </article>
@endif

<article class="exec-section">
    <h3>تقارير القياس</h3>
    @if ($package->reports->isNotEmpty())
        <ul class="exec-reports">
            @foreach ($package->reports as $report)
                @php
                    $metric = collect($report->metrics_json ?? [])->first();
                    $note = $report->notes_json['summary'] ?? null;
                @endphp
                <li class="exec-report">
                    <span class="exec-report-progress">{{ $report->progress }}%</span>
                    <span class="exec-report-body">
                        <strong>{{ $reportPhaseLabels[$report->phase] ?? $report->phase }}</strong>
                        @if ($note)
                            <small>{{ $note }}</small>
                        @endif
                        @if ($metric)
                            <small>{{ $metric['name'] ?? 'مؤشر' }}: {{ $metric['value'] ?? '-' }}</small>
                        @endif
                    </span>
                    <time datetime="{{ $report->created_at?->toIso8601String() }}">{{ $report->created_at?->diffForHumans() }}</time>
                </li>
            @endforeach
        </ul>
    @else
        <p>لا توجد تقارير قياس بعد.</p>
    @endif

    @if ($canCreateReports)
        <form method="POST" action="{{ route('execution-packages.reports.store', $package) }}" class="exec-report-form">
            @csrf
            <label>
                <span>المرحلة</span>
                <select name="phase" required>
                    @foreach ($reportPhaseLabels as $phase => $label)
                        <option value="{{ $phase }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>نسبة التقدم</span>
                <input type="number" name="progress" min="0" max="100" value="0" required>
            </label>
            <label>
                <span>اسم المؤشر</span>
                <input type="text" name="metric_name" placeholder="مثلاً: العملاء المحتملون">
            </label>
            <label>
                <span>قيمة المؤشر</span>
                <input type="text" name="metric_value" placeholder="مثلاً: 34 خلال أسبوع">
            </label>
            <label class="exec-report-form-note">
                <span>ملاحظة القياس</span>
                <textarea name="note" rows="3" placeholder="ماذا تغيّر بعد التنفيذ؟"></textarea>
            </label>
            <button type="submit" class="btn btn-primary">حفظ تقرير القياس</button>
        </form>
    @else
        <p>يظهر نموذج القياس بعد بدء التنفيذ.</p>
    @endif
</article>

<section class="studio-gen-footer mb-8">
    <a href="{{ route('projects.recommendations.index', $package->project) }}" class="btn btn-secondary">العودة للتوصيات</a>
    @if ($nextPackageAction)
        <form method="POST" action="{{ route('execution-packages.status', $package) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="{{ $nextPackageAction['status'] }}">
            <button type="submit" class="btn {{ $nextPackageAction['style'] }}">{{ $nextPackageAction['label'] }}</button>
        </form>
    @elseif ($package->status === 'in_progress')
        <span class="btn btn-secondary">أكمل كل المهام قبل تأكيد التنفيذ</span>
    @endif
    @if ($package->status === 'proposed')
        <form method="POST" action="{{ route('projects.approvals.store', $package->project) }}">
            @csrf
            <input type="hidden" name="item_type" value="execution_package">
            <input type="hidden" name="item_id" value="{{ $package->id }}">
            <input type="hidden" name="note" value="مراجعة واعتماد حزمة التنفيذ: {{ $package->title }}">
            <button type="submit" class="btn btn-secondary">طلب اعتماد الحزمة</button>
        </form>
    @endif
</section>
@endsection
