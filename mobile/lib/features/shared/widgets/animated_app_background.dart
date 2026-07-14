import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../app/theme/app_colors.dart';

class AnimatedAppBackground extends StatefulWidget {
  const AnimatedAppBackground({
    super.key,
    required this.child,
    this.padding = EdgeInsets.zero,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;

  @override
  State<AnimatedAppBackground> createState() => _AnimatedAppBackgroundState();
}

class _AnimatedAppBackgroundState extends State<AnimatedAppBackground>
    with SingleTickerProviderStateMixin, WidgetsBindingObserver {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 12),
    )..repeat();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // أوقف الأنيميشن في الخلفية لتوفير المعالج والبطارية، واستأنفه عند العودة.
    if (state == AppLifecycleState.resumed) {
      if (!_controller.isAnimating) _controller.repeat();
    } else {
      if (_controller.isAnimating) _controller.stop();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Stack(
      children: [
        Positioned.fill(
          child: DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topRight,
                end: Alignment.bottomLeft,
                colors: isDark
                    ? const [AppColors.darkBg, AppColors.darkSurface]
                    : const [AppColors.lightBg, AppColors.lightSurface],
              ),
            ),
          ),
        ),
        Positioned.fill(
          child: IgnorePointer(
            child: RepaintBoundary(
              child: AnimatedBuilder(
                animation: _controller,
                builder: (context, _) => CustomPaint(
                  painter: _BackgroundPainter(
                    progress: _controller.value,
                    isDark: isDark,
                  ),
                ),
              ),
            ),
          ),
        ),
        Padding(padding: widget.padding, child: widget.child),
      ],
    );
  }
}

class _BackgroundPainter extends CustomPainter {
  const _BackgroundPainter({required this.progress, required this.isDark});

  final double progress;
  final bool isDark;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()..style = PaintingStyle.fill;
    final t = progress * math.pi * 2;

    void circle(Color color, Offset base, double radius, double phase) {
      paint.color = color;
      final offset = Offset(
        math.sin(t + phase) * size.width * 0.035,
        math.cos(t + phase) * size.height * 0.025,
      );
      canvas.drawCircle(base + offset, radius, paint);
    }

    circle(
      AppColors.primary.withValues(alpha: isDark ? 0.16 : 0.13),
      Offset(size.width * 0.18, size.height * 0.10),
      size.shortestSide * 0.28,
      0,
    );
    circle(
      AppColors.info.withValues(alpha: isDark ? 0.12 : 0.10),
      Offset(size.width * 0.82, size.height * 0.22),
      size.shortestSide * 0.20,
      1.8,
    );
    circle(
      AppColors.success.withValues(alpha: isDark ? 0.10 : 0.08),
      Offset(size.width * 0.78, size.height * 0.86),
      size.shortestSide * 0.26,
      3.2,
    );
  }

  @override
  bool shouldRepaint(covariant _BackgroundPainter oldDelegate) {
    return oldDelegate.progress != progress || oldDelegate.isDark != isDark;
  }
}
