<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: hacentunisia; color: #1a2233; font-size: 10pt; line-height: 1.8; }
        h1 { color: #fff; font-size: 20pt; margin: 0; }
        h2 { color: #071f5b; font-size: 14pt; border-bottom: 2px solid #2575ff; padding-bottom: 3pt; margin: 16pt 0 7pt; }
        h3 { color: #071f5b; font-size: 11pt; margin: 7pt 0 3pt; }
        h4 { color: #071f5b; font-size: 10pt; margin: 7pt 0 2pt; }
        p { margin: 0 0 6pt; }
        ul, ol { margin: 3pt 0 8pt; }
        table { width: 100%; border-collapse: collapse; margin: 7pt 0; }
        th, td { border: 1px solid #dfe8f5; padding: 5pt; vertical-align: top; }
        th { background: #f5f9ff; color: #071f5b; }
        .cover { background: #071f5b; color: #fff; padding: 22pt; margin-bottom: 14pt; }
        .cover p { color: #dbe6ff; }
        .card, .finding { border: 1px solid #dfe8f5; padding: 9pt; margin: 7pt 0; page-break-inside: avoid; }
        .card--warn { background: #fff8f2; border-color: #f6cfa4; }
        .muted { color: #5d6b82; }
        .eyebrow { color: #2575ff; font-size: 8pt; font-weight: bold; }
        .evidence { background: #f5f9ff; padding: 6pt; }
        .print-section { break-inside: avoid; page-break-inside: avoid; }
        .print-section--long { break-inside: auto; page-break-inside: auto; }
        .print-report table, .print-table { width: 100%; table-layout: fixed; border-collapse: collapse; }
        .print-report th, .print-report td, .print-table th, .print-table td { overflow-wrap: anywhere; word-break: break-word; }

        /* المثال الجاهز مطبوعًا: mPDF لا يفهم details/summary ولا flex، فالمكوّن
           يُخرج في وضع الطباعة وسومًا كتلية فقط، وهذه أنماطها. الكتلة النصّية
           تبقى مميّزة بصريًا عمّا حولها لأن التمييز هو ما يقول للقارئ إن ما
           أمامه شيء يُنقل حرفيًا لا فقرة تُقرأ. */
        .worked-example { border: 1px solid #dfe8f5; background: #f5f9ff; padding: 7pt 9pt; margin: 7pt 0; page-break-inside: avoid; }
        .worked-example__summary { color: #071f5b; font-weight: bold; font-size: 9.5pt; margin: 0 0 4pt; }
        .worked-example__title { color: #5d6b82; font-weight: normal; }
        .worked-example__lead { color: #5d6b82; font-size: 8.5pt; margin: 0 0 4pt; }
        .worked-example__text { background: #ffffff; border: 1px solid #dfe8f5; padding: 6pt 8pt; margin: 0;
            white-space: pre-wrap; font-family: hacentunisia; font-size: 9pt; line-height: 1.7;
            overflow-wrap: anywhere; page-break-inside: avoid; }
        .worked-example__text--ltr { direction: ltr; text-align: left; font-family: monospace; font-size: 7.5pt; line-height: 1.5; }
        .worked-example__notes { margin: 5pt 0 0; font-size: 8.5pt; color: #5d6b82; }
        .worked-example__source { margin: 5pt 0 0; font-size: 8.5pt; color: #5d6b82; }
        .evidence-badge { font-size: 8pt; font-weight: normal; color: #b05c00; }
    </style>
</head>
<body class="print-report">
    <div class="cover">
        <h1>أين يقف مشروعك؟</h1>
        <p>{{ $snapshot['project']['name'] }} · تقرير خاص بك يشرح وضعك والخطوة التالية بلغة واضحة</p>
        <p>الإصدار {{ $agencyReport->version }} · {{ $agencyReport->generated_at?->format('Y-m-d H:i') }}</p>
    </div>

    @include('agency-reports.partials.owner-document', ['snapshot' => $snapshot, 'print' => true])
</body>
</html>
