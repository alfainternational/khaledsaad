import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../data/services/app_lock_service.dart';

/// حاجب يغطّي التطبيق عندما يكون القفل البيومتري مفعّلاً والتطبيق مقفولاً،
/// حتى يؤكّد المستخدم هويته. يُركّب فوق كل الشاشات في جذر التطبيق.
class AppLockGate extends StatelessWidget {
  const AppLockGate({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<AppLockService>()) return const SizedBox.shrink();
    final lock = Get.find<AppLockService>();
    final theme = Theme.of(context);

    return Obx(() {
      if (!lock.enabled.value || !lock.locked.value) {
        return const SizedBox.shrink();
      }
      return Positioned.fill(
        child: Material(
          color: theme.colorScheme.surface,
          child: Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.lock_outline,
                    size: 56, color: theme.colorScheme.primary),
                const SizedBox(height: 16),
                Text('التطبيق مقفول',
                    style: theme.textTheme.titleMedium
                        ?.copyWith(fontWeight: FontWeight.w800)),
                const SizedBox(height: 8),
                Text('أكّد هويتك للمتابعة',
                    style: theme.textTheme.bodyMedium),
                const SizedBox(height: 20),
                FilledButton.icon(
                  onPressed: lock.unlock,
                  icon: const Icon(Icons.fingerprint),
                  label: const Text('افتح'),
                ),
              ],
            ),
          ),
        ),
      );
    });
  }
}
