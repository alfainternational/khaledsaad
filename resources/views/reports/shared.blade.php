@extends('layouts.public')
@section('interface_family', 'reports')
@section('layout', 'report')

@section('title', $report['title'].' — نسخة للاطلاع | خالد سعد')
@section('description', 'نسخة للقراءة فقط من تقرير تشخيص تسويقي أُنشئ عبر منصة خالد سعد.')

@section('content')
    @include('partials.site-header')

    <main id="main-content" class="container shared-report">
        <p class="alert alert--info" role="note">
            نسخة للاطلاع فقط، شاركها صاحب التقرير معك. الرابط تنتهي صلاحيته تلقائيًا.
        </p>

        <header class="page-head">
            <div>
                <p class="eyebrow">{{ $report['tool']['title'] }} · {{ $report['project']['name'] }}</p>
                <h1>{{ $report['title'] }}</h1>
                <x-freshness-line :updated-at="$report['generated_at'] ?? null" />
            </div>
        </header>

        <section class="report-head">
            <article class="card card--score">
                <p class="eyebrow">الدرجة</p>
                <p class="score-big">{{ $report['score'] }}<small>/100</small></p>
                <p class="score-chip">{{ $report['score_band'] }}</p>
                @if ($comparison)
                    <p @class(['delta', 'delta--up' => $comparison['direction'] === 'up', 'delta--down' => $comparison['direction'] === 'down'])>
                        {{ $comparison['label'] }}
                    </p>
                @endif
            </article>

            <article class="card">
                <p class="eyebrow">الخلاصة</p>
                <p>{{ $report['summary'] }}</p>
            </article>
        </section>

        <section aria-labelledby="shared-findings">
            <h2 id="shared-findings" class="section-title">أهم ما وجده التشخيص</h2>

            @foreach ($report['findings'] as $finding)
                <article class="finding">
                    <header class="finding__head">
                        <h3>{{ $finding['title'] }}</h3>
                        <span class="badge badge--{{ $finding['severity'] }}">{{ $finding['severity_label'] }}</span>
                        <x-evidence-badge :level="$finding['is_assumption'] ? 'inferred' : 'measured'" compact />
                    </header>
                    <p>{{ $finding['description'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="try-cta">
            <div>
                <h2>عندك مشروع يستحق تشخيصًا مثل هذا؟</h2>
                <p>أجب عن أسئلة واضحة عن مشروعك واخرج بدرجتك وأهم فجواتك — البداية مجانية.</p>
            </div>
            <a class="button button--primary button--large" href="{{ route('tools.index') }}">
                ابدأ تشخيص مشروعك <span aria-hidden="true">←</span>
            </a>
        </section>
    </main>

    @include('partials.site-footer', ['brand' => config('brand')])
@endsection
