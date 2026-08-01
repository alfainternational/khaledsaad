@extends('layouts.app')
@section('layout', 'index')

@section('title', 'المشاريع')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">المشاريع</p>
            <h1>كل مشاريعك</h1>
        </div>
        <a href="{{ route('app.projects.create') }}" class="btn btn--primary">أضف مشروعًا</a>
    </header>

    @if ($projects === [])
        <section class="empty">
            <h2>لا توجد مشاريع بعد</h2>
            <p>أضف مشروعك الأول لتجميع معلوماته وتشخيصاته وتقاريره في مكان واحد.</p>
            <a href="{{ route('app.projects.create') }}" class="btn btn--primary">أضف مشروعك الأول</a>
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
                    <p class="muted">{{ $project['sector_display'] }}</p>
                </a>
            @endforeach
        </div>
    @endif
@endsection
