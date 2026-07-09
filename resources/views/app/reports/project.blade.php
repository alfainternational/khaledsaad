@extends('layouts.app', ['title' => 'التقرير الشامل', 'pageTitle' => 'التقرير الاستراتيجي الشامل', 'pageKicker' => $project->name])

@section('content')
<div class="report-shell">

    <div class="report-toolbar no-print">
        <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">طباعة / حفظ PDF</button>
    </div>

    {{-- رأس: المؤشرات --}}
    <section class="report-summary-grid">
        <div class="report-stat">
            <span class="report-stat-label">اكتمال المراحل</span>
            <strong class="report-stat-value">{{ $report['completion'] }}%</strong>
        </div>
        <div class="report-stat">
            <span class="report-stat-label">أدوات منجَزة</span>
            <strong class="report-stat-value">{{ $report['tools_completed'] }}</strong>
        </div>
        <div class="report-stat">
            <span class="report-stat-label">متوسط الجودة</span>
            <strong class="report-stat-value">{{ $report['avg_quality'] }}%</strong>
        </div>
        @if (! empty($report['client']))
            <div class="report-stat">
                <span class="report-stat-label">العميل</span>
                <strong class="report-stat-value">{{ $report['client'] }}</strong>
            </div>
        @endif
    </section>

    {{-- الملخص التنفيذي --}}
    <section class="report-block">
        <h2 class="report-h2">الملخص التنفيذي</h2>
        <p class="report-prose">{{ $report['executive_summary'] }}</p>
    </section>

    {{-- الأولويات --}}
    @if (! empty($report['priorities']))
    <section class="report-block">
        <h2 class="report-h2">أهم الأولويات</h2>
        <ol class="report-ol">
            @foreach ($report['priorities'] as $p)
                <li>{{ $p }}</li>
            @endforeach
        </ol>
    </section>
    @endif

    {{-- الخطة الموحّدة 7/30/90 --}}
    @php $plan = $report['plan'] ?? []; @endphp
    @if (! empty($plan['quick_wins_7']) || ! empty($plan['improvements_30']) || ! empty($plan['strategic_90']))
    <section class="report-block">
        <h2 class="report-h2">الخطة التنفيذية الموحّدة</h2>
        <div class="report-plan-grid">
            <article class="report-plan-col">
                <h3 class="report-h3">خلال 7 أيام</h3>
                <ul class="report-ul">@forelse ($plan['quick_wins_7'] ?? [] as $a)<li>{{ $a }}</li>@empty<li class="report-muted">—</li>@endforelse</ul>
            </article>
            <article class="report-plan-col">
                <h3 class="report-h3">خلال 30 يوماً</h3>
                <ul class="report-ul">@forelse ($plan['improvements_30'] ?? [] as $a)<li>{{ $a }}</li>@empty<li class="report-muted">—</li>@endforelse</ul>
            </article>
            <article class="report-plan-col">
                <h3 class="report-h3">خلال 90 يوماً</h3>
                <ul class="report-ul">@forelse ($plan['strategic_90'] ?? [] as $a)<li>{{ $a }}</li>@empty<li class="report-muted">—</li>@endforelse</ul>
            </article>
        </div>
    </section>
    @endif

    {{-- الأقسام حسب المرحلة --}}
    <section class="report-block">
        <h2 class="report-h2">التحليل حسب المراحل</h2>
        @foreach ($report['stages'] as $stage)
            <article class="report-stage">
                <h3 class="report-h3">{{ $stage['label'] }}</h3>
                @if (! empty($stage['items']))
                    @foreach ($stage['items'] as $item)
                        <div class="report-tool">
                            <strong class="report-tool-title">{{ $item['tool_name'] }}</strong>
                            <span class="app-badge {{ $item['score'] >= 75 ? 'app-badge-success' : ($item['score'] >= 45 ? 'app-badge-warning' : 'app-badge-danger') }}">{{ $item['score'] }}%</span>
                            <p class="report-tool-headline">{{ $item['headline'] }}</p>
                            @if (! empty($item['points']))
                                <ul class="report-ul">@foreach ($item['points'] as $pt)<li>{{ $pt }}</li>@endforeach</ul>
                            @endif
                        </div>
                    @endforeach
                @else
                    <p class="report-muted">لم تُنجَز أدوات في هذه المرحلة بعد.</p>
                @endif
                @if (! empty($stage['missing']))
                    <p class="report-gap">ينقص: {{ implode('، ', $stage['missing']) }}</p>
                @endif
            </article>
        @endforeach
    </section>

    {{-- التشخيص الفني من التدقيق الذكي --}}
    @php $audit = $report['audit'] ?? null; @endphp
    @if (! empty($audit) && (! empty($audit['top_problems']) || ! empty($audit['executive_score'])))
    <section class="report-block">
        <h2 class="report-h2">التشخيص الفني (من التدقيق الذكي)</h2>
        @if (! empty($audit['executive_score']))
            <p class="report-prose">الدرجة التنفيذية للتدقيق: <strong>{{ $audit['executive_score'] }}%</strong>@if(!empty($audit['completed_at'])) · بتاريخ {{ $audit['completed_at'] }}@endif</p>
        @endif
        @if (! empty($audit['top_problems']))
            <h3 class="report-h3">أبرز المشاكل</h3>
            <ul class="report-ul">@foreach ($audit['top_problems'] as $pr)<li>{{ $pr }}</li>@endforeach</ul>
        @endif
        @if (! empty($audit['quick_wins_7']) || ! empty($audit['improvements_30']) || ! empty($audit['strategic_90']))
            <div class="report-plan-grid mt-2">
                <article class="report-plan-col"><h3 class="report-h3">7 أيام</h3><ul class="report-ul">@forelse($audit['quick_wins_7'] ?? [] as $a)<li>{{ $a }}</li>@empty<li class="report-muted">—</li>@endforelse</ul></article>
                <article class="report-plan-col"><h3 class="report-h3">30 يوماً</h3><ul class="report-ul">@forelse($audit['improvements_30'] ?? [] as $a)<li>{{ $a }}</li>@empty<li class="report-muted">—</li>@endforelse</ul></article>
                <article class="report-plan-col"><h3 class="report-h3">90 يوماً</h3><ul class="report-ul">@forelse($audit['strategic_90'] ?? [] as $a)<li>{{ $a }}</li>@empty<li class="report-muted">—</li>@endforelse</ul></article>
            </div>
        @endif
    </section>
    @endif

    {{-- الفجوات --}}
    @if (! empty($report['gaps']))
    <section class="report-block">
        <h2 class="report-h2">فجوات تكتمل بها الصورة</h2>
        <ul class="report-ul">@foreach ($report['gaps'] as $g)<li>{{ $g }}</li>@endforeach</ul>
    </section>
    @endif

    <p class="report-muted report-source">مصدر التركيب: {{ ($report['synthesis_source'] ?? 'local') === 'llm' ? 'تحليل ذكي مبني على مخرجاتك' : 'تحليل محلي' }}</p>
</div>
@endsection
