@extends('layouts.app')
@section('layout', 'index')
@section('title', 'خطط المحتوى')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">مخرج حي مرتبط بالمشروع</p>
            <h1>خطط المحتوى</h1>
            <p class="muted">استورد ملف Word مرة واحدة، ثم أدِر التحرير والتصميم والمراجعة والنشر والقياس من مكان واحد.</p>
        </div>
    </header>

    <section class="card content-import" aria-labelledby="import-heading">
        <h2 id="import-heading" class="section-title">استيراد خطة من Word</h2>
        <form method="POST" action="{{ route('app.content-plans.import') }}" enctype="multipart/form-data" class="field-row">
            @csrf
            <label class="field">
                <span class="field__label">المشروع</span>
                <select name="project_id" required>
                    <option value="">اختر المشروع</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected((string) old('project_id') === (string) $project->id)>{{ $project->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span class="field__label">ملف الخطة DOCX</span>
                <input type="file" name="document" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required>
                <small class="field__help">حتى 5 ميجابايت. لا يختار النظام مشروعًا نيابةً عنك.</small>
            </label>
            <button type="submit" class="btn btn--primary">استورد الخطة</button>
        </form>
    </section>

    <nav class="filter-chips" aria-label="حالة الخطط">
        <a href="{{ route('app.content-plans.index') }}" @class(['filter-chip', 'is-active' => $status === 'active'])>النشطة</a>
        <a href="{{ route('app.content-plans.index', ['status' => 'archived']) }}" @class(['filter-chip', 'is-active' => $status === 'archived'])>المؤرشفة</a>
    </nav>

    @if ($plans->isEmpty())
        <section class="empty">
            <h2>{{ $status === 'active' ? 'لا توجد خطة نشطة بعد' : 'لا توجد خطط مؤرشفة' }}</h2>
            <p class="muted">اختر مشروعًا وارفع ملف Word لتتحول بطاقاته إلى لوحة تشغيلية.</p>
        </section>
    @else
        <div class="content-plan-list">
            @foreach ($plans as $plan)
                <article class="card content-plan-summary">
                    <div>
                        <p class="eyebrow">{{ $plan->project->name }} · {{ $plan->month->translatedFormat('F Y') }}</p>
                        <h2>{{ $plan->title }}</h2>
                        <p class="muted">{{ $plan->posts->count() }} منشورًا · مصدرها {{ $plan->source_filename ?: 'إدخال يدوي' }}</p>
                    </div>
                    <div class="content-plan-summary__progress">
                        <strong>{{ $plan->progressPercent() }}%</strong>
                        <span>إنجاز الخطة</span>
                    </div>
                    <a href="{{ route('app.content-plans.show', $plan) }}" class="btn btn--primary btn--sm">افتح اللوحة</a>
                </article>
            @endforeach
        </div>
    @endif
@endsection
