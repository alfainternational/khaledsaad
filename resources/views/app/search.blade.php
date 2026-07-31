@extends('layouts.app')
@section('layout', 'index')

@section('title', 'البحث')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">البحث</p>
            <h1>ابحث في مشاريعك وتقاريرك ومهامك</h1>
        </div>
    </header>

    <form method="GET" action="{{ route('app.search') }}" role="search" class="search-form">
        <label class="sr-only" for="global-search">كلمة البحث</label>
        <input id="global-search" type="search" name="q" value="{{ $term }}"
            placeholder="اسم مشروع، عنوان تقرير، مهمة، أو تشخيص…" autofocus>
        <button type="submit" class="btn btn--primary">ابحث</button>
    </form>

    @if ($results === null)
        <p class="muted">اكتب ما تبحث عنه — النتائج من مشاريعك أنت فقط.</p>
    @else
        @php($total = $results['projects']->count() + $results['reports']->count() + $results['tasks']->count() + $results['tools']->count())

        @if ($total === 0)
            <section class="empty">
                <h2>لا نتائج عن «{{ $term }}»</h2>
                <p>جرّب كلمة أقصر أو جزءًا من الاسم.</p>
            </section>
        @else
            @if ($results['projects']->isNotEmpty())
                <section aria-labelledby="search-projects">
                    <h2 id="search-projects" class="section-title">المشاريع ({{ $results['projects']->count() }})</h2>
                    <ul class="search-results">
                        @foreach ($results['projects'] as $project)
                            <li>
                                <a href="{{ route('app.projects.show', $project->slug) }}">{{ $project->name }}</a>
                                @if ($project->industry)<span class="muted">— {{ $project->industry }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($results['reports']->isNotEmpty())
                <section aria-labelledby="search-reports">
                    <h2 id="search-reports" class="section-title">التقارير ({{ $results['reports']->count() }})</h2>
                    <ul class="search-results">
                        @foreach ($results['reports'] as $report)
                            <li>
                                <a href="{{ route('app.reports.show', $report->id) }}">{{ $report->title }}</a>
                                <span class="muted">— {{ $report->score }} من 100 · {{ $report->created_at->translatedFormat('j F Y') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($results['tasks']->isNotEmpty())
                <section aria-labelledby="search-tasks">
                    <h2 id="search-tasks" class="section-title">المهام ({{ $results['tasks']->count() }})</h2>
                    <ul class="search-results">
                        @foreach ($results['tasks'] as $task)
                            <li>
                                <a href="{{ route('app.projects.tasks', $task->project->slug) }}">{{ $task->title }}</a>
                                <span class="muted">— {{ $task->project->name }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($results['tools']->isNotEmpty())
                <section aria-labelledby="search-tools">
                    <h2 id="search-tools" class="section-title">التشخيصات ({{ $results['tools']->count() }})</h2>
                    <ul class="search-results">
                        @foreach ($results['tools'] as $tool)
                            <li><a href="{{ route('app.tools.index') }}">{{ $tool->title }}</a></li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endif
    @endif
@endsection
