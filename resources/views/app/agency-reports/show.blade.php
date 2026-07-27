@extends('layouts.app')

@section('title', $agencyReport->title)

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">نسخة ثابتة · الإصدار {{ $agencyReport->version }}</p>
            <h1>{{ $agencyReport->title }}</h1>
            <p class="muted">لقطة {{ $agencyReport->generated_at?->locale('ar')->translatedFormat('j F Y، H:i') }}</p>
        </div>
        <div class="page-head__actions">
            <a class="btn btn--ghost" href="{{ route('app.agency-reports.pdf', $agencyReport) }}">حمّل PDF للتسليم</a>
            <a class="btn btn--ghost" href="{{ route('app.projects.agency-reports.index', $agencyReport->project) }}">الإصدارات</a>
        </div>
    </header>

    <section class="card">
        <h2 class="section-title">مشاركة المستند مع وكالة</h2>

        @if ($share['is_live'])
            <p>الرابط فعّال حتى
                {{ \Illuminate\Support\Carbon::parse($share['expires_at'])->locale('ar')->translatedFormat('j F Y') }}.
            </p>
            <p class="share-link"><code>{{ $share['url'] }}</code></p>
            <p class="muted">
                فُتح {{ $share['views_count'] }} مرة من {{ $share['unique_viewers'] }} جهة
                @if ($share['last_viewed_at'])
                    · آخر فتح {{ \Illuminate\Support\Carbon::parse($share['last_viewed_at'])->locale('ar')->diffForHumans() }}
                @endif
            </p>

            <form method="POST" action="{{ route('app.agency-reports.share.revoke', $agencyReport) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn--ghost">ألغِ الرابط الآن</button>
            </form>
        @else
            <p class="muted">
                أنشئ رابطًا محدود المدة تسلّمه للوكالة بدل إرسال الملف يدويًا. يمكنك إلغاؤه في أي لحظة،
                وكل فتحة تُسجَّل لك.
                @if ($share['revoked_at'])
                    الرابط السابق أُلغي في
                    {{ \Illuminate\Support\Carbon::parse($share['revoked_at'])->locale('ar')->translatedFormat('j F Y') }}.
                @endif
            </p>

            <form method="POST" action="{{ route('app.agency-reports.share', $agencyReport) }}" class="form form--inline">
                @csrf
                <label class="field">
                    <span class="field__label">مدة الصلاحية</span>
                    <select name="days">
                        @foreach ($share['expiry_choices'] as $days)
                            <option value="{{ $days }}" @selected($days === 30)>{{ $days }} يومًا</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="btn btn--primary">أنشئ رابط مشاركة</button>
            </form>
        @endif
    </section>

    {{-- دليلك أنت: يظهر هنا فقط، ولا يدخل الرابط المشترك ولا PDF الوكالة. --}}
    <section class="card card--link">
        <p class="eyebrow">خاص بك</p>
        <h2 class="section-title">القسم التالي لا تراه الوكالة</h2>
        <p class="muted">
            ما يلي أوراقك في التفاوض: حساب ما تكفيه ميزانيتك، وأسئلة المقارنة، وعلامات الإنذار.
            أما المستند الذي يُسلَّم فيبدأ من «التكليف» ولا يتضمن هذا القسم.
        </p>
    </section>

    @include('agency-reports.partials.owner-guide', ['snapshot' => $snapshot])

    <h2 class="section-title">المستند كما تراه الوكالة</h2>
    @include('agency-reports.partials.document', ['snapshot' => $snapshot])
@endsection
