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
                    {{-- المصدر نفسه الذي تقرأ منه الرئيسية: لا يجوز أن تعرض
                         الشاشتان رقمين مختلفين لمشروع واحد (A3، INV-2). --}}
                    @if ($project['headline_score'])
                        <p class="score-chip">
                            {{ \App\Support\Presentation\Num::score($project['headline_score']['value']) }}
                            · {{ $project['headline_score']['name'] }}
                        </p>
                        <p class="muted">
                            {{ $project['headline_score']['basis'] }}
                            @if ($project['headline_score']['is_assumption'])
                                · <span class="tag tag--assumption">{{ __('فرضية') }}</span>
                            @endif
                        </p>
                    @else
                        <p class="muted">{{ __('لم يُشخَّص بعد') }}</p>
                    @endif
                    <p class="muted">{{ $project['sector_display'] }}</p>
                </a>
            @endforeach
        </div>
    @endif
@endsection
