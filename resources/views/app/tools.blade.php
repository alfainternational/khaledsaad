@extends('layouts.app', ['title' => 'الأدوات', 'pageTitle' => 'الأدوات', 'pageKicker' => ''])

@php
    $groupedTools = $tools->groupBy('stage');
    $currentStage = $journeySnapshot['current_stage'] ?? $currentProject?->stage;
    $recommendedNowCount = $tools
        ->filter(fn ($tool) => $tool->unlocked && ! $tool->completed_in_current_project && (int) $tool->stage === (int) $currentStage)
        ->count();
    $inProgressCount = $tools
        ->filter(fn ($tool) => $tool->unlocked && ! $tool->completed_in_current_project && $tool->current_project_runs > 0)
        ->count();
    $completedCount = $tools->where('completed_in_current_project', true)->count();
    $lockedCount = $tools->where('unlocked', false)->count();
@endphp

@section('content')
@if ($lockedCount > 0)
    <details class="card mb-6 tool-stage-access-notice p-4">
        <summary class="cursor-pointer heading-sm mb-0 list-none py-1 [&::-webkit-details-marker]:hidden">
            لماذا يوجد أدوات مقفلة؟
        </summary>
        <div class="mt-3 text-body text-secondary border-t border-[var(--border)] pt-3">
            <p class="mb-3">
                <strong>الخطّة</strong> هي التي تفتح مراحل العمل. إذا لاحظت أداة مقفلة، غالباً تحتاج ترقية أو تغيير الخطة.
                <a href="{{ route('account.index') }}" class="text-primary underline-offset-2 hover:underline">مراجعة الاشتراك</a>
            </p>
            <ul class="text-sm space-y-1" style="padding-inline-start: 1.25rem;">
                <li><span class="text-secondary">خطتك:</span> {{ $planDisplayName ?? 'غير محددة' }}</li>
                <li>
                    <span class="text-secondary">مراحل متاحة:</span>
                    @foreach ($unlockedStages as $s)
                        {{ \App\Support\Dashboard\StageCatalog::label((int) $s) }}@if (! $loop->last) · @endif
                    @endforeach
                </li>
                @if (! empty($lockedStages))
                    <li>
                        <span class="text-secondary">تحتاج خطّة أعلى:</span>
                        @foreach ($lockedStages as $s)
                            {{ \App\Support\Dashboard\StageCatalog::label((int) $s) }}@if (! $loop->last) · @endif
                        @endforeach
                    </li>
                @endif
            </ul>
        </div>
    </details>
@endif

<section class="card mb-8" data-tool-library>
    <div class="tool-ui-library-controls">
        <div class="app-field mb-0">
            <input type="search" class="app-input" placeholder="ابحث عن أداة…" data-tool-search aria-label="بحث في الأدوات">
        </div>
        <div class="tool-ui-library-filters">
            <button type="button" class="btn btn-secondary btn-sm is-active" data-tool-filter="all">الكل</button>
            @if ($recommendedNowCount)
                <button type="button" class="btn btn-ghost btn-sm" data-tool-filter="recommended">موصى بها ({{ $recommendedNowCount }})</button>
            @endif
            @if ($inProgressCount)
                <button type="button" class="btn btn-ghost btn-sm" data-tool-filter="in-progress">قيد العمل ({{ $inProgressCount }})</button>
            @endif
            @if ($completedCount)
                <button type="button" class="btn btn-ghost btn-sm" data-tool-filter="completed">مكتملة ({{ $completedCount }})</button>
            @endif
            @if ($lockedCount)
                <button type="button" class="btn btn-ghost btn-sm" data-tool-filter="locked">مغلقة ({{ $lockedCount }})</button>
            @endif
        </div>
    </div>
</section>

@foreach ($groupedTools as $stage => $stageTools)
    <x-app.card title="{{ \App\Support\Dashboard\StageCatalog::label((int) $stage) }}" class="mb-8">
        <div class="app-card-grid">
            @foreach ($stageTools as $tool)
                @php
                    $state = 'in-progress';
                    if (! $tool->unlocked) {
                        $state = 'locked';
                    } elseif ($tool->completed_in_current_project) {
                        $state = 'completed';
                    } elseif ((int) $tool->stage === (int) $currentStage && $tool->current_project_runs === 0) {
                        $state = 'recommended';
                    }
                @endphp
                <x-app.tool-card
                    :name="$tool->name ?: $tool->code"
                    :description="$tool->description ?: ''"
                    :status="$state"
                    :action-text="$tool->completed_in_current_project ? 'عرض النتيجة' : 'فتح'"
                    :href="route('tools.show', $tool)"
                    :latest-summary="$tool->latest_current_project_summary"
                />
            @endforeach
        </div>
    </x-app.card>
@endforeach
@endsection
