import 'package:flutter/material.dart';

import '../../../app/theme/app_semantic_colors.dart';

/// شارة حالة ملوّنة موحّدة (نشط/متوقف/مكتمل/مؤرشف/معلّقة/معتمدة/مرفوضة...).
///
/// الألوان تُشتق من [AppSemanticColors] لتتكيّف مع الوضعين، ولحالة «مؤرشف»
/// تُستخدم درجة محايدة بنص واضح (لا باهت) لتباين كافٍ.
class StatusBadge extends StatelessWidget {
  const StatusBadge({super.key, required this.status});

  final String status;

  static const _labels = <String, String>{
    'active': 'نشط',
    'paused': 'متوقف',
    'completed': 'مكتمل',
    'archived': 'مؤرشف',
    'draft': 'مسودة',
    'pending': 'معلّقة',
    'approved': 'معتمدة',
    'rejected': 'مرفوضة',
  };

  @override
  Widget build(BuildContext context) {
    final sem = AppSemanticColors.of(context);
    final scheme = Theme.of(context).colorScheme;

    final (Color fg, Color bg) = switch (status) {
      'active' || 'approved' => (sem.success, sem.successContainer),
      'paused' || 'pending' || 'draft' => (sem.warning, sem.warningContainer),
      'rejected' => (sem.danger, sem.dangerContainer),
      'completed' => (sem.info, sem.infoContainer),
      'archived' => (sem.neutral, sem.neutralContainer),
      _ => (scheme.primary, scheme.primaryContainer),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        _labels[status] ?? status,
        style: TextStyle(color: fg, fontSize: 12, fontWeight: FontWeight.w700),
      ),
    );
  }
}
