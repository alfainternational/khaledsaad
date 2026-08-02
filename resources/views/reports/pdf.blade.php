<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        /* خط الموقع نفسه: Hacen Tunisia المحمّل من public/assets/fonts */
        body { font-family: hacentunisia; color: #1a2233; font-size: 10pt; line-height: 1.8; }
        h1 { color: #ffffff; font-size: 17pt; margin: 0; }
        h2 { color: #071f5b; font-size: 12.5pt; margin: 16pt 0 6pt; border-bottom: 2px solid #2575ff; padding-bottom: 3pt; }
        h3 { color: #071f5b; font-size: 10.5pt; margin: 0 0 3pt; }
        h4 { color: #071f5b; font-size: 10pt; margin: 9pt 0 3pt; }
        p { margin: 0 0 4pt; }
        .muted { color: #5d6b82; }
        .eyebrow { color: #5d6b82; font-size: 8pt; }

        /* --- الغلاف --- */
        .cover { background-color: #071f5b; padding: 16pt 18pt; }
        .cover-sub { color: #b9c6e8; font-size: 9.5pt; margin-top: 3pt; }
        .cover-date { color: #8fa1d0; font-size: 8.5pt; }

        /* --- الدرجة --- */
        .score-num { font-size: 30pt; font-weight: bold; color: #071f5b; line-height: 1.1; }
        .score-den { font-size: 11pt; color: #5d6b82; }
        .band { border: 1px solid #dfe8f5; background-color: #ffffff; padding: 2pt 10pt; border-radius: 10pt; font-size: 9pt; color: #071f5b; }
        .delta { font-size: 8.5pt; font-weight: bold; margin-top: 6pt; }
        .delta-up { color: #0f8a4d; }
        .delta-down { color: #d92d20; }
        .delta-flat { color: #5d6b82; }

        /* صندوق التوثيق جدول لا div: الـdiv بخلفية داخل خلية لا يتمدد مع التفاف السطور في mPDF */
        .verified { background-color: #e7f8ef; border: 1px solid #b7e6cd; }
        .verified b { color: #0a7d4f; font-size: 9.5pt; }
        .verified em { color: #38614f; font-size: 8.5pt; font-style: normal; }

        /* --- بطاقات الرسوم --- */
        .chart-card { border: 1px solid #dfe8f5; border-radius: 6pt; padding: 10pt 12pt; }
        .chart-title { color: #071f5b; font-size: 9.5pt; font-weight: bold; margin: 0 0 7pt; }
        .bar-label { font-size: 8.5pt; color: #38445c; }
        .bar-count { font-size: 8.5pt; color: #071f5b; font-weight: bold; text-align: left; }
        .legend { font-size: 8pt; color: #5d6b82; }
        .dot { display: inline-block; width: 7pt; height: 7pt; border-radius: 3.5pt; }
        .col-value { font-size: 8pt; color: #071f5b; font-weight: bold; }
        .col-label { font-size: 7.5pt; color: #5d6b82; }

        /* مصفوفة الأثر والجهد */
        .matrix td, .matrix th { border: 1px solid #dfe8f5; text-align: center; font-size: 8.5pt; padding: 4pt 2pt; }
        .matrix th { background-color: #f5f9ff; color: #071f5b; }
        .matrix .hot { background-color: #e7f8ef; color: #0a7d4f; font-weight: bold; }
        .matrix .cell-filled { color: #071f5b; font-weight: bold; }
        .matrix .cell-empty { color: #b6c1d4; }

        /* --- صناديق المحتوى --- */
        .next-step { background-color: #fff8f2; border: 1px solid #f6cfa4; border-right: 3px solid #ff9b27; padding: 9pt 12pt; margin: 10pt 0; }
        .watch-box { background-color: #f5f9ff; border: 1px solid #dfe8f5; border-right: 3px solid #09d7e5; padding: 8pt 12pt; margin: 8pt 0; }
        .warn-box { background-color: #fff8f2; border: 1px solid #f6cfa4; padding: 8pt 12pt; margin: 8pt 0; }
        .info-box { background-color: #f5f9ff; border: 1px solid #dfe8f5; padding: 8pt 12pt; margin: 8pt 0; }

        .finding { border: 1px solid #dfe8f5; border-radius: 6pt; padding: 10pt 12pt; margin-bottom: 8pt; page-break-inside: avoid; }
        .badge { padding: 1pt 7pt; border-radius: 8pt; font-size: 7.5pt; border: 1px solid #dfe8f5; }
        .badge-critical { background-color: #fdeceb; color: #8d1d13; border-color: #f0b3ad; }
        .badge-high { background-color: #fff2e6; color: #8a4b06; border-color: #f6cfa4; }
        .badge-medium { background-color: #fff9e6; color: #6d5405; }
        .badge-low { background-color: #f5f9ff; color: #5d6b82; }
        .badge-assumption { background-color: #f2f0ff; color: #40339c; border-color: #cfc7f5; }
        .badge-task { background-color: #e7f8ef; color: #0a7d4f; border-color: #b7e6cd; }
        .evidence { background-color: #f5f9ff; padding: 5pt 9pt; border-radius: 4pt; font-size: 8.5pt; margin: 5pt 0; }
        .rec { border-top: 1px dashed #dfe8f5; padding-top: 5pt; margin-top: 5pt; }
        .tags { font-size: 8pt; color: #5d6b82; }

        /* الخطوات والمثال الجاهز داخل التوصية.
           المثال بخلفية رمادية وحدود ثابتة ليُقرأ ككتلة تُنسخ لا كنصّ تحليل،
           وwhite-space: pre-wrap يحفظ أسطر الرسالة كما كُتبت — سطرٌ ملتصق
           يُفقد الرسالة شكلها فتُقرأ كفقرة لا كنصّ يُلصق. */
        .rec-steps { margin: 4pt 12pt 4pt 0; padding: 0; font-size: 9pt; color: #33415c; }
        .rec-steps li { margin-bottom: 2pt; }
        .rec-example { border: 1px solid #e5ddc8; background-color: #fbf8ef; padding: 6pt 8pt; margin-top: 5pt;
            page-break-inside: avoid; }
        .rec-example__head { font-size: 9pt; font-weight: 700; color: #6b5b20; margin: 0 0 4pt; }
        .rec-example__body { white-space: pre-wrap; font-size: 9pt; line-height: 1.7; color: #2b3648;
            margin: 0; padding: 0; }
        .rec-example__notes { margin: 5pt 12pt 0 0; padding: 0; font-size: 8pt; color: #5d6b82; }

        /* كتلة الخلاصة: صندوق كتلي يتمدد مع النص، لا خلية جدول تقصّه. */
        .summary-box { border: 1px solid #dfe8f5; border-right: 3px solid #2575ff; background-color: #ffffff;
            padding: 9pt 12pt 12pt; margin-top: 7pt; }

        /* توقيع المُراجِع في نهاية التقرير اليدوي: بيان صادق موجز. */
        .report-sign { margin-top: 14pt; padding-top: 8pt; border-top: 1px solid #dfe8f5;
            color: #5d6b82; font-size: 9pt; text-align: center; }

        /* --- تفاصيل التحليل --- */
        .section-box { border: 1px solid #dfe8f5; border-radius: 6pt; padding: 9pt 12pt; margin-bottom: 7pt; page-break-inside: avoid; }
        .kv td { border-bottom: 1px solid #eef2fa; padding: 3pt 0; font-size: 9pt; }
        .kv .kv-value { text-align: left; color: #071f5b; font-weight: bold; }
        .chip { border: 1px solid #dfe8f5; border-radius: 8pt; padding: 1pt 7pt; font-size: 8pt; background-color: #ffffff; }
        .chip-local { background-color: #e7f8ef; color: #0a7d4f; }
        .chip-regional { background-color: #e0ecff; color: #2575ff; }
        .chip-global { background-color: #f0eafd; color: #6b46c1; }
        .watch-row td { border-bottom: 1px solid #eef2fa; padding: 4pt 0; font-size: 8.5pt; vertical-align: top; }
        .watch-source { text-align: left; color: #5d6b82; font-size: 8pt; white-space: nowrap; }
        ul { margin: 3pt 0; padding-right: 14pt; }
        li { margin-bottom: 2pt; }
        .assumptions li { color: #6d5405; }

        /* عقد تخطيط الطباعة: أقسام قصيرة متماسكة، وأقسام طويلة قابلة للتجزئة، وجداول بلا تجاوز أفقي. */
        .print-section { break-inside: avoid; page-break-inside: avoid; }
        .print-section--long { break-inside: auto; page-break-inside: auto; }
        .print-report table, .print-table { width: 100%; table-layout: fixed; border-collapse: collapse; }
        .print-report th, .print-report td, .print-table th, .print-table td { overflow-wrap: anywhere; word-break: break-word; }
    </style>
</head>
<body class="print-report">

{{-- الغلاف --}}
<div class="cover">
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <h1>{{ $report['title'] }}</h1>
                <div class="cover-sub">{{ $report['tool']['title'] }} — {{ $report['project']['name'] }}</div>
            </td>
            <td width="130" style="text-align: left; vertical-align: top;">
                <img src="{{ public_path('assets/brand/khaled-saad-light.png') }}" width="110">
                <div class="cover-date">{{ $generatedAt->locale('ar')->translatedFormat('j F Y') }}</div>
            </td>
        </tr>
    </table>
</div>

{{--
    الدرجة ثم الخلاصة.

    الخلاصة كتلة مستقلة بعرض كامل، لا خلية في جدول تخطيط: mPDF يحسب ارتفاع
    الخلية أقل من محتواها العربي فيقع آخر سطر خارج الإطار (اقتصاص بصري).
    الكتلة المستقلة تتمدد مع النص مهما طال، فلا يُقصّ شيء أبدًا.
--}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 12pt;">
    <tr>
        <td width="36%" style="vertical-align: top; border: 1px solid #dfe8f5; background-color: #f5f9ff; padding: 12pt 14pt;">
            <span class="score-num">{{ $report['score'] }}</span>
            <span class="score-den">/ 100</span>
            <div style="margin-top: 5pt;"><span class="band">{{ $report['score_band'] }}</span></div>
            @php($scorePct = max(2, min(100, (int) $report['score'])))
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 8pt;">
                <tr>
                    <td width="{{ $scorePct }}%" height="8" style="background-color: {{ $charts['score_gauge']['color'] }}; font-size: 1pt;">&nbsp;</td>
                    @if ($scorePct < 100)
                        <td height="8" style="background-color: #e4ebf7; font-size: 1pt;">&nbsp;</td>
                    @endif
                </tr>
            </table>
        </td>
        <td width="3%"></td>
        <td style="vertical-align: top; padding: 0;">
            @if (! empty($report['is_manually_reviewed']))
                <table width="100%" cellpadding="6" cellspacing="0" class="verified">
                    <tr>
                        <td>
                            <b>راجعها {{ $report['reviewer_name'] }} بنفسه — ليست نتيجة آلة</b><br>
                            <em>قرأ إجاباتك وكتب لك هذا التحليل{{ $report['reviewed_at'] ? ' في '.$report['reviewed_at'] : '' }}.</em>
                        </td>
                    </tr>
                </table>
            @endif

            {{-- المقارنة الزمنية: نفس ما يظهر أعلى صفحة التقرير --}}
            @if (! empty($comparison))
                <div class="delta delta-{{ $comparison['direction'] }}">{{ $comparison['label'] }}</div>
            @endif
        </td>
    </tr>
</table>

<div class="summary-box print-section">
    <h3>الخلاصة</h3>
    <p>{{ $report['summary'] }}</p>
    <p class="muted" style="font-size: 8.5pt; margin: 4pt 0 0;">
        {{ $report['counts']['evidence_backed'] }} نتيجة مبنية على ما كتبته،
        و{{ $report['counts']['assumptions'] }} اجتهاد يحتاج تأكيدًا منك.
    </p>
</div>

{{-- لوحة المؤشرات --}}
<h2>المؤشرات في لمحة</h2>
<table width="100%" cellpadding="0" cellspacing="0">
    <tr>
        @if ($charts['severity_distribution'] !== null)
            <td width="48.5%" class="chart-card" style="vertical-align: top;">
                <p class="chart-title">{{ $charts['severity_distribution']['title'] }}</p>
                <table width="100%" cellpadding="2" cellspacing="0">
                    @foreach ($charts['severity_distribution']['items'] as $item)
                        @php($pct = max(4, (int) round($item['count'] * 100 / max(1, $charts['severity_distribution']['total']))))
                        <tr>
                            <td width="26%" class="bar-label">{{ $item['label'] }}</td>
                            <td>
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td width="{{ $pct }}%" height="9" style="background-color: {{ $item['color'] }}; font-size: 1pt;">&nbsp;</td>
                                        @if ($pct < 100)
                                            <td height="9" style="background-color: #eef2fa; font-size: 1pt;">&nbsp;</td>
                                        @endif
                                    </tr>
                                </table>
                            </td>
                            <td width="10%" class="bar-count">{{ $item['count'] }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
            <td width="3%"></td>
        @endif

        @if ($charts['evidence_split'] !== null)
            <td class="chart-card" style="vertical-align: top;">
                <p class="chart-title">{{ $charts['evidence_split']['title'] }}</p>
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        @foreach ($charts['evidence_split']['items'] as $item)
                            @if ($item['count'] > 0)
                                <td width="{{ (int) round($item['count'] * 100 / max(1, $charts['evidence_split']['total'])) }}%"
                                    style="background-color: {{ $item['color'] }}; height: 11pt; text-align: center; color: #ffffff; font-size: 8pt;">
                                    {{ $item['count'] }}
                                </td>
                            @endif
                        @endforeach
                    </tr>
                </table>
                <p class="legend" style="margin-top: 6pt;">
                    @foreach ($charts['evidence_split']['items'] as $item)
                        <span class="dot" style="background-color: {{ $item['color'] }};"></span>
                        {{ $item['label'] }} ({{ $item['count'] }})&nbsp;&nbsp;
                    @endforeach
                </p>
            </td>
        @endif
    </tr>
</table>

@if ($charts['score_history'] !== null || $charts['impact_effort'] !== null)
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 8pt;">
        <tr>
            @if ($charts['score_history'] !== null)
                <td width="48.5%" class="chart-card" style="vertical-align: top;">
                    <p class="chart-title">{{ $charts['score_history']['title'] }}</p>
                    @php($chartHeight = 60)
                    <table width="100%" cellpadding="1" cellspacing="0">
                        <tr>
                            @foreach ($charts['score_history']['points'] as $point)
                                @php($barHeight = max(4, (int) round($point['value'] * $chartHeight / 100)))
                                <td style="vertical-align: bottom; text-align: center;">
                                    <div class="col-value">{{ $point['value'] }}</div>
                                    <table cellpadding="0" cellspacing="0" style="margin: 0 auto;">
                                        <tr>
                                            <td width="16" height="{{ $barHeight }}" style="background-color: {{ $point['is_current'] ? '#2575ff' : '#9db7e8' }}; font-size: 1pt;">&nbsp;</td>
                                        </tr>
                                    </table>
                                </td>
                            @endforeach
                        </tr>
                        <tr>
                            @foreach ($charts['score_history']['points'] as $point)
                                <td class="col-label" style="text-align: center; border-top: 1px solid #dfe8f5;">{{ $point['label'] }}</td>
                            @endforeach
                        </tr>
                    </table>
                </td>
                <td width="3%"></td>
            @endif

            @if ($charts['impact_effort'] !== null)
                <td class="chart-card" style="vertical-align: top;">
                    <p class="chart-title">{{ $charts['impact_effort']['title'] }}</p>
                    <table width="100%" cellpadding="0" cellspacing="0" class="matrix print-table">
                        <tr>
                            <th></th>
                            @foreach (['low', 'medium', 'high'] as $effort)
                                <th>{{ $charts['impact_effort']['effort_labels'][$effort] }}</th>
                            @endforeach
                        </tr>
                        @foreach (['high', 'medium', 'low'] as $impact)
                            <tr>
                                <th>{{ $charts['impact_effort']['impact_labels'][$impact] }}</th>
                                @foreach (['low', 'medium', 'high'] as $effort)
                                    @php($cell = collect($charts['impact_effort']['cells'])->first(fn ($c) => $c['impact'] === $impact && $c['effort'] === $effort))
                                    <td class="{{ $impact === 'high' && $effort === 'low' ? 'hot' : ($cell['count'] > 0 ? 'cell-filled' : 'cell-empty') }}">
                                        {{ $cell['count'] > 0 ? $cell['count'] : '—' }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </table>
                    @if ($charts['impact_effort']['quick_wins'] > 0)
                        <p class="legend" style="margin-top: 5pt;">ابدأ من الخلية الخضراء: {{ $charts['impact_effort']['quick_wins'] }} توصية بأثر عالٍ وجهد بسيط.</p>
                    @endif
                </td>
            @endif
        </tr>
    </table>
@endif

{{-- الخطوة التالية --}}
@if (! empty($report['next_step']))
    <div class="next-step">
        <h3>الخطوة التالية: {{ $report['next_step']['title'] ?? '' }}</h3>
        <p>{{ $report['next_step']['description'] ?? '' }}</p>
    </div>
@endif

{{-- التقرير الحي: ما رصدته المراقبة، كما يظهر على الشاشة --}}
<div class="watch-box">
    @if ($watcher?->isActive())
        @if (! empty($watcher->changes))
            <h3>تقريرك الحي رصد تغييرًا</h3>
            <ul>
                @foreach ($watcher->changes as $change)
                    <li>{{ $change['text'] ?? '' }}</li>
                @endforeach
            </ul>
        @else
            <h3>متابعة التقرير</h3>
            <p class="muted">
                سننبهك إذا تغيّرت البيانات التي بُني عليها هذا التقرير.
                @if ($watcher->last_checked_at)
                    آخر فحص {{ $watcher->last_checked_at->locale('ar')->translatedFormat('j F Y') }}.
                @endif
            </p>
        @endif
    @else
        <h3>لا تدع هذا التقرير يشيخ</h3>
        <p class="muted">
            فعّل المتابعة المستمرة: نراقب مشروعك يوميًا — ملفك، منافسيك، إجاباتك — وننبهك فور أن يتغيّر
            ما بُنيت عليه هذه النتائج. بلا أي تكلفة. فعّله من صفحة التقرير في حسابك.
        </p>
    @endif
</div>

{{-- ما قلناه بالتخمين — نصارحك به --}}
@if (! empty($report['assumptions']))
    <h2>أشياء خمّناها، ونحتاج تأكيدك عليها</h2>
    <div class="warn-box">
        <ul class="assumptions">
            @foreach ($report['assumptions'] as $assumption)
                <li>{{ $assumption }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- ما وجدناه وكيف تعالجه --}}
<h2>ما وجدناه لك، وكيف تعالجه</h2>
@forelse ($report['findings'] as $finding)
    <div class="finding print-section">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td><h3>{{ $finding['title'] }}</h3></td>
                <td style="text-align: left; white-space: nowrap;" width="150">
                    <span class="badge badge-{{ $finding['severity'] }}">{{ $finding['severity_label'] }}</span>
                    <span class="badge {{ $finding['is_assumption'] ? 'badge-assumption' : '' }}">{{ $finding['basis_label'] }}</span>
                </td>
            </tr>
        </table>
        <p>{{ $finding['description'] }}</p>

        @if (! empty($finding['evidence']))
            <div class="evidence"><strong>الدليل:</strong> {{ $finding['evidence'] }}</div>
        @endif

        @foreach ($finding['recommendations'] as $rec)
            <div class="rec">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td><strong>{{ $rec['title'] }}</strong></td>
                        @if ($rec['task_id'])
                            <td width="70" style="text-align: left;"><span class="badge badge-task">أصبحت مهمة</span></td>
                        @endif
                    </tr>
                </table>
                <p class="muted" style="font-size: 9pt;">{{ $rec['description'] }}</p>
                <p class="tags">
                    {{ $rec['impact_label'] }} — {{ $rec['effort_label'] }}@if (! empty($rec['timeframe'])) — المدة: {{ $rec['timeframe'] }}@endif @if (! empty($rec['kpi_hint'])) — المؤشر: {{ $rec['kpi_hint'] }}@endif
                </p>

                {{-- الخطوات والمثال في المطبوع أيضًا: التقرير الذي يُطبع ويُقرأ
                     على الطاولة هو أكثر نسخة يُنفَّذ منها، فحذفهما منه يفرغه. --}}
                @if (! empty($rec['action_steps']))
                    <ol class="rec-steps">
                        @foreach ($rec['action_steps'] as $step)
                            <li>{{ $step }}</li>
                        @endforeach
                    </ol>
                @endif

                @if (! empty($rec['worked_example']['body']))
                    <div class="rec-example">
                        <p class="rec-example__head">
                            {{ $rec['worked_example']['kind_label'] ?? 'مثال جاهز' }}:
                            {{ $rec['worked_example']['title'] ?? '' }}
                            <span class="badge badge-assumption">فرضية</span>
                        </p>
                        <pre class="rec-example__body">{{ $rec['worked_example']['body'] }}</pre>
                        @if (! empty($rec['worked_example']['notes']))
                            <ul class="rec-example__notes">
                                @foreach ($rec['worked_example']['notes'] as $note)
                                    <li>{{ $note }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@empty
    <div class="info-box">
        <h3>لم يكتمل التحليل الموسّع هذه المرة</h3>
        <p class="muted">درجتك وإجاباتك محفوظة. يمكنك طلب التحليل مرة أخرى من دون إعادة كتابة المعلومات.</p>
    </div>
@endforelse

{{-- تفاصيل التحليل: نفس أقسام الشاشة بكل أنواعها --}}
<h2>تفاصيل التحليل</h2>
@foreach ($report['sections'] as $section)
    @php($content = $section['content'] ?? [])
    <div class="section-box print-section print-section--long">
        <h3>{{ $section['title'] }}</h3>

        @if ($section['key'] === 'score')
            {{-- تفصيل الدرجة: كيف احتُسبت نقطة نقطة --}}
            @if (! empty($content['method']))
                <p class="muted" style="font-size: 9pt;">{{ $content['method'] }}</p>
            @endif
            @if (! empty($content['weights_basis']))
                <p class="muted" style="font-size: 9pt;">فرضية منهجية — {{ $content['weights_basis'] }}</p>
            @endif
            @if (! empty($content['weights_scale']))
                <p class="muted" style="font-size: 8.5pt;">{{ $content['weights_scale'] }}</p>
            @endif
            <table width="100%" cellpadding="0" cellspacing="0" class="kv">
                @foreach ($content['breakdown'] ?? [] as $row)
                    <tr>
                        <td class="muted">{{ $row['label'] ?? '' }}</td>
                        <td class="kv-value">{{ $row['points'] ?? 0 }} / {{ $row['weight'] ?? 0 }}</td>
                    </tr>
                    @if (! empty($row['answer_label']) || isset($row['share']))
                        <tr>
                            <td colspan="2" class="muted" style="font-size: 8.5pt; padding-bottom: 4pt;">
                                @isset($row['share'])({{ $row['share'] }}٪ من الدرجة@if (! empty($row['weight_tier'])) · بند {{ $row['weight_tier'] }}، الأثقل رقم {{ $row['weight_rank'] }} من {{ $row['weight_rank_of'] }}@endif)@endisset
                                @if (! empty($row['answer_label']))
                                    — إجابتك: {{ $row['answer_label'] }} (معامل {{ $row['factor'] ?? 0 }} من 1)
                                @endif
                            </td>
                        </tr>
                    @endif
                @endforeach
            </table>

            @if (! empty($content['excluded']))
                <p class="muted" style="font-size: 8.5pt;">
                    بنود لا تنطبق على المشروع فلم تدخل الحساب:
                    @foreach ($content['excluded'] as $row){{ $row['label'] ?? '' }}@if (! $loop->last) · @endif @endforeach
                </p>
            @endif

        @elseif ($section['key'] === 'competitors')
            @if (! empty($content['intro']))
                <p>{{ $content['intro'] }}</p>
            @endif

            @if (! empty($content['confirmed']))
                <h4>منافسوك (الأقرب أثرًا أولًا)</h4>
                <p>
                    @foreach ($content['confirmed'] as $competitor)
                        <span class="chip chip-{{ $competitor['tier'] ?? 'local' }}">{{ $competitor['tier_label'] ?? '' }}</span>
                        <span>{{ $competitor['name'] }}</span>@if (! empty($competitor['url'])) <span class="muted" style="font-size: 8pt;">({{ $competitor['url'] }})</span>@endif
                        @if (! $loop->last) &nbsp;—&nbsp; @endif
                    @endforeach
                </p>
            @endif

            @if (! empty($content['prompt_local']))
                <div class="warn-box" style="margin: 6pt 0;">
                    <p style="font-size: 9pt; margin: 0;">لم تسمِّ منافسيك المحليين بعد — وهم الأهم. من يأخذ عملاءك في مدينتك يوجّه خطتك أكثر من أي علامة بعيدة. أضِفهم من صفحة التقرير.</p>
                </div>
            @endif

            @if (! empty($content['candidates']))
                <h4>مرشّحون اكتشفناهم — أكّد من ينافسك فعلًا</h4>
                <p>
                    @foreach ($content['candidates'] as $candidate)
                        <span class="chip">{{ $candidate['tier_label'] ?? '' }}</span>
                        <span>{{ $candidate['name'] }}</span>@if (! $loop->last) &nbsp;—&nbsp; @endif
                    @endforeach
                </p>
            @endif

            @if (! empty($content['watchlist']))
                <h4>أين ترى إعلاناتهم</h4>
                <table width="100%" cellpadding="0" cellspacing="0">
                    @foreach ($content['watchlist'] as $view)
                        <tr class="watch-row">
                            <td>
                                <strong>{{ $view['platforms'] ?? '' }}</strong>
                                <div class="muted" style="font-size: 8pt;">{{ $view['what'] ?? '' }}</div>
                            </td>
                            <td class="watch-source">{{ $view['source'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif

            @if (! empty($content['look_for']))
                <h4>ماذا تبحث عنه في كل مكتبة</h4>
                <ul>
                    @foreach ($content['look_for'] as $point)
                        <li>{{ $point }}</li>
                    @endforeach
                </ul>
            @endif

        @else
            @if (! empty($content['headline']))
                <p><strong>{{ $content['headline'] }}</strong></p>
            @endif
            <ul>
                @foreach ($content['points'] ?? [] as $point)
                    <li>{{ $point['text'] ?? '' }}@if ($point['is_assumption'] ?? false) <span class="badge badge-assumption">افتراض</span>@endif</li>
                @endforeach
            </ul>
        @endif
    </div>
@endforeach

{{-- الخطوة المقترحة تاليًا: نفس بطاقة الشاشة --}}
@if (! empty($suggestion))
    <h2>إلى أين بعد هذا؟</h2>
    <div class="next-step" style="margin-top: 0;">
        <h3>{{ $suggestion['tool']->title }}</h3>
        <p class="muted">{{ $suggestion['reason'] }}</p>
        <p style="font-size: 9pt;">ابدأها من حسابك — إجاباتك السابقة نملؤها لك تلقائيًا.</p>
    </div>
@endif

{{-- توقيع صادق في نهاية التقرير اليدوي. --}}
@if (! empty($report['is_manually_reviewed']))
    <p class="report-sign">راجعه وكتبه بنفسه: {{ $report['reviewer_name'] }}@if ($report['reviewed_at']) · {{ $report['reviewed_at'] }}@endif</p>
@endif

</body>
</html>
