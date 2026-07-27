@extends('layouts.app')

@section('title', $agencyReport->title)

@section('content')
    @php($briefReady = $snapshot['agency_brief']['readiness']['is_ready'] ?? false)

    <header class="page-head">
        <div>
            <p class="eyebrow">تقريرك الخاص · الإصدار {{ $agencyReport->version }}</p>
            <h1>{{ $agencyReport->title }}</h1>
            <p class="muted">صورة ثابتة لحالة مشروعك في {{ $agencyReport->generated_at?->locale('ar')->translatedFormat('j F Y، H:i') }}</p>
        </div>
        <div class="page-head__actions">
            <a class="btn btn--ghost" href="{{ route('app.agency-reports.pdf', $agencyReport) }}">حمّل تقريرك PDF</a>
            @if ($briefReady)
                <a class="btn btn--primary" href="{{ route('app.agency-reports.brief', $agencyReport) }}">افتح موجز الوكالة</a>
            @else
                <a class="btn btn--ghost" href="{{ route('app.projects.agency-reports.index', $agencyReport->project) }}">أكمل بيانات موجز الوكالة</a>
            @endif
            <a class="btn btn--ghost" href="{{ route('app.projects.agency-reports.index', $agencyReport->project) }}">الإصدارات</a>
        </div>
    </header>

    @if ($freshness['is_stale'])
        <section class="card card--warn">
            <h2 class="section-title">لديك معلومات أحدث من هذا التقرير</h2>
            <ul class="bullets">@foreach ($freshness['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach</ul>
            <p>سنحتفظ بهذه النسخة، وننشئ لك نسخة جديدة بالمعلومات الحالية.</p>
            <form method="POST" action="{{ route('app.projects.agency-reports.store', $agencyReport->project) }}">
                @csrf
                @foreach (($agencyReport->visibility ?? []) as $key => $value)
                    <input type="hidden" name="visibility[{{ $key }}]" value="{{ $value }}">
                @endforeach
                <button type="submit" class="btn btn--primary">أنشئ تقريرًا محدثًا</button>
            </form>
        </section>
    @endif

    @include('agency-reports.partials.owner-document', ['snapshot' => $snapshot])
@endsection
