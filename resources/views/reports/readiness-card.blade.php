<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: hacentunisia; color: #1a2233; font-size: 10pt; line-height: 1.8; }
        h2 { color: #071f5b; font-size: 12.5pt; margin: 16pt 0 6pt; border-bottom: 2px solid #2575ff; padding-bottom: 3pt; }
        h3 { color: #071f5b; font-size: 10.5pt; margin: 0 0 3pt; }
        p { margin: 0 0 6pt; }
        .muted { color: #5d6b82; }
        .eyebrow { color: #5d6b82; font-size: 8pt; }

        .cover { background-color: #071f5b; padding: 16pt 18pt; }
        .cover h1 { color: #ffffff; font-size: 17pt; margin: 0; }
        .cover-sub { color: #b9c6e8; font-size: 9.5pt; margin-top: 3pt; }
        .cover-date { color: #8fa1d0; font-size: 8.5pt; }

        .score-num { font-size: 30pt; font-weight: bold; color: #071f5b; line-height: 1.1; }
        .score-den { font-size: 11pt; color: #5d6b82; }
        .band { border: 1px solid #dfe8f5; background-color: #ffffff; padding: 2pt 10pt; border-radius: 10pt; font-size: 9pt; color: #071f5b; }

        /* وسم المصدر: ما يفرّق هذه البطاقة عن تقرير مبنيّ على كلام المستخدم. */
        .measured { background-color: #e7f8ef; border: 1px solid #b7e6cd; color: #0a7d4f; padding: 2pt 9pt; border-radius: 8pt; font-size: 8pt; }

        .check { border: 1px solid #dfe8f5; border-radius: 6pt; padding: 9pt 12pt; margin-bottom: 7pt; page-break-inside: avoid; }
        .check-pass { border-right: 3px solid #0f8a4d; }
        .check-fail { border-right: 3px solid #d92d20; }
        .status-pass { color: #0a7d4f; font-size: 8.5pt; font-weight: bold; }
        .status-fail { color: #a3221a; font-size: 8.5pt; font-weight: bold; }
        .why { color: #5d6b82; font-size: 8.5pt; margin: 3pt 0 0; }
        .fix { background-color: #f5f9ff; padding: 5pt 9pt; border-radius: 4pt; font-size: 8.5pt; margin-top: 5pt; }

        /* القصاصة الجاهزة للصق. dir="ltr" على الكود: JSON-LD وrobots.txt
           تُقرآن من اليسار، وعرضهما بـRTL يقلب الأقواس فيصير النص غير صالح
           للنسخ — وهو كل الغرض منه. */
        .snippet { margin-top: 4pt; }
        .snippet__where { font-size: 8pt; color: #5d6b82; margin-bottom: 2pt; }
        .snippet__code { direction: ltr; text-align: left; white-space: pre-wrap; font-family: monospace;
            font-size: 7.5pt; line-height: 1.5; background-color: #fbf8ef; border: 1px solid #e5ddc8;
            padding: 5pt 7pt; margin: 0; page-break-inside: avoid; }

        .tbl { border-collapse: collapse; width: 100%; }
        .tbl td, .tbl th { border: 1px solid #dfe8f5; font-size: 8.5pt; padding: 4pt 6pt; text-align: right; }
        .tbl th { background-color: #f5f9ff; color: #071f5b; }
        .tbl .num { text-align: center; font-weight: bold; color: #071f5b; }

        .warn-box { background-color: #fff8f2; border: 1px solid #f6cfa4; padding: 8pt 12pt; margin: 8pt 0; }
        .info-box { background-color: #f5f9ff; border: 1px solid #dfe8f5; padding: 8pt 12pt; margin: 8pt 0; }

        .effort { padding: 1pt 7pt; border-radius: 8pt; font-size: 7.5pt; border: 1px solid #dfe8f5; }
        .effort-low { background-color: #e7f8ef; color: #0a7d4f; border-color: #b7e6cd; }
        .effort-medium { background-color: #fff9e6; color: #6d5405; }
        .effort-high { background-color: #fff2e6; color: #8a4b06; border-color: #f6cfa4; }

        table { border-collapse: collapse; }
        td { overflow-wrap: anywhere; }
    </style>
</head>
<body class="print-report">

<div class="cover">
    <h1>بطاقة الجاهزية للذكاء الاصطناعي</h1>
    <div class="cover-sub">{{ $project->name }}</div>
    <div class="cover-date">{{ $generatedAt->locale('ar')->translatedFormat('j F Y') }} — {{ $audit->url }}</div>
</div>

@if (! $audit->reachable)
    {{-- تعذّر الفحص ليس نتيجة فحص. الصفحة تقول ذلك بدل أن تعرض صفرًا يُقرأ كحكم. --}}
    <div class="warn-box">
        <h3>لم نتمكّن من الوصول إلى الموقع</h3>
        <p class="muted" style="font-size: 9pt; margin: 0;">
            لم يُفحص شيء، وهذه ليست نتيجة سلبية. تأكّد أن العنوان صحيح وأن الموقع يستقبل الزيارات، ثم أعد التشغيل.
        </p>
    </div>
@else

<table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 12pt;">
    <tr>
        <td width="34%" style="vertical-align: top;">
            <span class="score-num">{{ $score->score }}</span>
            <span class="score-den">/ 100</span>
            <div style="margin-top: 5pt;"><span class="measured">مقيس من موقعك</span></div>
        </td>
        <td style="vertical-align: top; padding-right: 14pt;">
            <p class="eyebrow">على أي أساس</p>
            <p style="font-size: 9pt; margin: 0;">
                {{-- كل رقم يُعرض مع أساسه (§١٣): بلا التغطية لا يعرف القارئ إن كانت ٤١ من فحص كامل أم ناقص. --}}
                فُحص {{ (int) round($score->coverage * count($score->breakdown)) }} بندًا من
                {{ count($score->breakdown) }}، أي تغطية {{ (int) round($score->coverage * 100) }}٪.
            </p>
            <p class="muted" style="font-size: 8.5pt; margin: 4pt 0 0;">
                هذه الدرجة مرصودة من صفحات موقعك وإعداداته، لا من إجاباتك عن نفسك.
            </p>
        </td>
    </tr>
</table>

<h2>الفحص بندًا بندًا</h2>

@foreach ($audit->checklist() as $item)
    <div class="check {{ $item['passed'] ? 'check-pass' : 'check-fail' }}">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td><h3>{{ $item['label'] }}</h3></td>
                <td width="22%" style="text-align: left;">
                    <span class="{{ $item['passed'] ? 'status-pass' : 'status-fail' }}">
                        {{ $item['passed'] ? 'سليم' : 'يحتاج إصلاحًا' }}
                    </span>
                </td>
            </tr>
        </table>

        @if (! empty($item['detail']))
            <p class="eyebrow" style="margin: 2pt 0 0;">
                ما وجدناه: {{ is_array($item['detail']) ? implode('، ', $item['detail']) : $item['detail'] }}
            </p>
        @endif

        @unless ($item['passed'])
            <p class="why">{{ $item['why'] }}</p>
            <div class="fix"><b>الإصلاح:</b> {{ $item['fix'] }}</div>
        @endunless
    </div>
@endforeach

@endif

<h2>تقرير الزحف</h2>

@if ($crawl === null)
    <div class="info-box">
        <p style="margin: 0; font-size: 9pt;">لم يُرفع سجل خادم بعد، فلا نعرف إن كانت بوتات النماذج تزور موقعك أصلًا.</p>
        <p class="muted" style="margin: 4pt 0 0; font-size: 8.5pt;">
            ارفع سجل الوصول من لوحة الاستضافة لتعرف: أي بوت زار، ومتى، وأي صفحات رُفضت أمامه.
        </p>
    </div>
@elseif ($crawl['parsed_lines'] === 0)
    <div class="warn-box">
        <p style="margin: 0; font-size: 9pt;">تعذّرت قراءة السجل المرفوع.</p>
        <p class="muted" style="margin: 4pt 0 0; font-size: 8.5pt;">
            {{-- «صفر زيارة» من ملف لم يُقرأ يصف الملف لا الموقع. --}}
            لم يُفهم أي سطر من {{ $crawl['unparsed_lines'] }}، فلا يمكن استنتاج شيء عن الزيارات.
            تأكّد أنه سجل وصول بصيغة Combined.
        </p>
    </div>
@else
    <p class="muted" style="font-size: 9pt;">
        {{ $crawl['total_visits'] }} زيارة بوت خلال آخر ٣٠ يومًا، من
        {{ $crawl['parsed_lines'] }} سطرًا مقروءًا.
        @if ($crawl['unparsed_lines'] > 0)
            ({{ $crawl['unparsed_lines'] }} سطرًا لم يُفهم — التغطية {{ (int) round($crawl['parse_ratio'] * 100) }}٪.)
        @endif
    </p>

    @if ($crawl['bots'] === [])
        <div class="warn-box">
            <p style="margin: 0; font-size: 9pt;">لم يزر موقعك أي بوت ذكاء اصطناعي في هذه النافذة.</p>
            <p class="muted" style="margin: 4pt 0 0; font-size: 8.5pt;">
                الموقع الذي لا يُزار لا يظهر في الإجابات مهما تحسّن محتواه. ابدأ من بند «بوتات الذكاء غير محجوبة» أعلاه.
            </p>
        </div>
    @else
        <table class="tbl" style="margin-top: 6pt;">
            <tr>
                <th>البوت</th>
                <th width="18%">الزيارات</th>
                <th width="20%">رُفض أمامه</th>
                <th width="26%">آخر زيارة</th>
            </tr>
            @foreach ($crawl['bots'] as $bot)
                <tr>
                    <td>{{ $bot['bot'] }}</td>
                    <td class="num">{{ $bot['visits'] }}</td>
                    <td class="num">{{ $bot['blocked'] }}</td>
                    <td>{{ $bot['last_seen'] ? \Illuminate\Support\Carbon::parse($bot['last_seen'])->locale('ar')->translatedFormat('j F') : '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if (! empty($crawl['blocked']))
        <h3 style="margin-top: 12pt;">صفحات جاءها البوت ولم يقرأها</h3>
        <p class="muted" style="font-size: 8.5pt; margin: 0 0 5pt;">
            أهم ما يكشفه هذا التقرير: صفحات تظنّها مرئية وهي ليست كذلك.
        </p>
        <table class="tbl">
            <tr><th>الصفحة</th><th width="18%">البوت</th><th width="14%">الحالة</th></tr>
            @foreach (array_slice($crawl['blocked'], 0, 15) as $row)
                <tr>
                    <td>{{ $row['path'] }}</td>
                    <td>{{ $row['bot'] }}</td>
                    <td class="num">{{ $row['status'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endif

@if (! empty($fixes))
    <h2>ما أصلحه هذا الأسبوع</h2>
    <p class="muted" style="font-size: 8.5pt; margin: 0 0 6pt;">
        مرتّبة على الأثر ثم الجهد: ابدأ من الأعلى.
    </p>

    <table class="tbl">
        <tr><th width="6%">#</th><th>البند</th><th width="20%">الجهد</th></tr>
        @foreach (array_slice($fixes, 0, 10) as $index => $fix)
            <tr>
                <td class="num">{{ $index + 1 }}</td>
                <td>
                    {{ $fix['title'] }}
                    @if ($fix['fix'])
                        <div class="muted" style="font-size: 8pt;">{{ $fix['fix'] }}</div>
                    @endif

                    {{-- القصاصة في المطبوع أيضًا: أكثر من يُنفّذ هذه البنود
                         مطوّر يقرأ البطاقة مطبوعة بجانب المحرر. --}}
                    @if ($fix['snippet'] ?? null)
                        <div class="snippet">
                            <div class="snippet__where">{{ $fix['snippet']['where'] }}</div>
                            <pre class="snippet__code">{{ $fix['snippet']['code'] }}</pre>
                        </div>
                    @endif
                </td>
                <td><span class="effort effort-{{ $fix['effort'] }}">{{ $fix['effort_label'] }}</span></td>
            </tr>
        @endforeach
    </table>
@endif

</body>
</html>
