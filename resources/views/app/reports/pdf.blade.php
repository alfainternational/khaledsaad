@php
    $brand = $branding['color'] ?: '#6366f1';
    $brandName = $branding['name'] ?? 'منصة التسويق الاستراتيجي';
    $sevLabel = ['high' => 'حرج', 'mid' => 'متوسط', 'low' => 'منخفض'];
    $sevColor = ['high' => '#f43f5e', 'mid' => '#f59e0b', 'low' => '#64748b'];
    $diagnosis = $report['diagnosis'] ?? ['problems' => [], 'missing' => []];
    $domainPlans = $report['domain_plans'] ?? [];
    $plan = $report['plan'] ?? [];
    $audit = $report['audit'] ?? [];
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><style>
    body { font-family: dejavusanscondensed, sans-serif; color: #1e293b; font-size: 11px; line-height: 1.7; }
    .cover { border: 2px solid {{ $brand }}; border-radius: 6px; padding: 18px; margin-bottom: 16px; }
    .cover-brand { color: {{ $brand }}; font-weight: bold; font-size: 13px; margin-bottom: 4px; }
    .cover-sub { color: #64748b; font-size: 10px; margin-bottom: 12px; }
    .cover-title { font-size: 20px; font-weight: bold; color: #0f172a; margin-bottom: 6px; }
    .cover-meta { color: #64748b; font-size: 10px; margin-bottom: 12px; }
    .cover-verdict { background: #f1f5f9; border-right: 4px solid {{ $brand }}; padding: 10px 12px; font-size: 11px; }
    .score-box { color: {{ $brand }}; font-size: 26px; font-weight: bold; }
    h2 { color: {{ $brand }}; font-size: 15px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin: 18px 0 10px; }
    h3 { font-size: 12px; color: #0f172a; margin: 12px 0 6px; }
    .kpi-tbl { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    .kpi-tbl td { border: 1px solid #e2e8f0; padding: 8px; text-align: center; width: 25%; }
    .kpi-v { font-size: 18px; font-weight: bold; color: {{ $brand }}; }
    .kpi-l { font-size: 9px; color: #64748b; }
    .diag { border: 1px solid #e2e8f0; border-right: 4px solid #f59e0b; border-radius: 4px; padding: 10px 12px; margin-bottom: 8px; }
    .diag-sev { font-weight: bold; font-size: 9px; padding: 1px 8px; border-radius: 8px; color: #fff; }
    .diag-p { font-weight: bold; font-size: 12px; color: #0f172a; }
    .diag-row { margin-top: 6px; }
    .diag-tag { font-weight: bold; font-size: 9px; }
    .diag-impact { color: {{ $brand }}; font-size: 9px; font-weight: bold; margin-top: 5px; }
    .plan-tbl { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .plan-tbl td { border: 1px solid #e2e8f0; padding: 8px; vertical-align: top; width: 33.3%; }
    .plan-tbl th { background: {{ $brand }}; color: #fff; padding: 6px; font-size: 11px; }
    .domain { border: 1px solid #e2e8f0; border-top: 3px solid {{ $brand }}; border-radius: 4px; padding: 10px 12px; margin-bottom: 8px; }
    .domain-by { color: #94a3b8; font-size: 8px; }
    .domain-goal { color: #64748b; font-size: 10px; margin-bottom: 6px; }
    .domain-h { color: {{ $brand }}; font-weight: bold; font-size: 10px; margin-top: 8px; }
    ul { margin: 4px 18px 4px 0; padding: 0; }
    li { margin-bottom: 3px; font-size: 10px; }
    .muted { color: #94a3b8; font-size: 9px; }
    .foot { border-top: 1px solid #e2e8f0; padding-top: 8px; margin-top: 16px; color: #94a3b8; font-size: 9px; text-align: center; }
</style></head><body>

<div class="cover">
    <div class="cover-brand">{{ $brandName }}</div>
    <div class="cover-sub">وثيقة استراتيجية تسويقية شاملة — مُعدّة للعميل</div>
    <div class="cover-title">{{ $project->name }}</div>
    <div class="cover-meta">
        @if ($report['client'])العميل: {{ $report['client'] }} · @endif
        اكتمال: {{ $report['completion'] }}% · أدوات منجَزة: {{ $report['tools_completed'] }} · {{ now()->translatedFormat('F Y') }}
    </div>
    <div class="cover-verdict"><b>الملخص التنفيذي:</b> {{ $report['executive_summary'] }}</div>
</div>

<table class="kpi-tbl"><tr>
    @if (! is_null($report['content_quality'] ?? null))
        <td><div class="kpi-v">{{ $report['content_quality'] }}%</div><div class="kpi-l">جودة المحتوى</div></td>
    @endif
    <td><div class="kpi-v">{{ $report['completion'] }}%</div><div class="kpi-l">اكتمال المراحل</div></td>
    <td><div class="kpi-v">{{ $report['tools_completed'] }}</div><div class="kpi-l">أدوات منجَزة</div></td>
    <td><div class="kpi-v">{{ count($diagnosis['problems']) }}</div><div class="kpi-l">مشاكل مُشخّصة</div></td>
</tr></table>

@if (! empty($diagnosis['problems']))
<h2>التشخيص: مشاكلك وأسبابها الفعلية وحلولها</h2>
@foreach ($diagnosis['problems'] as $item)
    @php $sev = $item['severity'] ?? 'mid'; @endphp
    <div class="diag" style="border-right-color: {{ $sevColor[$sev] }};">
        <span class="diag-sev" style="background: {{ $sevColor[$sev] }};">{{ $sevLabel[$sev] }}</span>
        <span class="diag-p">{{ $item['problem'] }}</span>
        <div class="diag-row"><span class="diag-tag" style="color:#f43f5e;">السبب الفعلي: </span>{{ $item['cause'] }}</div>
        <div class="diag-row"><span class="diag-tag" style="color:#16a34a;">الحل الواقعي: </span>{{ $item['solution'] }}</div>
        @if (! empty($item['impact']))<div class="diag-impact">الأثر المتوقّع: {{ $item['impact'] }}</div>@endif
    </div>
@endforeach
@endif

@if (! empty($domainPlans))
<h2>الخطط التفصيلية لكل مجال</h2>
@foreach ($domainPlans as $dp)
    <div class="domain">
        <h3>{{ $dp['title'] }} <span class="domain-by">— {{ $dp['by'] }}</span></h3>
        <div class="domain-goal">{{ $dp['goal'] }}</div>
        @foreach ($dp['sections'] as $sec)
            <div class="domain-h">{{ $sec['heading'] }}</div>
            <ul>@foreach ($sec['items'] as $it)<li>{{ $it }}</li>@endforeach</ul>
        @endforeach
    </div>
@endforeach
@endif

@if (! empty($plan['quick_wins_7']) || ! empty($plan['improvements_30']) || ! empty($plan['strategic_90']))
<h2>خارطة التنفيذ (7 / 30 / 90 يوماً)</h2>
<table class="plan-tbl">
    <tr><th>خلال 7 أيام</th><th>خلال 30 يوماً</th><th>خلال 90 يوماً</th></tr>
    <tr>
        <td><ul>@forelse ($plan['quick_wins_7'] ?? [] as $a)<li>{{ $a }}</li>@empty<li class="muted">—</li>@endforelse</ul></td>
        <td><ul>@forelse ($plan['improvements_30'] ?? [] as $a)<li>{{ $a }}</li>@empty<li class="muted">—</li>@endforelse</ul></td>
        <td><ul>@forelse ($plan['strategic_90'] ?? [] as $a)<li>{{ $a }}</li>@empty<li class="muted">—</li>@endforelse</ul></td>
    </tr>
</table>
@endif

<h2>التحليل حسب المراحل الخمس</h2>
@foreach ($report['stages'] as $stage)
    <h3>{{ $stage['label'] }}</h3>
    @if (! empty($stage['items']))
        @foreach ($stage['items'] as $it)
            <div style="margin-bottom:5px;"><b>{{ $it['headline'] }}</b>
            @if (! empty($it['points']))<ul>@foreach ($it['points'] as $pt)<li>{{ $pt }}</li>@endforeach</ul>@endif
            </div>
        @endforeach
    @else
        <div class="muted">لم تُنجَز أدوات هذه المرحلة بعد.</div>
    @endif
@endforeach

@if (! empty($audit['top_problems']))
<h2>التشخيص الفني للموقع</h2>
<ul>@foreach ($audit['top_problems'] as $pr)<li>{{ $pr }}</li>@endforeach</ul>
@endif

<div class="foot">{{ $brandName }} — وثيقة سرّية مُعدّة للعميل · {{ $report['client'] ?: $project->name }}</div>

</body></html>
