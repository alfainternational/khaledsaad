import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';

import '../../../app/theme/app_semantic_colors.dart';

/// تغذية راجعة موحّدة عبر التطبيق: رسائل نجاح/خطأ + اهتزاز خفيف،
/// لتوحيد أنماط الأخطاء المتفرقة (snackbar/inline/AppStateView).
class UiFeedback {
  const UiFeedback._();

  static void success(String message, {String title = 'تم'}) {
    HapticFeedback.lightImpact();
    final sem = AppSemanticColors.of(Get.context!);
    Get.snackbar(
      title,
      message,
      snackPosition: SnackPosition.BOTTOM,
      backgroundColor: sem.successContainer,
      colorText: Get.theme.colorScheme.onSurface,
      margin: const EdgeInsets.all(12),
      borderRadius: 12,
      duration: const Duration(seconds: 3),
    );
  }

  static void error(String message, {String title = 'حدث خطأ'}) {
    HapticFeedback.mediumImpact();
    final sem = AppSemanticColors.of(Get.context!);
    Get.snackbar(
      title,
      message,
      snackPosition: SnackPosition.BOTTOM,
      backgroundColor: sem.dangerContainer,
      colorText: Get.theme.colorScheme.onSurface,
      margin: const EdgeInsets.all(12),
      borderRadius: 12,
      duration: const Duration(seconds: 4),
    );
  }

  /// تراجع اختياري بعد إجراء مدمّر.
  static void undoable(String message, {required VoidCallback onUndo}) {
    HapticFeedback.lightImpact();
    Get.snackbar(
      'تم',
      message,
      snackPosition: SnackPosition.BOTTOM,
      margin: const EdgeInsets.all(12),
      borderRadius: 12,
      duration: const Duration(seconds: 5),
      mainButton: TextButton(
        onPressed: () {
          if (Get.isSnackbarOpen) Get.closeCurrentSnackbar();
          onUndo();
        },
        child: const Text('تراجع'),
      ),
    );
  }
}

/// زر نسخ إلى الحافظة قابل لإعادة الاستخدام مع تأكيد وتغذية راجعة.
class CopyIconButton extends StatelessWidget {
  const CopyIconButton({
    super.key,
    required this.text,
    this.tooltip = 'نسخ',
  });

  final String text;
  final String tooltip;

  @override
  Widget build(BuildContext context) {
    return IconButton(
      tooltip: tooltip,
      icon: const Icon(Icons.copy_all_outlined),
      onPressed: () async {
        await Clipboard.setData(ClipboardData(text: text));
        UiFeedback.success('نُسخ إلى الحافظة');
      },
    );
  }
}
