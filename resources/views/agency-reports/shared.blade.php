<!doctype html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#071F5B">

        {{-- مستند خاص شاركه صاحبه برابط: لا يُفهرس ولا يُتتبع خارجيًا. --}}
        <meta name="robots" content="noindex, nofollow, noarchive">
        <meta name="referrer" content="no-referrer">

        <title>موجز التكليف — {{ $snapshot['agency_brief']['project']['name'] }}</title>

        @include('partials.font')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="panel" data-layout="reading">
        <main class="panel__main panel__main--shared">
            <header class="page-head">
                <div>
                    <p class="eyebrow">موجز تكليف مشترك · الإصدار {{ $agencyReport->version }}</p>
                    <h1>موجز التكليف — {{ $snapshot['agency_brief']['project']['name'] }}</h1>
                    <p class="muted">
                        لقطة {{ $agencyReport->generated_at?->locale('ar')->translatedFormat('j F Y') }}
                        · شاركه صاحب المشروع، وصلاحية الرابط تنتهي في
                        {{ $agencyReport->share_expires_at?->locale('ar')->translatedFormat('j F Y') }}
                    </p>
                </div>
                <div class="page-head__actions">
                    <a class="btn btn--primary" href="{{ route('shared.agency-report.pdf', $shareToken) }}">حمّل نسخة PDF</a>
                </div>
            </header>

            <section class="card">
                <p>
                    يحتوي هذا الموجز على المعلومات التي تحتاجها الوكالة لفهم المطلوب وتسعيره وبدء العمل.
                    صيغت البنود بصيغة محايدة، وتظهر المعلومات غير المعروفة بوضوح حتى تُقاس قبل اعتماد أي وعد.
                </p>
            </section>

            @include('agency-reports.partials.document', ['snapshot' => $snapshot])
        </main>
    </body>
</html>
