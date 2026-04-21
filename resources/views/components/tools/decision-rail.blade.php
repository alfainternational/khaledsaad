@props([
    'tool',
    'stageLabel',
    'blueprint',
    'projectModeContext' => [],
    'currentProject' => null,
])

@php
    $outcome = trim((string) ($blueprint['outcome'] ?? ''));
    $outcomeShort = \Illuminate\Support\Str::limit($outcome, 180, '…');
    $hasContextBlocks = ! empty($blueprint['why']) || ! empty($blueprint['when']) || ! empty($blueprint['ai_role']);
@endphp

<header class="tool-ui-header mb-4">
    <div>
        <h2 class="heading-lg">{{ $tool->name ?: $tool->code }}</h2>
        @if ($outcomeShort !== '')
            <p class="text-body mt-1">{{ $outcomeShort }}</p>
        @endif
    </div>
    <div class="tool-ui-header-meta">
        <span class="app-badge">{{ $stageLabel }}</span>
        @if ($currentProject)
            <span class="app-badge">{{ $currentProject->name }}</span>
        @endif
        @if (($projectModeContext['run_count'] ?? 0) > 0)
            <span class="app-badge">{{ $projectModeContext['latest_completeness'] ?? 0 }}%</span>
        @endif
    </div>
</header>

@if ($hasContextBlocks)
    <details class="tool-context-card mb-6 group">
        <summary class="cursor-pointer text-sm font-medium text-secondary py-2 list-none flex items-center gap-2 [&::-webkit-details-marker]:hidden">
            <span class="group-open:rotate-90 transition-transform inline-block text-xs" aria-hidden="true">›</span>
            تفاصيل إضافية (لماذا ومتى؟)
        </summary>
        <div class="tool-context-grid border-t border-[var(--border)] pt-4 mt-1">
            @if (! empty($blueprint['why']))
                <div class="tool-context-item">
                    <strong>الفكرة</strong>
                    <p>{{ $blueprint['why'] }}</p>
                </div>
            @endif
            @if (! empty($blueprint['when']))
                <div class="tool-context-item">
                    <strong>وقت الاستخدام</strong>
                    <p>{{ $blueprint['when'] }}</p>
                </div>
            @endif
            @if (! empty($blueprint['ai_role']))
                <div class="tool-context-item">
                    <strong>دور المساعد الذكي</strong>
                    <p>{{ $blueprint['ai_role'] }}</p>
                </div>
            @endif
        </div>
    </details>
@endif
