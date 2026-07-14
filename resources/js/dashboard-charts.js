import ApexCharts from 'apexcharts';

/**
 * TailAdmin-style dashboard charts.
 *
 * Reads a JSON payload from <script type="application/json" id="dashboard-charts-payload">
 * and renders one ApexChart per entry into the element `[data-chart-key="<key>"]`.
 * Charts are theme-aware (colors pulled from CSS custom properties) and re-render
 * automatically when the theme toggles (listens for the `ks:themechange` event).
 */

const registry = new Map();

function cssVar(name) {
    const value = getComputedStyle(document.documentElement).getPropertyValue(name);
    return value ? value.trim() : '';
}

function resolveColor(token) {
    if (typeof token !== 'string') {
        return '#6366f1';
    }
    return token.startsWith('--') ? (cssVar(token) || '#6366f1') : token;
}

function palette() {
    const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
    return {
        isDark,
        text: cssVar('--text') || (isDark ? '#f1f5f9' : '#0f172a'),
        muted: cssVar('--text-muted') || '#64748b',
        grid: isDark ? 'rgba(255,255,255,0.08)' : 'rgba(15,23,42,0.08)',
        track: isDark ? 'rgba(255,255,255,0.08)' : 'rgba(15,23,42,0.06)',
        tooltipTheme: isDark ? 'dark' : 'light',
    };
}

function baseFont() {
    return "'Cairo', 'Segoe UI', system-ui, sans-serif";
}

function buildOptions(cfg) {
    const pal = palette();
    const colors = (cfg.colors || ['--p']).map(resolveColor);
    const height = cfg.height || 280;

    const common = {
        chart: {
            fontFamily: baseFont(),
            foreColor: pal.muted,
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: { enabled: true, speed: 500 },
        },
        colors,
        tooltip: { theme: pal.tooltipTheme, style: { fontFamily: baseFont() } },
        grid: {
            borderColor: pal.grid,
            strokeDashArray: 4,
            padding: { left: 8, right: 8 },
        },
        dataLabels: { enabled: false },
        legend: {
            show: cfg.legend !== false,
            position: 'top',
            horizontalAlign: 'right',
            fontFamily: baseFont(),
            fontWeight: 700,
            labels: { colors: pal.muted },
            markers: { radius: 12 },
        },
    };

    if (cfg.type === 'bar') {
        return {
            ...common,
            series: cfg.series,
            chart: { ...common.chart, type: 'bar', height },
            plotOptions: {
                bar: { columnWidth: cfg.columnWidth || '42%', borderRadius: 6, borderRadiusApplication: 'end' },
            },
            xaxis: {
                categories: cfg.categories || [],
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: { style: { colors: pal.muted, fontFamily: baseFont() } },
            },
            yaxis: {
                labels: { style: { colors: pal.muted, fontFamily: baseFont() } },
            },
            legend: { ...common.legend, show: (cfg.series || []).length > 1 },
            fill: { opacity: 1 },
        };
    }

    if (cfg.type === 'area' || cfg.type === 'line') {
        return {
            ...common,
            series: cfg.series,
            chart: { ...common.chart, type: cfg.type, height },
            stroke: { curve: 'smooth', width: cfg.type === 'area' ? 2 : 3 },
            fill: cfg.type === 'area'
                ? { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.02, stops: [0, 90, 100] } }
                : { type: 'solid', opacity: 1 },
            markers: { size: 0, hover: { size: 5 } },
            xaxis: {
                categories: cfg.categories || [],
                axisBorder: { show: false },
                axisTicks: { show: false },
                tooltip: { enabled: false },
                labels: { style: { colors: pal.muted, fontFamily: baseFont() } },
            },
            yaxis: {
                labels: { style: { colors: pal.muted, fontFamily: baseFont() } },
            },
        };
    }

    if (cfg.type === 'radialBar') {
        return {
            ...common,
            series: [cfg.value ?? 0],
            chart: { ...common.chart, type: 'radialBar', height },
            plotOptions: {
                radialBar: {
                    startAngle: -120,
                    endAngle: 120,
                    hollow: { size: '62%' },
                    track: { background: pal.track, strokeWidth: '100%', margin: 4 },
                    dataLabels: {
                        name: {
                            offsetY: 26,
                            fontSize: '13px',
                            fontFamily: baseFont(),
                            fontWeight: 700,
                            color: pal.muted,
                        },
                        value: {
                            offsetY: -14,
                            fontSize: '34px',
                            fontFamily: baseFont(),
                            fontWeight: 800,
                            color: pal.text,
                            formatter: (val) => `${Math.round(val)}%`,
                        },
                    },
                },
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'dark',
                    type: 'horizontal',
                    gradientToColors: [resolveColor(cfg.gradientTo || '--teal')],
                    stops: [0, 100],
                },
            },
            stroke: { lineCap: 'round' },
            labels: [cfg.label || ''],
            legend: { show: false },
        };
    }

    if (cfg.type === 'donut') {
        return {
            ...common,
            series: cfg.series || [],
            chart: { ...common.chart, type: 'donut', height },
            labels: cfg.labels || [],
            stroke: { width: 0 },
            plotOptions: {
                pie: { donut: { size: '70%', labels: { show: true, total: { show: true, fontFamily: baseFont() } } } },
            },
            legend: { ...common.legend, position: 'bottom' },
        };
    }

    return { ...common, series: cfg.series || [], chart: { ...common.chart, height } };
}

function wireTabs(key, cfg, chart) {
    if (!Array.isArray(cfg.tabs) || cfg.tabs.length === 0) {
        return;
    }
    const wrap = document.querySelector(`[data-chart-tabs="${key}"]`);
    if (!wrap) {
        return;
    }
    const buttons = Array.from(wrap.querySelectorAll('[data-tab-index]'));
    buttons.forEach((btn) => {
        // Assign via onclick so re-renders (theme change) never stack listeners.
        btn.onclick = () => {
            const i = Number(btn.dataset.tabIndex) || 0;
            const tab = cfg.tabs[i];
            if (!tab) {
                return;
            }
            chart.updateSeries(tab.series, true);
            buttons.forEach((b) => b.classList.toggle('is-active', b === btn));
        };
    });
}

function renderAll() {
    const payloadEl = document.getElementById('dashboard-charts-payload');
    if (!payloadEl) {
        return;
    }

    let payload;
    try {
        payload = JSON.parse(payloadEl.textContent || '{}');
    } catch (_) {
        return;
    }

    // Tear down any existing instances before (re)rendering.
    registry.forEach((chart) => chart.destroy());
    registry.clear();

    Object.entries(payload).forEach(([key, cfg]) => {
        const el = document.querySelector(`[data-chart-key="${key}"]`);
        if (!el || !cfg) {
            return;
        }
        try {
            const chart = new ApexCharts(el, buildOptions(cfg));
            chart.render();
            registry.set(key, chart);
            wireTabs(key, cfg, chart);
        } catch (_) {
            /* ignore a single broken chart */
        }
    });
}

export function initDashboardCharts() {
    if (!document.getElementById('dashboard-charts-payload')) {
        return;
    }
    renderAll();

    let pending = null;
    document.addEventListener('ks:themechange', () => {
        window.clearTimeout(pending);
        pending = window.setTimeout(renderAll, 60);
    });
}
