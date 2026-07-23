import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import 'models.dart';

Color _hex(String value) {
  final cleaned = value.replaceFirst('#', '');
  final parsed = int.tryParse(cleaned, radix: 16) ?? 0x2575ff;
  return Color(0xFF000000 | parsed);
}

/// قسم «المؤشرات في لمحة» — يقابل partials/charts.blade.php على الويب
/// ولوحة المؤشرات في الـPDF: نفس البيانات القادمة من report.charts.
class ReportChartsSection extends StatelessWidget {
  const ReportChartsSection({super.key, required this.charts});

  final ReportChartsModel charts;

  @override
  Widget build(BuildContext context) {
    if (charts.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('المؤشرات في لمحة',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
        const SizedBox(height: 12),
        if (charts.gauge != null) ...[
          BrandCard(child: _GaugeChart(gauge: charts.gauge!)),
          const SizedBox(height: 12),
        ],
        if (charts.severity != null) ...[
          BrandCard(child: _HorizontalBars(series: charts.severity!)),
          const SizedBox(height: 12),
        ],
        if (charts.evidence != null) ...[
          BrandCard(child: _StackedBar(series: charts.evidence!)),
          const SizedBox(height: 12),
        ],
        if (charts.history != null) ...[
          BrandCard(child: _ScoreHistoryChart(history: charts.history!)),
          const SizedBox(height: 12),
        ],
        if (charts.impactEffort != null) ...[
          BrandCard(child: _ImpactEffortMatrix(matrix: charts.impactEffort!)),
          const SizedBox(height: 12),
        ],
      ],
    );
  }
}

class _ChartTitle extends StatelessWidget {
  const _ChartTitle(this.text);

  final String text;

  @override
  Widget build(BuildContext context) =>
      Text(text, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700));
}

/// عدّاد الدرجة: حلقة تتلون حسب النطاق.
class _GaugeChart extends StatelessWidget {
  const _GaugeChart({required this.gauge});

  final ScoreGaugeModel gauge;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _ChartTitle(gauge.title),
        const SizedBox(height: 12),
        Center(
          child: SizedBox(
            width: 132,
            height: 132,
            child: CustomPaint(
              painter: _GaugePainter(
                progress: gauge.max <= 0 ? 0 : gauge.value / gauge.max,
                color: _hex(gauge.colorHex),
                trackColor: BrandColors.line,
              ),
              child: Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text('${gauge.value}',
                        style: const TextStyle(
                            fontSize: 30,
                            fontWeight: FontWeight.w700,
                            color: BrandColors.navy)),
                    Text('/${gauge.max}',
                        style: const TextStyle(fontSize: 12, color: BrandColors.muted)),
                  ],
                ),
              ),
            ),
          ),
        ),
        const SizedBox(height: 10),
        Center(child: SeverityBadge(label: gauge.band, severity: 'low')),
      ],
    );
  }
}

class _GaugePainter extends CustomPainter {
  const _GaugePainter({
    required this.progress,
    required this.color,
    required this.trackColor,
  });

  final double progress;
  final Color color;
  final Color trackColor;

  @override
  void paint(Canvas canvas, Size size) {
    const stroke = 12.0;
    final center = size.center(Offset.zero);
    final radius = (size.shortestSide - stroke) / 2;

    final track = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke
      ..color = trackColor;

    final fill = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke
      ..strokeCap = StrokeCap.round
      ..color = color;

    canvas.drawCircle(center, radius, track);
    canvas.drawArc(
      Rect.fromCircle(center: center, radius: radius),
      -math.pi / 2,
      2 * math.pi * progress.clamp(0.0, 1.0),
      false,
      fill,
    );
  }

  @override
  bool shouldRepaint(_GaugePainter oldDelegate) =>
      oldDelegate.progress != progress || oldDelegate.color != color;
}

/// أشرطة أفقية: توزيع النتائج حسب الخطورة.
class _HorizontalBars extends StatelessWidget {
  const _HorizontalBars({required this.series});

  final ChartSeriesModel series;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _ChartTitle(series.title),
        const SizedBox(height: 12),
        for (final item in series.items)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 5),
            child: Row(
              children: [
                SizedBox(
                  width: 68,
                  child: Text(item.label,
                      style: const TextStyle(fontSize: 13, color: BrandColors.muted)),
                ),
                Expanded(
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(99),
                    child: LinearProgressIndicator(
                      value: series.total <= 0 ? 0 : item.count / series.total,
                      minHeight: 9,
                      backgroundColor: BrandColors.surfaceSoft,
                      valueColor: AlwaysStoppedAnimation<Color>(_hex(item.colorHex)),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                SizedBox(
                  width: 22,
                  child: Text('${item.count}',
                      style: const TextStyle(
                          fontWeight: FontWeight.w700, color: BrandColors.navy)),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

/// شريط مكدس: مدعوم بدليل مقابل اجتهاد.
class _StackedBar extends StatelessWidget {
  const _StackedBar({required this.series});

  final ChartSeriesModel series;

  @override
  Widget build(BuildContext context) {
    final visible = series.items.where((item) => item.count > 0).toList();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _ChartTitle(series.title),
        const SizedBox(height: 12),
        ClipRRect(
          borderRadius: BorderRadius.circular(99),
          child: Row(
            children: [
              for (final item in visible)
                Expanded(
                  flex: item.count,
                  child: Container(
                    height: 22,
                    color: _hex(item.colorHex),
                    alignment: Alignment.center,
                    child: Text('${item.count}',
                        style: const TextStyle(
                            color: Colors.white,
                            fontSize: 12,
                            fontWeight: FontWeight.w700)),
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(height: 10),
        Wrap(
          spacing: 14,
          runSpacing: 6,
          children: [
            for (final item in series.items)
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 10,
                    height: 10,
                    decoration: BoxDecoration(
                      color: _hex(item.colorHex),
                      shape: BoxShape.circle,
                    ),
                  ),
                  const SizedBox(width: 5),
                  Text('${item.label} (${item.count})',
                      style: const TextStyle(fontSize: 12.5, color: BrandColors.muted)),
                ],
              ),
          ],
        ),
      ],
    );
  }
}

/// أعمدة تطور الدرجة عبر التقارير.
class _ScoreHistoryChart extends StatelessWidget {
  const _ScoreHistoryChart({required this.history});

  final ScoreHistoryModel history;

  @override
  Widget build(BuildContext context) {
    const chartHeight = 120.0;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _ChartTitle(history.title),
        const SizedBox(height: 14),
        Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            for (final point in history.points)
              Expanded(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text('${point.value}',
                        style: const TextStyle(
                            fontSize: 12.5,
                            fontWeight: FontWeight.w700,
                            color: BrandColors.navy)),
                    const SizedBox(height: 4),
                    Container(
                      width: 18,
                      height: math.max(
                          6.0,
                          chartHeight *
                              (history.max <= 0 ? 0 : point.value / history.max)),
                      decoration: BoxDecoration(
                        gradient: point.isCurrent
                            ? const LinearGradient(
                                begin: Alignment.topCenter,
                                end: Alignment.bottomCenter,
                                colors: [BrandColors.cyan, BrandColors.blue],
                              )
                            : null,
                        color: point.isCurrent ? null : const Color(0xFF9DB7E8),
                        borderRadius: const BorderRadius.vertical(
                            top: Radius.circular(6)),
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(point.label,
                        style:
                            const TextStyle(fontSize: 11, color: BrandColors.muted),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis),
                  ],
                ),
              ),
          ],
        ),
      ],
    );
  }
}

/// مصفوفة الأثر مقابل الجهد — «ابدأ من الخلية الخضراء».
class _ImpactEffortMatrix extends StatelessWidget {
  const _ImpactEffortMatrix({required this.matrix});

  final ImpactEffortModel matrix;

  static const _levels = ['low', 'medium', 'high'];

  @override
  Widget build(BuildContext context) {
    TableCell header(String text) => TableCell(
          child: Container(
            padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),
            color: BrandColors.surfaceSoft,
            child: Text(text,
                textAlign: TextAlign.center,
                style: const TextStyle(
                    fontSize: 11.5,
                    fontWeight: FontWeight.w600,
                    color: BrandColors.navy)),
          ),
        );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _ChartTitle(matrix.title),
        const SizedBox(height: 12),
        Table(
          border: TableBorder.all(color: BrandColors.line),
          children: [
            TableRow(children: [
              header(''),
              for (final effort in _levels) header(matrix.effortLabels[effort] ?? effort),
            ]),
            for (final impact in ['high', 'medium', 'low'])
              TableRow(children: [
                header(matrix.impactLabels[impact] ?? impact),
                for (final effort in _levels)
                  TableCell(
                    child: Builder(builder: (context) {
                      final count = matrix.countFor(impact, effort);
                      final isHot = impact == 'high' && effort == 'low';

                      return Container(
                        padding: const EdgeInsets.symmetric(vertical: 10),
                        color: isHot ? const Color(0xFFE7F8EF) : null,
                        child: Text(
                          count > 0 ? '$count' : '—',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontWeight:
                                count > 0 ? FontWeight.w700 : FontWeight.w400,
                            color: isHot
                                ? const Color(0xFF0A7D4F)
                                : count > 0
                                    ? BrandColors.navy
                                    : const Color(0xFFB6C1D4),
                          ),
                        ),
                      );
                    }),
                  ),
              ]),
          ],
        ),
        if (matrix.quickWins > 0) ...[
          const SizedBox(height: 8),
          Text(
            'ابدأ من الخلية الخضراء: ${matrix.quickWins} توصية بأثر عالٍ وجهد بسيط.',
            style: const TextStyle(fontSize: 12.5, color: BrandColors.muted),
          ),
        ],
      ],
    );
  }
}
