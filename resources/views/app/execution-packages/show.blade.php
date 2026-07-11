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
                </span>
                <span class="exec-task-state">{{ $taskStatusLabels[$task->status] ?? $task->status }}</span>
                <span class="exec-task-actions">
                    @if ($task->status === 'pending')
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

<section class="studio-gen-footer mb-8">
    <a href="{{ route('projects.recommendations.index', $package->project) }}" class="btn btn-secondary">العودة للتوصيات</a>
    <form method="POST" action="{{ route('execution-packages.status', $package) }}">
        @csrf @method('PATCH')
        <input type="hidden" name="status" value="approved">
        @if ($package->status === 'proposed')
            <button type="submit" class="btn btn-primary">اعتماد الحزمة</button>
        @endif
    </form>
</section>
@endsection
