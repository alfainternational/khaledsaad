<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: hacentunisia; color: #1a2233; font-size: 9.5pt; line-height: 1.75; }
        h1 { color: #fff; font-size: 18pt; margin: 0; }
        h2 { color: #071f5b; font-size: 13pt; border-bottom: 2px solid #2575ff; padding-bottom: 3pt; margin: 16pt 0 7pt; }
        h3 { color: #071f5b; font-size: 10.5pt; margin: 8pt 0 3pt; }
        p { margin: 0 0 5pt; }
        ul, ol { margin: 3pt 0 8pt; }
        table { width: 100%; border-collapse: collapse; margin: 6pt 0; }
        th, td { border: 1px solid #dfe8f5; padding: 5pt; vertical-align: top; }
        th { background: #f5f9ff; color: #071f5b; }
        .cover { background: #071f5b; color: #fff; padding: 20pt; margin-bottom: 12pt; }
        .cover p { color: #c7d4f2; }
        .card { border: 1px solid #dfe8f5; padding: 9pt; margin: 6pt 0; page-break-inside: avoid; }
        .muted { color: #5d6b82; }
        .print-section { break-inside: avoid; page-break-inside: avoid; }
        .print-section--long { break-inside: auto; page-break-inside: auto; }
        .print-report table, .print-table { width: 100%; table-layout: fixed; border-collapse: collapse; }
        .print-report th, .print-report td, .print-table th, .print-table td { overflow-wrap: anywhere; word-break: break-word; }
    </style>
</head>
<body class="print-report">
    <div class="cover">
        <h1>موجز التكليف — {{ $snapshot['agency_brief']['project']['name'] }}</h1>
        <p>معلومات تساعد الوكالة على فهم المطلوب وتسعيره من دون تخمين</p>
        <p>الإصدار {{ $agencyReport->version }} · {{ $agencyReport->generated_at?->format('Y-m-d H:i') }}</p>
    </div>

    @include('agency-reports.partials.document', ['snapshot' => $snapshot])
</body>
</html>
