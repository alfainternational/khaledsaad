{{-- المؤشرات البصرية للتقرير — تُبنى من report.charts (نفس مصدر التطبيق والـPDF) --}}
@php($charts = $report['charts'] ?? null)

@if ($charts !== null)
    <section aria-labelledby="charts-heading">
        <h2 id="charts-heading" class="section-title">المؤشرات في لمحة</h2>

        <div class="chart-grid">
            {{-- عدّاد الدرجة --}}
            <article class="card chart">
                <h3>{{ $charts['score_gauge']['title'] }}</h3>
                <div class="gauge" role="img" aria-label="الدرجة {{ $charts['score_gauge']['value'] }} من {{ $charts['score_gauge']['max'] }}">
                    @php($circumference = 2 * M_PI * 52)
                    @php($filled = $charts['score_gauge']['value'] / max(1, $charts['score_gauge']['max']) * $circumference)
                    <svg viewBox="0 0 120 120" aria-hidden="true">
                        <circle cx="60" cy="60" r="52" class="gauge__track" />
                        <circle cx="60" cy="60" r="52" class="gauge__fill"
                            style="stroke: {{ $charts['score_gauge']['color'] }}; stroke-dasharray: {{ round($filled, 1) }} {{ round($circumference, 1) }};"
                            transform="rotate(-90 60 60)" />
                    </svg>
                    <p class="gauge__value">{{ $charts['score_gauge']['value'] }}<small>/{{ $charts['score_gauge']['max'] }}</small></p>
                </div>
                <p class="score-chip">{{ $charts['score_gauge']['band'] }}</p>
            </article>

            {{-- توزيع الخطورة --}}
            @if ($charts['severity_distribution'] !== null)
                <article class="card chart">
                    <h3>{{ $charts['severity_distribution']['title'] }}</h3>
                    <ul class="hbar-list">
                        @foreach ($charts['severity_distribution']['items'] as $item)
                            <li>
                                <span class="hbar-list__label">{{ $item['label'] }}</span>
                                <span class="hbar-list__track">
                                    <span class="hbar-list__fill" style="inline-size: {{ max(5, (int) round($item['count'] * 100 / max(1, $charts['severity_distribution']['total']))) }}%; background: {{ $item['color'] }};"></span>
                                </span>
                                <b class="hbar-list__count">{{ $item['count'] }}</b>
                            </li>
                        @endforeach
                    </ul>
                </article>
            @endif

            {{-- الدليل مقابل الاجتهاد --}}
            @if ($charts['evidence_split'] !== null)
                <article class="card chart">
                    <h3>{{ $charts['evidence_split']['title'] }}</h3>
                    <div class="stacked-bar" role="img" aria-label="{{ $charts['evidence_split']['title'] }}">
                        @foreach ($charts['evidence_split']['items'] as $item)
                            @if ($item['count'] > 0)
                                <span style="flex: {{ $item['count'] }}; background: {{ $item['color'] }};">{{ $item['count'] }}</span>
                            @endif
                        @endforeach
                    </div>
                    <ul class="chart-legend">
                        @foreach ($charts['evidence_split']['items'] as $item)
                            <li><i style="background: {{ $item['color'] }};"></i> {{ $item['label'] }} ({{ $item['count'] }})</li>
                        @endforeach
                    </ul>
                </article>
            @endif

            {{-- تطور الدرجة --}}
            @if ($charts['score_history'] !== null)
                <article class="card chart">
                    <h3>{{ $charts['score_history']['title'] }}</h3>
                    <div class="col-chart" role="img" aria-label="{{ $charts['score_history']['title'] }}">
                        @foreach ($charts['score_history']['points'] as $point)
                            <div class="col-chart__item">
                                <b>{{ $point['value'] }}</b>
                                <span @class(['col-chart__bar', 'is-current' => $point['is_current']]) style="block-size: {{ max(4, (int) round($point['value'] * 100 / max(1, $charts['score_history']['max']))) }}%;"></span>
                                <small>{{ $point['label'] }}</small>
                            </div>
                        @endforeach
                    </div>
                </article>
            @endif

            {{-- مصفوفة الأثر والجهد --}}
            @if ($charts['impact_effort'] !== null)
                <article class="card chart chart--wide">
                    <h3>{{ $charts['impact_effort']['title'] }}</h3>
                    <div class="table-wrap">
                        <table class="matrix-table">
                            <thead>
                                <tr>
                                    <th></th>
                                    @foreach (['low', 'medium', 'high'] as $effort)
                                        <th>{{ $charts['impact_effort']['effort_labels'][$effort] }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (['high', 'medium', 'low'] as $impact)
                                    <tr>
                                        <th>{{ $charts['impact_effort']['impact_labels'][$impact] }}</th>
                                        @foreach (['low', 'medium', 'high'] as $effort)
                                            @php($cell = collect($charts['impact_effort']['cells'])->first(fn ($c) => $c['impact'] === $impact && $c['effort'] === $effort))
                                            <td @class(['is-hot' => $impact === 'high' && $effort === 'low', 'is-empty' => $cell['count'] === 0])>
                                                {{ $cell['count'] > 0 ? $cell['count'] : '—' }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($charts['impact_effort']['quick_wins'] > 0)
                        <p class="muted">ابدأ من الخلية الخضراء: {{ $charts['impact_effort']['quick_wins'] }} توصية بأثر عالٍ وجهد بسيط.</p>
                    @endif
                </article>
            @endif
        </div>
    </section>
@endif
