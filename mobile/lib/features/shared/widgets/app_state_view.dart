import 'package:flutter/material.dart';

/// عرض موحّد لحالات الشاشة: تحميل / فارغ / خطأ + إعادة محاولة.
class AppStateView extends StatelessWidget {
  const AppStateView._({
    this.icon,
    this.title,
    this.message,
    this.onRetry,
    this.isLoading = false,
  });

  final IconData? icon;
  final String? title;
  final String? message;
  final VoidCallback? onRetry;
  final bool isLoading;

  factory AppStateView.loading() => const AppStateView._(isLoading: true);

  factory AppStateView.empty({
    IconData icon = Icons.inbox_outlined,
    required String title,
    String? message,
  }) =>
      AppStateView._(icon: icon, title: title, message: message);

  factory AppStateView.error({
    String title = 'حدث خطأ',
    String? message,
    VoidCallback? onRetry,
  }) =>
      AppStateView._(
        icon: Icons.error_outline,
        title: title,
        message: message,
        onRetry: onRetry,
      );

  @override
  Widget build(BuildContext context) {
    if (isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    final theme = Theme.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (icon != null)
              Icon(icon, size: 56, color: theme.colorScheme.primary),
            const SizedBox(height: 16),
            if (title != null)
              Text(
                title!,
                style: theme.textTheme.titleMedium
                    ?.copyWith(fontWeight: FontWeight.w700),
                textAlign: TextAlign.center,
              ),
            if (message != null) ...[
              const SizedBox(height: 8),
              Text(
                message!,
                style: theme.textTheme.bodyMedium,
                textAlign: TextAlign.center,
              ),
            ],
            if (onRetry != null) ...[
              const SizedBox(height: 20),
              FilledButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh),
                label: const Text('إعادة المحاولة'),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
