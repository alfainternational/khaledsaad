@extends('layouts.app')

@section('title', 'المشاريع')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">المشاريع</p>
            <h1>كل مشاريعك</h1>
        </div>
        <a href="{{ route('app.projects.create') }}" class="btn btn--primary">مشروع جديد</a>
    </header>

    @if ($projects === [])
        <section class="empty">
            <h2>القائمة فارغة</h2>
            <p>المشروع هو الذاكرة التي تتشاركها كل الأدوات — تُدخل البيانات مرة واحدة فقط.</p>
            <a href="{{ route('app.projects.create') }}" class="btn btn--primary">أنشئ مشروعًا</a>
        </section>
    @else
        <div class="card-grid">
            @foreach ($projects as $project)
                <a class="card card--link" href="{{ route('app.projects.show', $project['slug']) }}">
                    <h3>{{ $project['name'] }}</h3>
                    @if ($project['latest_score'] !== null)
                        <p class="score-chip">{{ $project['latest_score'] }}/100 · {{ $project['score_band'] }}</p>
                    @else
                        <p class="muted">لم يُشخَّص بعد</p>
                    @endif
                    <p class="muted">{{ $project['industry'] ?? 'قطاع غير محدد' }}</p>
                </a>
            @endforeach
        </div>
    @endif
@endsection
