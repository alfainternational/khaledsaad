@extends('layouts.app')
@section('layout', 'report')

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
            <a class="btn btn--ghost" href="{{ route('app.agency-reports.pdf', $agencyReport) }}">حمّل تقريرك الخاص PDF</a>
            @if ($briefReady)
                <a class="btn btn--primary" href="{{ route('app.agency-reports.brief', $agencyReport) }}">افتح موجز الوكالة</a>
            @else
                <a class="btn btn--primary" href="{{ route('app.projects.agency-reports.index', $agencyReport->project) }}">أكمل موجز الوكالة</a>
            @endif
            <a class="btn btn--ghost" href="{{ route('app.projects.agency-reports.index', $agencyReport->project) }}">الإصدارات</a>
        </div>
    </header>

    {{--
        موجز الوكالة مستند ثانٍ منفصل عن «تقريرك الخاص»، ولا يصدر حتى يكتمل
        موجز التكليف. كان الحجب صامتًا (زر عام بلا سبب)، فيظنّ المستخدم أن
        موجز الوكالة غير موجود. نعلن الفجوة صراحةً بالبنود الناقصة بالاسم (§٤.٣).
    --}}
    @unless ($briefReady)
        @php($briefReadiness = $snapshot['agency_brief']['readiness'] ?? [])
        @php($briefMissing = collect($briefReadiness['requirements'] ?? [])->reject(fn ($r) => $r['complete'] ?? false)->values())
        <section class="card card--warn">
            <p class="eyebrow">مستند ثانٍ — موجز الوكالة</p>
            <h2 class="section-title">موجز الوكالة لم يصدر بعد</h2>
            <p>
                ما حمّلته أعلاه هو <b>تقريرك الخاص</b>: يشرح لك وضع مشروعك.
                <b>موجز الوكالة</b> مستند مختلف — النسخة التي ترسلها للوكالات لتسعّر على أساس واحد —
                ولا يصدر حتى يكتمل موجز التكليف.
            </p>
            @if ($briefMissing->isNotEmpty())
                <p class="evidence"><b>{{ $briefReadiness['message'] ?? 'ينقص موجز الوكالة بعض البنود.' }}</b></p>
                <ul class="bullets">
                    @foreach ($briefMissing as $requirement)
                        <li>ينقصك: {{ $requirement['label'] }}</li>
                    @endforeach
                </ul>
            @endif
            <a class="btn btn--primary" href="{{ route('app.projects.agency-reports.index', $agencyReport->project) }}">أكمل البنود الناقصة الآن</a>
        </section>
    @endunless

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
