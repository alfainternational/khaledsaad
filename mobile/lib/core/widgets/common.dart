import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

/// مكونات مشتركة تقابل أصناف CSS في الويب:
/// .card / .score-chip / .badge / .empty / .alert
class BrandCard extends StatelessWidget {
  const BrandCard({super.key, required this.child, this.onTap, this.muted = false});

  final Widget child;
  final VoidCallback? onTap;
  final bool muted;

  @override
  Widget build(BuildContext context) {
    return Opacity(
      opacity: muted ? 0.72 : 1,
      child: Card(
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Padding(padding: const EdgeInsets.all(18), child: child),
        ),
      ),
    );
  }
}

class Eyebrow extends StatelessWidget {
  const Eyebrow(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) => Text(
        text,
        style: const TextStyle(
          color: BrandColors.muted,
          fontSize: 12,
          fontWeight: FontWeight.w600,
          letterSpacing: 0.4,
        ),
      );
}

class ScoreChip extends StatelessWidget {
  const ScoreChip({super.key, required this.label});

  final String label;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
        decoration: BoxDecoration(
          color: BrandColors.surfaceSoft,
          border: Border.all(color: BrandColors.line),
          borderRadius: BorderRadius.circular(999),
        ),
        child: Text(
          label,
          style: const TextStyle(color: BrandColors.navy, fontWeight: FontWeight.w600, fontSize: 13),
        ),
      );
}

class SeverityBadge extends StatelessWidget {
  const SeverityBadge({super.key, required this.label, required this.severity});

  final String label;
  final String severity;

  @override
  Widget build(BuildContext context) {
    final (background, foreground) = switch (severity) {
      'critical' => (const Color(0xFFFDECEB), const Color(0xFF8D1D13)),
      'high' => (const Color(0xFFFFF2E6), const Color(0xFF8A4B06)),
      'medium' => (const Color(0xFFFFF9E6), const Color(0xFF6D5405)),
      'assumption' => (const Color(0xFFF2F0FF), const Color(0xFF40339C)),
      _ => (BrandColors.surfaceSoft, BrandColors.muted),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(color: foreground, fontSize: 12, fontWeight: FontWeight.w600),
      ),
    );
  }
}

class EmptyState extends StatelessWidget {
  const EmptyState({super.key, required this.title, required this.message, this.action});

  final String title;
  final String message;
  final Widget? action;

  @override
  Widget build(BuildContext context) => Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 44),
        decoration: BoxDecoration(
          color: BrandColors.surface,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: BrandColors.line, style: BorderStyle.solid),
        ),
        child: Column(
          children: [
            Text(title, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
            const SizedBox(height: 8),
            Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(color: BrandColors.muted),
            ),
            if (action != null) ...[const SizedBox(height: 16), action!],
          ],
        ),
      );
}

class ErrorNotice extends StatelessWidget {
  const ErrorNotice({super.key, required this.message, this.onRetry});

  final String message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) => Container(
        width: double.infinity,
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: const Color(0xFFFDECEB),
          border: Border.all(color: const Color(0xFFF6C4C0)),
          borderRadius: BorderRadius.circular(13),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(message, style: const TextStyle(color: Color(0xFF8D1D13))),
            if (onRetry != null) ...[
              const SizedBox(height: 12),
              OutlinedButton(onPressed: onRetry, child: const Text('أعد المحاولة')),
            ],
          ],
        ),
      );
}

class BigScore extends StatelessWidget {
  const BigScore({super.key, required this.score, required this.band, this.delta});

  final int score;
  final String band;
  final String? delta;

  @override
  Widget build(BuildContext context) => Column(
        children: [
          const Eyebrow('درجة الجاهزية'),
          const SizedBox(height: 6),
          RichText(
            text: TextSpan(
              style: const TextStyle(
                fontFamily: 'HacenTunisia',
                fontSize: 52,
                fontWeight: FontWeight.w700,
                color: BrandColors.navy,
              ),
              children: [
                TextSpan(text: '$score'),
                const TextSpan(
                  text: '/100',
                  style: TextStyle(fontSize: 18, color: BrandColors.muted, fontWeight: FontWeight.w500),
                ),
              ],
            ),
          ),
          const SizedBox(height: 8),
          ScoreChip(label: band),
          if (delta != null) ...[
            const SizedBox(height: 8),
            Text(
              delta!,
              textAlign: TextAlign.center,
              style: const TextStyle(fontWeight: FontWeight.w600, color: BrandColors.success),
            ),
          ],
        ],
      );
}

/// حالة تحميل موحدة: لا سبينر بلا نهاية ولا ابتلاع للأخطاء.
class AsyncView<T> extends StatelessWidget {
  const AsyncView({
    super.key,
    required this.snapshot,
    required this.builder,
    required this.onRetry,
  });

  final AsyncSnapshot<T> snapshot;
  final Widget Function(T data) builder;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    if (snapshot.connectionState == ConnectionState.waiting) {
      return const Center(child: Padding(padding: EdgeInsets.all(40), child: CircularProgressIndicator()));
    }

    if (snapshot.hasError) {
      return Padding(
        padding: const EdgeInsets.all(16),
        child: ErrorNotice(message: snapshot.error.toString(), onRetry: onRetry),
      );
    }

    if (!snapshot.hasData) {
      return Padding(
        padding: const EdgeInsets.all(16),
        child: ErrorNotice(message: 'لا توجد بيانات لعرضها.', onRetry: onRetry),
      );
    }

    return builder(snapshot.data as T);
  }
}
