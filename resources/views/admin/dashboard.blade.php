@extends('layouts.admin', ['title' => 'لوحة الإدارة', 'pageTitle' => 'لوحة الإدارة', 'pageKicker' => 'Dashboard'])

@php
    $primaryAlert = $operationalAlerts[0] ?? null;
    $moreAlerts = array_slice($operationalAlerts, 1);

    $arrowUp = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7M17 7H9M17 7V15"/></svg>';
    $arrowDown = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7l10 10M17 17H9M17 17V9"/></svg>';

    $trendPill = function (?array $t) use ($arrowUp, $arrowDown): string {
        if (! $t) {
            return '';
        }
        $dir = $t['direction'];
        $icon = $dir === 'down' ? $arrowDown : ($dir === 'up' ? $arrowUp : '');
        $val = rtrim(rtrim(number_format($t['pct'], 1), '0'), '.');
        return '<span class="ta-trend ta-trend--'.$dir.'">'.$icon.$val.'%</span>';
    };
@endphp

@section('content')

<div class="ta-dash">

    <section class="ta-pagehead">
        <div>
            <h2>{{ auth()->user()->name }}، مركز التحكم</h2>
            <p>إدارة المنصة · {{ number_format($stats['accounts']) }} حساب · {{ number_format($stats['users']) }} مستخدم · {{ number_format($targetMeta['active_subscriptions']) }} اشتراك نشط</p>
        </div>
        <div class="ta-pagehead-actions">
            <a href="{{ route('admin.users.create') }}" class="btn btn-secondary btn-sm">مستخدم</a>
            <a href="{{ route('admin.accounts.create') }}" class="btn btn-secondary btn-sm">حساب</a>
            <a href="{{ route('admin.workspaces.create') }}" class="btn btn-secondary btn-sm">مساحة</a>
            <a href="{{ route('admin.plans.create') }}" class="btn btn-primary btn-sm">خطة</a>
        </div>
    </section>

    @if ($primaryAlert)
        <section class="dash-spotlight">
            <div class="dash-spotlight-glow"></div>
            <div class="dash-spotlight-content">
                <span class="dash-spotlight-label">تنبيه تشغيلي</span>
                <h3 class="dash-spotlight-title">{{ $primaryAlert['title'] }}</h3>
                <p>{{ $primaryAlert['body'] }}</p>
            </div>
            <a href="{{ $primaryAlert['url'] }}" class="btn btn-primary btn-lg dash-spotlight-cta">معالجة</a>
        </section>
    @endif

    {{-- ═══ بطاقات المؤشرات ═══ --}}
    <section class="ta-metrics">
        <article class="ta-metric">
            <span class="ta-metric-icon ta-icon--indigo">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </span>
            <div class="ta-metric-foot">
                <div>
                    <span class="ta-metric-label">المستخدمون</span>
                    <strong class="ta-metric-value">{{ number_format($stats['users']) }}</strong>
                </div>
                {!! $trendPill($trends['users'] ?? null) !!}
            </div>
        </article>

        <article class="ta-metric">
            <span class="ta-metric-icon ta-icon--teal">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </span>
            <div class="ta-metric-foot">
                <div>
                    <span class="ta-metric-label">الحسابات</span>
                    <strong class="ta-metric-value">{{ number_format($stats['accounts']) }}</strong>
                </div>
                {!! $trendPill($trends['accounts'] ?? null) !!}
            </div>
        </article>

        <article class="ta-metric">
            <span class="ta-metric-icon ta-icon--violet">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </span>
            <div class="ta-metric-foot">
                <div>
                    <span class="ta-metric-label">مساحات العمل</span>
                    <strong class="ta-metric-value">{{ number_format($stats['workspaces']) }}</strong>
                </div>
            </div>
        </article>

        <article class="ta-metric">
            <span class="ta-metric-icon ta-icon--sky">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </span>
            <div class="ta-metric-foot">
                <div>
                    <span class="ta-metric-label">تشغيل الأدوات</span>
                    <strong class="ta-metric-value">{{ number_format($stats['tool_runs']) }}</strong>
                </div>
                {!! $trendPill($trends['tool_runs'] ?? null) !!}
            </div>
        </article>

        <article class="ta-metric">
            <span class="ta-metric-icon ta-icon--amber">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </span>
            <div class="ta-metric-foot">
                <div>
                    <span class="ta-metric-label">مخرجات الذكاء</span>
                    <strong class="ta-metric-value">{{ number_format($stats['ai_generations']) }}</strong>
                </div>
                {!! $trendPill($trends['ai_generations'] ?? null) !!}
            </div>
        </article>

        <article class="ta-metric">
            <span class="ta-metric-icon ta-icon--rose">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
            </span>
            <div class="ta-metric-foot">
                <div>
                    <span class="ta-metric-label">المشاريع</span>
                    <strong class="ta-metric-value">{{ number_format($stats['projects']) }}</strong>
                </div>
            </div>
        </article>
    </section>

    {{-- ═══ مخطط النشاط الشهري + عدّاد الهدف ═══ --}}
    <section class="ta-cols">
        <article class="ta-panel">
            <div class="ta-panel-head">
                <div>
                    <div class="ta-panel-title">نشاط المنصة الشهري</div>
                    <div class="ta-panel-sub">عدد مرات تشغيل الأدوات خلال آخر ٨ أشهر</div>
                </div>
                <a href="{{ route('admin.tool-runs.index') }}" class="btn btn-ghost btn-sm">السجل الكامل</a>
            </div>
            <div class="ta-chart ta-chart--pad" data-chart-key="sales"></div>
        </article>

        <article class="ta-panel ta-target">
            <div class="ta-panel-head">
                <div>
                    <div class="ta-panel-title">الهدف الشهري</div>
                    <div class="ta-panel-sub">تشغيلات هذا الشهر مقابل الهدف</div>
                </div>
            </div>
            <div class="ta-target-chart">
                <div class="ta-chart" data-chart-key="target"></div>
            </div>
            <p class="ta-target-caption">
                أنجزتَ {{ number_format($targetMeta['month']) }} تشغيلاً هذا الشهر، والهدف {{ number_format($targetMeta['goal']) }} تشغيل. واصل الوتيرة.
            </p>
            <div class="ta-target-stats">
                <div class="ta-target-stat">
                    <span>اليوم</span>
                    <strong>{{ number_format($targetMeta['today']) }}</strong>
                </div>
                <div class="ta-target-stat">
                    <span>هذا الشهر</span>
                    <strong>{{ number_format($targetMeta['month']) }}</strong>
                </div>
                <div class="ta-target-stat">
                    <span>الهدف</span>
                    <strong>{{ number_format($targetMeta['goal']) }}</strong>
                </div>
            </div>
        </article>
    </section>

    {{-- ═══ مخطط النمو ═══ --}}
    <article class="ta-panel">
        <div class="ta-panel-head">
            <div>
                <div class="ta-panel-title">نمو المنصة</div>
                <div class="ta-panel-sub">المستخدمون والحسابات الجدد شهرياً</div>
            </div>
            <a href="{{ route('admin.accounts.index') }}" class="btn btn-ghost btn-sm">الحسابات</a>
        </div>
        <div class="ta-chart ta-chart--pad" data-chart-key="statistics"></div>
    </article>

    {{-- ═══ آخر تشغيلات الأدوات + جانب ═══ --}}
    <section class="ta-cols-flip">
        <article class="ta-panel">
            <div class="ta-panel-head">
                <div>
                    <div class="ta-panel-title">آخر تشغيلات الأدوات</div>
                    <div class="ta-panel-sub">أحدث ما نُفّذ عبر المنصة</div>
                </div>
                <a href="{{ route('admin.tool-runs.index') }}" class="btn btn-ghost btn-sm">الكل</a>
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
                        @forelse ($recentToolRuns as $run)
                            @php
                                $score = (int) ($run->completeness_score ?? 0);
                                $statusClass = $score >= 80 ? 'success' : ($score >= 40 ? 'warning' : 'danger');
                            @endphp
                            <tr>
                                <td>
                                    <div class="ta-cell-primary">
                                        <span class="ta-cell-avatar">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </span>
                                        <strong>{{ $run->tool?->name ?? $run->tool_code }}</strong>
                                    </div>
                                </td>
                                <td>{{ $run->project?->name ?? '—' }}</td>
                                <td>{{ $run->author?->name ?? 'نظام' }}</td>
                                <td><span class="ta-status ta-status--{{ $statusClass }}">{{ $score }}%</span></td>
                                <td class="ta-side-time">{{ $run->created_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><p class="app-empty">لا توجد تشغيلات بعد.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <div class="ta-dash">
            <article class="ta-panel">
                <div class="ta-panel-head">
                    <div class="ta-panel-title">توزيع الخطط</div>
                    <a href="{{ route('admin.plans.index') }}" class="btn btn-ghost btn-sm">الخطط</a>
                </div>
                <div class="ta-side-list">
                    @forelse ($planDistribution as $plan)
                        <div class="ta-side-item">
                            <span class="ta-side-dot ta-side-dot--teal"></span>
                            <div class="ta-side-body">
                                <strong>{{ $plan->name_ar }}</strong>
                                <span>{{ $plan->code }}</span>
                            </div>
                            <span class="ta-side-time">{{ $plan->subscriptions_count }} اشتراك</span>
                        </div>
                    @empty
                        <p class="app-empty">لا توجد خطط بعد.</p>
                    @endforelse
                </div>
            </article>

            <article class="ta-panel">
                <div class="ta-panel-head">
                    <div class="ta-panel-title">آخر النشاطات الإدارية</div>
                    <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-ghost btn-sm">السجل</a>
                </div>
                <div class="ta-side-list">
                    @forelse ($latestAuditLogs as $log)
                        <div class="ta-side-item">
                            <span class="ta-side-dot ta-side-dot--p"></span>
                            <div class="ta-side-body">
                                <strong>{{ $log->action }}</strong>
                                <span>{{ $log->actor?->email ?? 'نظام' }}</span>
                            </div>
                            <span class="ta-side-time">{{ $log->created_at?->diffForHumans() }}</span>
                        </div>
                    @empty
                        <p class="app-empty">لا يوجد سجل تدقيق بعد.</p>
                    @endforelse
                </div>
            </article>
        </div>
    </section>

</div>

<script type="application/json" id="dashboard-charts-payload">@json($charts)</script>

@endsection
