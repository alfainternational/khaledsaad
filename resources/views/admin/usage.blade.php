@extends('layouts.app')
@section('layout', 'dashboard')

@section('title', 'استهلاك الذكاء الاصطناعي')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة</p>
            <h1>تكلفة الذكاء الاصطناعي</h1>
            <p class="muted">آخر {{ $days }} يومًا. استخدم هذه الأرقام لمقارنة تكلفة كل تشخيص بحجم استخدامه.</p>
        </div>
    </header>

    <section class="stat-row" aria-label="الإجماليات">
        <article class="stat">
            <span class="stat__value">{{ number_format($totals['cost_usd'], 3) }}$</span>
            <span class="stat__label">التكلفة الإجمالية</span>
        </article>
        <article class="stat">
            <span class="stat__value">{{ $totals['runs'] }}</span>
            <span class="stat__label">عملية تحليل</span>
        </article>
        <article class="stat">
            <span class="stat__value">{{ $totals['calls'] }}</span>
            <span class="stat__label">استدعاء نموذج</span>
        </article>
        <article class="stat">
            <span class="stat__value">{{ $totals['avg_latency_ms'] }}ms</span>
            <span class="stat__label">متوسط الزمن</span>
        </article>
        <article class="stat">
            <span class="stat__value">{{ $totals['invalid_outputs'] }}</span>
            <span class="stat__label">مخرج رُفض بالمخطط</span>
        </article>
    </section>

    <section aria-labelledby="models-heading">
        <h2 id="models-heading" class="section-title">حسب النموذج</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>النموذج</th><th>الاستدعاءات</th><th>التكلفة</th><th>متوسط الزمن</th></tr>
                </thead>
                <tbody>
                    @forelse ($by_model as $row)
                        <tr>
                            <td>{{ $row['model'] }}</td>
                            <td>{{ $row['calls'] }}</td>
                            <td>{{ number_format($row['cost_usd'], 4) }}$</td>
                            <td>{{ $row['avg_latency_ms'] }}ms</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">لا استدعاءات مسجلة في هذه المدة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section aria-labelledby="stages-heading">
        <h2 id="stages-heading" class="section-title">حسب المرحلة</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>المرحلة</th><th>الاستدعاءات</th><th>التكلفة</th></tr>
                </thead>
                <tbody>
                    @forelse ($by_stage as $row)
                        <tr>
                            <td>{{ $row['stage'] }}</td>
                            <td>{{ $row['calls'] }}</td>
                            <td>{{ number_format($row['cost_usd'], 4) }}$</td>
                        </tr>
                    @empty
                        <tr><td colspan="3">لا توجد استدعاءات مسجلة حسب المرحلة في هذه المدة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section aria-labelledby="tools-heading">
        <h2 id="tools-heading" class="section-title">حالة الأدوات</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>الأداة</th><th>المفتاح</th><th>الحالة</th></tr>
                </thead>
                <tbody>
                    @foreach ($tools as $tool)
                        <tr>
                            <td>{{ $tool->title }}</td>
                            <td>{{ $tool->key }}</td>
                            <td>{{ $tool->status === 'published' ? 'مشغّلة' : 'قريبًا' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
