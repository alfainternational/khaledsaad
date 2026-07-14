import 'package:flutter/material.dart';

import 'skeleton.dart';

/// عرض موحّد لحالات الشاشة: تحميل / فارغ / خطأ + إعادة محاولة.
class AppStateView extends StatelessWidget {
  const AppStateView._({
    this.icon,
    this.title,
    this.message,
    this.onRetry,
    this.actionLabel,
    this.onAction,
    this.isLoading = false,
    this.useSkeleton = false,
  });

  final IconData? icon;
  final String? title;
  final String? message;
  final VoidCallback? onRetry;

  /// إجراء أساسي اختياري في حالة الفراغ (مثل: أنشئ مشروعاً).
  final String? actionLabel;
  final VoidCallback? onAction;
  final bool isLoading;
  final bool useSkeleton;

  factory AppStateView.loading({String? message}) =>
      AppStateView._(isLoading: true, message: message);

  /// تحميل بهيئة هيكلية (Skeleton) لإحساس أسرع في القوائم.
  factory AppStateView.skeleton() =>
      const AppStateView._(isLoading: true, useSkeleton: true);

  factory AppStateView.empty({
    IconData icon = Icons.inbox_outlined,
    required String title,
    String? message,
    String? actionLabel,
    VoidCallback? onAction,
  }) =>
      AppStateView._(
        icon: icon,
        title: title,
        message: message,
        actionLabel: actionLabel,
        onAction: onAction,
      );

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
      if (useSkeleton) return const SkeletonList();
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const CircularProgressIndicator(),
            if (message != null) ...[
              const SizedBox(height: 16),
              Text(message!, textAlign: TextAlign.center),
            ],
          ],
        ),
      );
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
            if (onAction != null && actionLabel != null) ...[
              const SizedBox(height: 20),
              FilledButton.icon(
                onPressed: onAction,
                icon: const Icon(Icons.add),
                label: Text(actionLabel!),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
