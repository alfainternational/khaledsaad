{{--
    Shared TailAdmin-style ecommerce dashboard.
    Rendered identically by the admin (/admin) and user (/dashboard) dashboards.
    All content comes from the $dash view-model built in the controller.
    Contract keys: head, banner, metrics, sales, target, statistics, distribution, table, charts.
--}}
@php
    $icons = [
        'users'     => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
        'account'   => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
        'bolt'      => 'M13 10V3L4 14h7v7l9-11h-7z',
        'tool'      => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
        'check'     => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        'folder'    => 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
        'clipboard' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
        'chart'     => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
    ];
    $icon = function (string $name) use ($icons): string {
        $d = $icons[$name] ?? $icons['chart'];
        return '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="'.$d.'"/></svg>';
    };

    $arrowUp = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7M17 7H9M17 7V15"/></svg>';
    $arrowDown = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7l10 10M17 17H9M17 17V9"/></svg>';

    $fmtPct = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');

    $pill = function (?array $t) use ($arrowUp, $arrowDown, $fmtPct): string {
        // Hide flat / zero trends — an empty "0%" pill reads as broken.
        if (! $t || ($t['direction'] ?? 'flat') === 'flat' || (float) ($t['pct'] ?? 0) === 0.0) {
            return '';
        }
        $dir = $t['direction'];
        $ico = $dir === 'down' ? $arrowDown : $arrowUp;
        return '<span class="ta-trend ta-trend--'.$dir.'">'.$ico.$fmtPct($t['pct']).'%</span>';
    };

    $head = $dash['head'];
    $banner = $dash['banner'] ?? null;
    $metrics = $dash['metrics'] ?? [];
    $sales = $dash['sales'];
    $target = $dash['target'];
    $statistics = $dash['statistics'];
    $distribution = $dash['distribution'];
    $table = $dash['table'];
@endphp

<div class="ta-dash">

    {{-- ═══ Page head ═══ --}}
    <section class="ta-pagehead">
        <div>
            <h2>{{ $head['title'] }}</h2>
            <p>{{ $head['subtitle'] }}</p>
        </div>
        @if (! empty($head['actions']))
            <div class="ta-pagehead-actions">
                @foreach ($head['actions'] as $action)
                    <a href="{{ $action['route'] }}" class="btn btn-{{ $action['variant'] ?? 'secondary' }} btn-sm">{{ $action['label'] }}</a>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ═══ Banner (alert / next step) ═══ --}}
    @if ($banner)
        <section class="eco-banner">
            <div class="eco-banner-body">
                <span class="eco-banner-label">{{ $banner['label'] }}</span>
                <h3 class="eco-banner-title">{{ $banner['title'] }}</h3>
                <p>{{ $banner['body'] }}</p>
            </div>
            @if (! empty($banner['cta']))
                <a href="{{ $banner['cta']['route'] }}" class="btn btn-primary btn-lg">{{ $banner['cta']['label'] }}</a>
            @endif
        </section>
    @endif

    {{-- ═══ Row: metrics + sales (7) | target (5) ═══ --}}
    <div class="eco-grid">
        <div class="eco-span-7 eco-stack">
            <div class="eco-metric-pair">
                @foreach ($metrics as $m)
                    <article class="ta-metric">
                        <span class="ta-metric-icon ta-icon--{{ $m['tint'] ?? 'indigo' }}">{!! $icon($m['icon'] ?? 'chart') !!}</span>
                        <div class="ta-metric-foot">
                            <div>
                                <span class="ta-metric-label">{{ $m['label'] }}</span>
                                <strong class="ta-metric-value">{{ $m['value'] }}</strong>
                            </div>
                            {!! $pill($m['trend'] ?? null) !!}
                        </div>
                    </article>
                @endforeach
            </div>

            <article class="ta-panel">
                <div class="ta-panel-head">
                    <div>
                        <div class="ta-panel-title">{{ $sales['title'] }}</div>
                        <div class="ta-panel-sub">{{ $sales['sub'] }}</div>
                    </div>
                    @if (! empty($sales['link']))
                        <a href="{{ $sales['link']['route'] }}" class="btn btn-ghost btn-sm">{{ $sales['link']['label'] }}</a>
                    @endif
                </div>
                <div class="ta-chart ta-chart--pad" data-chart-key="sales"></div>
            </article>
        </div>

        <div class="eco-span-5">
            <article class="ta-panel ta-target">
                <div class="ta-panel-head">
                    <div>
                        <div class="ta-panel-title">{{ $target['title'] }}</div>
                        <div class="ta-panel-sub">{{ $target['sub'] }}</div>
                    </div>
                    {!! $pill($target['badge'] ?? null) !!}
                </div>
                <div class="ta-target-chart">
                    <div class="ta-chart" data-chart-key="target"></div>
                </div>
                <p class="ta-target-caption">{{ $target['caption'] }}</p>
                <div class="ta-target-stats">
                    @foreach ($target['stats'] as $stat)
                        <div class="ta-target-stat">
                            <span>{{ $stat['label'] }}</span>
                            <strong>
                                <span class="ta-stat-value">
                                    {{ $stat['value'] }}
                                    @if (($stat['direction'] ?? null) === 'up')
                                        <span class="ta-stat-arrow ta-arrow-up">{!! $arrowUp !!}</span>
                                    @elseif (($stat['direction'] ?? null) === 'down')
                                        <span class="ta-stat-arrow ta-arrow-down">{!! $arrowDown !!}</span>
                                    @endif
                                </span>
                            </strong>
                        </div>
                    @endforeach
                </div>
            </article>
        </div>
    </div>

    {{-- ═══ Statistics area (full) with tabs ═══ --}}
    <article class="ta-panel">
        <div class="ta-panel-head">
            <div>
                <div class="ta-panel-title">{{ $statistics['title'] }}</div>
                <div class="ta-panel-sub">{{ $statistics['sub'] }}</div>
            </div>
            @if (! empty($statistics['tabs']))
                <div class="ta-stat-tabs">
                    <div class="ta-segment" data-chart-tabs="statistics">
                        @foreach ($statistics['tabs'] as $i => $tabLabel)
                            <button type="button" data-tab-index="{{ $i }}" class="{{ $i === 0 ? 'is-active' : '' }}">{{ $tabLabel }}</button>
                        @endforeach
                    </div>
                    @if (! empty($statistics['range']))
                        <span class="ta-daterange">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            {{ $statistics['range'] }}
                        </span>
                    @endif
                </div>
            @endif
        </div>
        <div class="ta-chart ta-chart--pad" data-chart-key="statistics"></div>
    </article>

    {{-- ═══ Row: distribution (5) | table (7) ═══ --}}
    <div class="eco-grid">
        <div class="eco-span-5">
            <article class="ta-panel">
                <div class="ta-panel-head">
                    <div>
                        <div class="ta-panel-title">{{ $distribution['title'] }}</div>
                        <div class="ta-panel-sub">{{ $distribution['sub'] ?? '' }}</div>
                    </div>
                    @if (! empty($distribution['link']))
                        <a href="{{ $distribution['link']['route'] }}" class="btn btn-ghost btn-sm">{{ $distribution['link']['label'] }}</a>
                    @endif
                </div>
                <div class="ta-dist">
                    @forelse ($distribution['items'] as $item)
                        @php $pct = max(0, min(100, (int) round($item['pct'] ?? 0))); $w = (int) (round($pct / 5) * 5); @endphp
                        <div class="ta-dist-item">
                            <div class="ta-dist-head">
                                <div class="ta-dist-name">
                                    <span class="ta-dist-flag">{{ $item['initials'] ?? '•' }}</span>
                                    <div>
                                        <strong>{{ $item['name'] }}</strong>
                                        <span>{{ $item['sub'] ?? '' }}</span>
                                    </div>
                                </div>
                                <span class="ta-dist-pct">{{ $pct }}%</span>
                            </div>
                            <div class="ta-dist-bar"><div class="ta-dist-fill ta-w-{{ $w }}"></div></div>
                        </div>
                    @empty
                        <p class="app-empty">لا توجد بيانات بعد.</p>
                    @endforelse
                </div>
            </article>
        </div>

        <div class="eco-span-7">
            <article class="ta-panel">
                <div class="ta-panel-head">
                    <div>
                        <div class="ta-panel-title">{{ $table['title'] }}</div>
                        <div class="ta-panel-sub">{{ $table['sub'] ?? '' }}</div>
                    </div>
                    @if (! empty($table['link']))
                        <a href="{{ $table['link']['route'] }}" class="btn btn-ghost btn-sm">{{ $table['link']['label'] }}</a>
                    @endif
                </div>
                <div class="ta-table-wrap">
                    <table class="ta-table">
                        <thead>
                            <tr>
                                <th>الأداة</th>
                                <th>المشروع</th>
                                <th>المنفِّذ</th>
                                <th>الاكتمال</th>
                                <th>الوقت</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($table['rows'] as $row)
                                @php
                                    $score = (int) ($row['score'] ?? 0);
                                    $statusClass = $score >= 80 ? 'success' : ($score >= 40 ? 'warning' : 'danger');
                                @endphp
                                <tr>
                                    <td>
                                        <div class="ta-cell-primary">
                                            <span class="ta-cell-avatar">{!! $icon('tool') !!}</span>
                                            <strong>{{ $row['title'] }}</strong>
                                        </div>
                                    </td>
                                    <td>{{ $row['project'] ?? '—' }}</td>
                                    <td>{{ $row['author'] ?? 'نظام' }}</td>
                                    <td><span class="ta-status ta-status--{{ $statusClass }}">{{ $score }}%</span></td>
                                    <td class="ta-side-time">{{ $row['time'] ?? '' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><p class="app-empty">لا توجد تشغيلات بعد.</p></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        </div>
    </div>

</div>

<script type="application/json" id="dashboard-charts-payload">@json($dash['charts'])</script>
