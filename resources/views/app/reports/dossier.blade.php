@extends('layouts.app', ['title' => 'دليل المشروع', 'pageTitle' => 'دليل المشروع — الإجابات الخام', 'pageKicker' => $project->name])

@section('content')
<div class="report-shell">

    <div class="report-toolbar no-print">
        <a href="{{ route('projects.report', $project) }}" class="btn btn-secondary btn-sm">التقرير التحليلي</a>
        @if (entitlement('outputs.can_export'))
            <a href="{{ route('projects.dossier.pdf', $project) }}" class="btn btn-primary btn-sm">تصدير الدليل PDF</a>
        @endif
        <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">طباعة</button>
    </div>

    <p class="report-muted">
        هذا الدليل يجمع كل ما أدخلته في أدوات المشروع في وثيقة واحدة مرتّبة حسب المراحل — كما هي، بلا تحليل.
        استخدمه كمرجع، أو سلّمه، أو اطبعه. للتحليل الاستراتيجي الكامل انتقل إلى «التقرير التحليلي».
    </p>

    {{-- المؤشرات --}}
    <section class="report-summary-grid">
        <div class="report-stat">
            <span class="report-stat-label">اكتمال المراحل</span>
            <strong class="report-stat-value">{{ $dossier['meta']['completion'] }}%</strong>
        </div>
        <div class="report-stat">
            <span class="report-stat-label">أدوات منجَزة</span>
            <strong class="report-stat-value">{{ $dossier['meta']['tools_completed'] }}</strong>
        </div>
        <div class="report-stat">
            <span class="report-stat-label">المرحلة الحالية</span>
            <strong class="report-stat-value">{{ $dossier['meta']['stage_label'] }}</strong>
        </div>
        @if (! empty($dossier['meta']['client']))
            <div class="report-stat">
                <span class="report-stat-label">العميل</span>
                <strong class="report-stat-value">{{ $dossier['meta']['client'] }}</strong>
            </div>
        @endif
    </section>

    @if (! $dossier['has_answers'])
        <section class="report-block">
            <p class="report-prose">لم تُنجَز أي أداة بعد لهذا المشروع. ابدأ بمرحلة «اعرف وضعك» لتظهر إجاباتك هنا مجمّعة.</p>
        </section>
    @endif

    @foreach ($dossier['stages'] as $stage)
        @continue(empty($stage['tools']) && empty($stage['missing']))
        <section class="report-block">
            <h2 class="report-h2">المرحلة {{ $stage['num'] }}: {{ $stage['label'] }} <span class="report-muted">({{ $stage['completion'] }}%)</span></h2>
            <p class="report-muted">{{ $stage['description'] }}</p>

            @forelse ($stage['tools'] as $tool)
                <article class="report-tool">
                    <div class="dossier-tool-head">
                        <span class="report-tool-title">{{ $tool['name'] }}</span>
                        <span class="dossier-tool-meta">
                            @if ($tool['answered_at']){{ $tool['answered_at'] }} · @endif اكتمال {{ $tool['completeness'] }}%
                        </span>
                    </div>

                    @if ($tool['headline'] !== '')
                        <p class="report-tool-headline"><strong>الخلاصة:</strong> {{ $tool['headline'] }}</p>
                    @endif

                    @if (! empty($tool['answers']))
                        <dl class="dossier-qa">
                            @foreach ($tool['answers'] as $answer)
                                <dt class="dossier-q">{{ $answer['label'] }}</dt>
                                <dd class="dossier-a">{{ $answer['value'] }}</dd>
                            @endforeach
                        </dl>
                    @else
                        <p class="report-muted">لا توجد إجابات نصّية مسجّلة في هذه الأداة.</p>
                    @endif
                </article>
            @empty
                <p class="report-muted">لم تُنجَز أدوات في هذه المرحلة بعد.</p>
            @endforelse

            @if (! empty($stage['missing']))
                <p class="report-gap">أدوات أساسية ناقصة هنا: {{ implode('، ', $stage['missing']) }}.</p>
            @endif
        </section>
    @endforeach

</div>
@endsection
