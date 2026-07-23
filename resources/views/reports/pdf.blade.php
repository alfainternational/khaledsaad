<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: hacen; color: #1a2233; font-size: 10pt; line-height: 1.8; }
        h1 { color: #ffffff; font-size: 17pt; margin: 0; }
        h2 { color: #071f5b; font-size: 12.5pt; margin: 16pt 0 6pt; border-bottom: 2px solid #2575ff; padding-bottom: 3pt; }
        h3 { color: #071f5b; font-size: 10.5pt; margin: 0 0 3pt; }
        p { margin: 0 0 4pt; }
        .muted { color: #5d6b82; }

        /* --- الغلاف --- */
        .cover { background-color: #071f5b; padding: 16pt 18pt; }
        .cover-sub { color: #b9c6e8; font-size: 9.5pt; margin-top: 3pt; }
        .cover-date { color: #8fa1d0; font-size: 8.5pt; }

        /* --- الدرجة --- */
        .score-panel { border: 1px solid #dfe8f5; background-color: #f5f9ff; padding: 12pt 14pt; }
        .score-num { font-size: 30pt; font-weight: bold; color: #071f5b; line-height: 1.1; }
        .score-den { font-size: 11pt; color: #5d6b82; }
        .band { border: 1px solid #dfe8f5; background-color: #ffffff; padding: 2pt 10pt; border-radius: 10pt; font-size: 9pt; color: #071f5b; }

        .summary-box { background-color: #ffffff; border: 1px solid #dfe8f5; border-right: 3px solid #2575ff; padding: 9pt 12pt; }
        .summary-box p { line-height: 15pt; margin: 0 0 5pt; }

        /* --- بطاقات الرسوم --- */
        .chart-card { border: 1px solid #dfe8f5; border-radius: 6pt; padding: 10pt 12pt; }
        .chart-title { color: #071f5b; font-size: 9.5pt; font-weight: bold; margin: 0 0 7pt; }
        .bar-label { font-size: 8.5pt; color: #38445c; }
        .bar-count { font-size: 8.5pt; color: #071f5b; font-weight: bold; text-align: left; }
        .legend { font-size: 8pt; color: #5d6b82; }
        .dot { display: inline-block; width: 7pt; height: 7pt; border-radius: 3.5pt; }

        /* أعمدة تطور الدرجة */
        .col-value { font-size: 8pt; color: #071f5b; font-weight: bold; }
        .col-label { font-size: 7.5pt; color: #5d6b82; }

        /* مصفوفة الأثر والجهد */
        .matrix td, .matrix th { border: 1px solid #dfe8f5; text-align: center; font-size: 8.5pt; padding: 4pt 2pt; }
        .matrix th { background-color: #f5f9ff; color: #071f5b; }
        .matrix .hot { background-color: #e7f8ef; color: #0a7d4f; font-weight: bold; }
        .matrix .cell-filled { color: #071f5b; font-weight: bold; }
        .matrix .cell-empty { color: #b6c1d4; }

        /* --- الخطوة التالية والنتائج --- */
        .next-step { background-color: #fff8f2; border: 1px solid #f6cfa4; border-right: 3px solid #ff9b27; padding: 9pt 12pt; margin: 10pt 0; }
        .finding { border: 1px solid #dfe8f5; border-radius: 6pt; padding: 10pt 12pt; margin-bottom: 8pt; page-break-inside: avoid; }
        .badge { padding: 1pt 7pt; border-radius: 8pt; font-size: 7.5pt; border: 1px solid #dfe8f5; }
        .badge-critical { background-color: #fdeceb; color: #8d1d13; border-color: #f0b3ad; }
        .badge-high { background-color: #fff2e6; color: #8a4b06; border-color: #f6cfa4; }
        .badge-medium { background-color: #fff9e6; color: #6d5405; }
        .badge-low { background-color: #f5f9ff; color: #5d6b82; }
        .badge-assumption { background-color: #f2f0ff; color: #40339c; border-color: #cfc7f5; }
        .evidence { background-color: #f5f9ff; padding: 5pt 9pt; border-radius: 4pt; font-size: 8.5pt; margin: 5pt 0; }
        .rec { border-top: 1px dashed #dfe8f5; padding-top: 5pt; margin-top: 5pt; }
        .tags { font-size: 8pt; color: #5d6b82; }
        ul { margin: 3pt 0; padding-right: 14pt; }
        .assumptions li { color: #6d5405; }
    </style>
</head>
<body>

{{-- الغلاف --}}
<div class="cover">
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <h1>{{ $report['title'] }}</h1>
                <div class="cover-sub">{{ $report['tool']['title'] }} · {{ $report['project']['name'] }}</div>
            </td>
            <td width="130" style="text-align: left; vertical-align: top;">
                <img src="{{ public_path('assets/brand/khaled-saad-light.png') }}" width="110">
                <div class="cover-date">{{ $generatedAt->locale('ar')->translatedFormat('j F Y') }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- الدرجة والخلاصة --}}
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
        <td style="vertical-align: top; background-color: #ffffff; border: 1px solid #dfe8f5; border-right: 3px solid #2575ff; padding: 9pt 12pt;">
            <h3>الخلاصة</h3>
            <p>{{ $report['summary'] }}</p>
            <p class="muted" style="font-size: 8.5pt; margin: 4pt 0 0;">
                {{ $report['counts']['evidence_backed'] }} نتيجة مبنية على ما كتبته،
                و{{ $report['counts']['assumptions'] }} اجتهاد يحتاج تأكيدًا منك.
            </p>
        </td>
    </tr>
</table>

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
                    <table width="100%" cellpadding="0" cellspacing="0" style="border-radius: 4pt;">
                        <tr>
                            @foreach ($charts['evidence_split']['items'] as $item)
                                @if ($item['count'] > 0)
                                    <td width="{{ (int) round($item['count'] * 100 / max(1, $charts['evidence_split']['total'])) }}%"
                                        style="background-color: {{ $item['color'] }}; height: 11pt; text-align: center; color: #ffffff; font-size: 8pt; border-radius: 3pt;">
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
                        <table width="100%" cellpadding="0" cellspacing="0" class="matrix">
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
@if (!empty($report['next_step']))
    <div class="next-step">
        <h3>الخطوة التالية: {{ $report['next_step']['title'] ?? '' }}</h3>
        <p>{{ $report['next_step']['description'] ?? '' }}</p>
    </div>
@endif

{{-- النتائج والتوصيات --}}
<h2>النتائج والتوصيات</h2>
@forelse ($report['findings'] as $finding)
    <div class="finding">
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

        @if (!empty($finding['evidence']))
            <div class="evidence"><strong>الدليل:</strong> {{ $finding['evidence'] }}</div>
        @endif

        @foreach ($finding['recommendations'] as $rec)
            <div class="rec">
                <strong>{{ $rec['title'] }}</strong>
                <p class="muted" style="font-size: 9pt;">{{ $rec['description'] }}</p>
                <p class="tags">
                    {{ $rec['impact_label'] }} · {{ $rec['effort_label'] }}@if (!empty($rec['kpi_hint'])) · المؤشر: {{ $rec['kpi_hint'] }}@endif
                </p>
            </div>
        @endforeach
    </div>
@empty
    <p class="muted">لم تُنتَج نتائج موسعة في هذا التقرير.</p>
@endforelse

{{-- الافتراضات --}}
@if (!empty($report['assumptions']))
    <h2>ما لم يُتحقق منه</h2>
    <ul class="assumptions">
        @foreach ($report['assumptions'] as $assumption)
            <li>{{ $assumption }}</li>
        @endforeach
    </ul>
@endif

{{-- تفاصيل التحليل --}}
<h2>تفاصيل التحليل</h2>
@foreach ($report['sections'] as $section)
    @if ($section['key'] !== 'score')
        <h3 style="margin-top: 9pt;">{{ $section['title'] }}</h3>
        @if (!empty($section['content']['headline']))
            <p><strong>{{ $section['content']['headline'] }}</strong></p>
        @endif
        <ul>
            @foreach ($section['content']['points'] ?? [] as $point)
                <li>{{ $point['text'] ?? '' }}@if ($point['is_assumption'] ?? false) <em class="muted">(اجتهاد)</em>@endif</li>
            @endforeach
        </ul>
    @endif
@endforeach

</body>
</html>
