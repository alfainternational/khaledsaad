@php
    $brand = $branding['color'] ?: '#6366f1';
    $brandName = $branding['name'] ?? 'منصة التسويق الاستراتيجي';
    $meta = $dossier['meta'];
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><style>
    body { font-family: dejavusanscondensed, sans-serif; color: #1e293b; font-size: 11px; line-height: 1.7; }
    .cover { border: 2px solid {{ $brand }}; border-radius: 6px; padding: 18px; margin-bottom: 16px; }
    .cover-brand { color: {{ $brand }}; font-weight: bold; font-size: 13px; margin-bottom: 4px; }
    .cover-sub { color: #64748b; font-size: 10px; margin-bottom: 12px; }
    .cover-title { font-size: 20px; font-weight: bold; color: #0f172a; margin-bottom: 6px; }
    .cover-meta { color: #64748b; font-size: 10px; }
    h2 { color: {{ $brand }}; font-size: 15px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin: 18px 0 8px; }
    .stage-desc { color: #64748b; font-size: 10px; margin-bottom: 8px; }
    .tool { border: 1px solid #e2e8f0; border-top: 3px solid {{ $brand }}; border-radius: 4px; padding: 9px 12px; margin-bottom: 8px; }
    .tool-head { font-weight: bold; font-size: 12px; color: #0f172a; }
    .tool-meta { color: #94a3b8; font-size: 8px; }
    .tool-headline { color: #334155; font-size: 10px; margin: 4px 0 6px; }
    .qa { margin-top: 4px; }
    .q { font-weight: bold; font-size: 10px; color: #0f172a; }
    .a { color: #334155; font-size: 10px; margin: 1px 0 6px; padding-right: 8px; border-right: 2px solid #e2e8f0; }
    .missing { color: #b45309; font-size: 9px; margin-top: 4px; }
    .muted { color: #94a3b8; font-size: 9px; }
    .foot { border-top: 1px solid #e2e8f0; padding-top: 8px; margin-top: 16px; color: #94a3b8; font-size: 9px; text-align: center; }
</style></head><body>

<div class="cover">
    <div class="cover-brand">{{ $brandName }}</div>
    <div class="cover-sub">دليل المشروع — الإجابات الخام مجمّعة (وثيقة مرجعية)</div>
    <div class="cover-title">{{ $project->name }}</div>
    <div class="cover-meta">
        @if ($meta['client'])العميل: {{ $meta['client'] }} · @endif
        @if ($meta['sector'])القطاع: {{ $meta['sector'] }} · @endif
        اكتمال: {{ $meta['completion'] }}% · أدوات منجَزة: {{ $meta['tools_completed'] }} · {{ now()->translatedFormat('F Y') }}
    </div>
</div>

@if (! $dossier['has_answers'])
    <p class="muted">لم تُنجَز أي أداة بعد لهذا المشروع.</p>
@endif

@foreach ($dossier['stages'] as $stage)
    @php if (empty($stage['tools']) && empty($stage['missing'])) continue; @endphp
    <h2>المرحلة {{ $stage['num'] }}: {{ $stage['label'] }} ({{ $stage['completion'] }}%)</h2>
    <div class="stage-desc">{{ $stage['description'] }}</div>

    @foreach ($stage['tools'] as $tool)
        <div class="tool">
            <div class="tool-head">{{ $tool['name'] }}
                <span class="tool-meta">@if ($tool['answered_at']){{ $tool['answered_at'] }} · @endif اكتمال {{ $tool['completeness'] }}%</span>
            </div>
            @if ($tool['headline'] !== '')<div class="tool-headline"><b>الخلاصة:</b> {{ $tool['headline'] }}</div>@endif
            @if (! empty($tool['answers']))
                <div class="qa">
                    @foreach ($tool['answers'] as $answer)
                        <div class="q">{{ $answer['label'] }}</div>
                        <div class="a">{{ $answer['value'] }}</div>
                    @endforeach
                </div>
            @else
                <div class="muted">لا توجد إجابات نصّية مسجّلة.</div>
            @endif
        </div>
    @endforeach

    @if (! empty($stage['missing']))
        <div class="missing">أدوات أساسية ناقصة: {{ implode('، ', $stage['missing']) }}.</div>
    @endif
@endforeach

<div class="foot">{{ $brandName }} · دليل مرجعي — الإجابات كما أُدخلت</div>
</body></html>
