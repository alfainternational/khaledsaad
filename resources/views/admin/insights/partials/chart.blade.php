{{--
    الرسم الزمني — SVG مولَّد من الأرقام نفسها، بلا مكتبة رسم.

    قاعدة §١٣: لا رسم بياني لأقل من أربع نقاط زمنية. ثلاث نقاط ترسم خطًّا
    صاعدًا أو هابطًا مهما كانت البيانات، فتقول اتجاهًا لا وجود له.

    ما هنا هندسة إحداثيات لا حساب مقياس: الأرقام تصل محسوبة من
    InsightsReport، وهذا يحوّلها إلى نقاط على مستطيل.

    @param array $timeline   [['date','label','sessions','visitors','page_views','avg_seconds'],…]
    @param string $metric    المفتاح المرسوم
--}}
@php
    $points = collect($timeline);
    $key = $metric ?? 'sessions';
    $peak = max(1, (int) $points->max($key));
    $width = 1000;
    $height = 220;
    $padBottom = 26;
    $step = $points->count() > 1 ? $width / ($points->count() - 1) : $width;

    $coords = $points->values()->map(function ($point, $index) use ($key, $peak, $step, $height, $padBottom) {
        $x = round($index * $step, 2);
        $y = round(($height - $padBottom) - (($point[$key] / $peak) * ($height - $padBottom - 8)), 2);

        return ['x' => $x, 'y' => $y, 'point' => $point];
    });

    $line = $coords->map(fn ($c) => $c['x'].','.$c['y'])->implode(' ');
    $area = $line !== ''
        ? '0,'.($height - $padBottom).' '.$line.' '.$coords->last()['x'].','.($height - $padBottom)
        : '';
@endphp

@if ($points->count() < 4)
    <p class="muted">لا يُرسم اتجاه من {{ $points->count() }} نقاط. المدة القصيرة تُقرأ من الجدول لا من الخط.</p>
@else
    <svg class="insights-chart" viewBox="0 0 {{ $width }} {{ $height }}"
         role="img" aria-label="{{ __('اتجاه :metric خلال :days يومًا — الذروة :peak', [
             'metric' => $label ?? __('الجلسات'),
             'days' => $points->count(),
             'peak' => $peak,
         ]) }}">
        {{-- خطوط إرشادية: الربع والنصف والثلاثة أرباع من الذروة --}}
        @foreach ([0.25, 0.5, 0.75] as $ratio)
            <line class="insights-chart__grid" x1="0" x2="{{ $width }}"
                  y1="{{ ($height - $padBottom) * (1 - $ratio) }}" y2="{{ ($height - $padBottom) * (1 - $ratio) }}" />
        @endforeach

        <polygon class="insights-chart__area" points="{{ $area }}" />
        <polyline class="insights-chart__line" points="{{ $line }}" vector-effect="non-scaling-stroke" />

        @foreach ($coords as $coord)
            <circle class="insights-chart__dot" cx="{{ $coord['x'] }}" cy="{{ $coord['y'] }}" r="2.5">
                <title>{{ $coord['point']['label'] }} — {{ $coord['point'][$key] }}</title>
            </circle>
        @endforeach
    </svg>

    <div class="chart-legend" aria-hidden="true">
        <span>{{ $points->first()['label'] }}</span>
        <span style="margin-inline-start:auto">الذروة {{ number_format($peak) }} · {{ $points->last()['label'] }}</span>
    </div>
@endif
