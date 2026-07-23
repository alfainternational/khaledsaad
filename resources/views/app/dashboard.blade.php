@extends('layouts.app')

@section('title', 'مشاريعك')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">مكانك</p>
            <h1>أين وصلت مشاريعك؟</h1>
        </div>
        <a href="{{ route('app.projects.create') }}" class="btn btn--primary">مشروع جديد</a>
    </header>

    <section class="stat-row" aria-label="ملخص">
        <article class="stat">
            <span class="stat__value">{{ count($projects) }}</span>
            <span class="stat__label">مشروع</span>
        </article>
        <article class="stat">
            <span class="stat__value">{{ $reports_count }}</span>
            <span class="stat__label">نتيجة جاهزة</span>
        </article>
        <article class="stat">
            <span class="stat__value">{{ $open_tasks }}</span>
            <span class="stat__label">مهمة تنتظرك</span>
        </article>
    </section>

    @if (($unfinished ?? []) !== [])
        {{-- طريق العودة. وعدنا المستخدم بأنه يقدر يغلق الصفحة ويرجع،
             وهذا هو المكان الذي يرجع منه. --}}
        <section aria-labelledby="resume-heading" class="resume-section">
            <h2 id="resume-heading" class="section-title">أكمل ما بدأته</h2>

            <ul class="list">
                @foreach ($unfinished as $item)
                    <li class="list__item resume-item">
                        <div class="resume-item__body">
                            <strong>{{ $item['tool_title'] }}</strong>
                            <span class="muted">{{ $item['project_name'] }} · {{ $item['hint'] }}</span>

                            @if ($item['state'] === 'draft' && $item['percent'] > 0)
                                <div class="progress__bar progress__bar--slim">
                                    <span style="inline-size: {{ $item['percent'] }}%"></span>
                                </div>
                            @endif
                        </div>

                        <a href="{{ $item['url'] }}" class="btn btn--primary btn--sm">{{ $item['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($projects === [])
        <section class="empty">
            <h2>ما عرّفتنا على مشروعك بعد</h2>
            <p>عرّفنا على مشروعك مرة واحدة، وبعدها كل خطوة تقرأ منه ولا تسألك من جديد.</p>
            <a href="{{ route('app.projects.create') }}" class="btn btn--primary">عرّفنا على مشروعك</a>
        </section>
    @else
        <section aria-labelledby="projects-heading">
            <h2 id="projects-heading" class="section-title">مشاريعك</h2>
            <div class="card-grid">
                @foreach ($projects as $project)
                    <a class="card card--link" href="{{ route('app.projects.show', $project['slug']) }}">
                        <h3>{{ $project['name'] }}</h3>
                        @if ($project['latest_score'] !== null)
                            <p class="score-chip">{{ $project['latest_score'] }}/100 · {{ $project['score_band'] }}</p>
                        @else
                            <p class="muted">ما بدأنا فيه بعد</p>
                        @endif
                        <p class="muted">{{ $project['industry'] ?? 'قطاع غير محدد' }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section aria-labelledby="tools-heading">
        <h2 id="tools-heading" class="section-title">ابدأ من هنا</h2>
        <div class="card-grid">
            @foreach ($suggested_tools as $tool)
                <a class="card card--link" href="{{ route('app.tools.show', $tool['key']) }}">
                    <p class="eyebrow">{{ $tool['category'] }}</p>
                    <h3>{{ $tool['title'] }}</h3>
                    <p class="muted">{{ $tool['promise'] ?: $tool['description'] }}</p>
                </a>
            @endforeach
        </div>
    </section>
@endsection
