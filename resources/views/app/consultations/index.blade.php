@extends('layouts.app')

@section('title', 'التشخيص الذكي الشامل')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">المستشار التسويقي الذكي</p>
            <h1>تشخيص شامل يقودك من الأسئلة إلى خطة تنفيذ</h1>
            <p class="muted">نحدد نطاق مشروعك، نجمع ما ينطبق عليه، ثم نبني تقريرًا موحدًا بأولويات قابلة للتنفيذ.</p>
        </div>
        <a href="{{ route('app.projects.create') }}" class="btn btn--ghost">أضف مشروعًا</a>
    </header>

    @if ($projects === [])
        <section class="empty">
            <h2>ابدأ بإضافة مشروعك</h2>
            <p>يحتاج التشخيص مشروعًا كي يحفظ إجاباتك ويخصص التوصيات.</p>
            <a href="{{ route('app.projects.create') }}" class="btn btn--primary">أضف المشروع وابدأ</a>
        </section>
    @else
        <div class="card-grid">
            @foreach ($projects as $project)
                <article class="card">
                    <p class="eyebrow">{{ $project['stage'] ?: 'مشروع' }}</p>
                    <h2>{{ $project['name'] }}</h2>
                    @if ($project['consultation'] && in_array($project['consultation']['status'], ['active', 'review', 'analysis_queued', 'failed'], true))
                        <p class="muted">أجبت عن {{ $project['consultation']['answered'] }} سؤالًا · الحالة: {{ $project['consultation']['status'] }}</p>
                        <a class="btn btn--primary" href="{{ route('app.consultations.show', $project['consultation']['uuid']) }}">أكمل الاستشارة</a>
                    @else
                        <p class="muted">ابدأ بأسئلة نطاق مرنة؛ ويمكنك اختيار أكثر من إجابة عندما ينطبق أكثر من خيار.</p>
                        <form method="POST" action="{{ route('app.consultations.start', $project['slug']) }}">
                            @csrf
                            <label class="field"><span class="field__label">مستوى العمق</span>
                                <select name="depth"><option value="standard">قياسي</option><option value="quick">سريع</option><option value="deep">متعمق</option></select>
                            </label>
                            <button class="btn btn--primary">ابدأ الاستشارة</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
