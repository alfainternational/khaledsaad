import 'package:flutter/material.dart';

/// شارة حالة ملوّنة موحّدة (نشط/متوقف/مكتمل/مؤرشف...).
class StatusBadge extends StatelessWidget {
  const StatusBadge({super.key, required this.status});

  final String status;

  static const _labels = <String, String>{
    'active': 'نشط',
    'paused': 'متوقف',
    'completed': 'مكتمل',
    'archived': 'مؤرشف',
    'draft': 'مسودة',
  };

  Color _color(BuildContext context) {
    switch (status) {
      case 'active':
        return const Color(0xFF16A34A);
      case 'paused':
        return const Color(0xFFD97706);
      case 'completed':
        return const Color(0xFF0EA5E9);
      case 'archived':
        return Theme.of(context).colorScheme.outline;
      default:
        return Theme.of(context).colorScheme.primary;
    }
  }

  @override
  Widget build(BuildContext context) {
    final color = _color(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        _labels[status] ?? status,
        style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w700),
      ),
    );
  }
}
