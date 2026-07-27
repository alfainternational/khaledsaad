@extends('layouts.app')

@section('title', 'موجز التكليف — '.$snapshot['project']['name'])

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">نسخة الوكالة · الإصدار {{ $agencyReport->version }}</p>
            <h1>موجز التكليف — {{ $snapshot['project']['name'] }}</h1>
            <p class="muted">معلومات محايدة تساعد الوكالة على فهم المطلوب وتسعيره من دون تخمين.</p>
        </div>
        <div class="page-head__actions">
            <a class="btn btn--primary" href="{{ route('app.agency-reports.brief.pdf', $agencyReport) }}">حمّل موجز الوكالة PDF</a>
            <a class="btn btn--ghost" href="{{ route('app.agency-reports.show', $agencyReport) }}">ارجع إلى تقريرك</a>
        </div>
    </header>

    <section class="card">
        <h2 class="section-title">مشاركة موجز التكليف مع وكالة</h2>
        @if ($share['is_live'])
            <p>الرابط فعّال حتى {{ \Illuminate\Support\Carbon::parse($share['expires_at'])->locale('ar')->translatedFormat('j F Y') }}.</p>
            <p class="share-link"><code>{{ $share['url'] }}</code></p>
            <form method="POST" action="{{ route('app.agency-reports.share.revoke', $agencyReport) }}">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn--ghost">ألغِ الرابط الآن</button>
            </form>
        @else
            <p>أنشئ رابطًا محدود المدة. الرابط يعرض موجز الوكالة فقط، ولا يعرض تقريرك الخاص.</p>
            <form method="POST" action="{{ route('app.agency-reports.share', $agencyReport) }}" class="form form--inline">
                @csrf
                <label class="field">
                    <span class="field__label">مدة الصلاحية</span>
                    <select name="days">
                        @foreach ($share['expiry_choices'] as $days)<option value="{{ $days }}" @selected($days === 30)>{{ $days }} يومًا</option>@endforeach
                    </select>
                </label>
                <button type="submit" class="btn btn--primary">أنشئ رابط المشاركة</button>
            </form>
        @endif
    </section>

    @include('agency-reports.partials.document', ['snapshot' => $snapshot])
@endsection
