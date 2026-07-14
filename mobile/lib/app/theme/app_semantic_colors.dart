import 'package:flutter/material.dart';

import 'app_colors.dart';

/// امتداد ألوان دلالية موحّد (نجاح/تحذير/خطر/معلومة) يتكيّف مع الوضعين.
///
/// المصدر الوحيد لألوان الحالات في كل التطبيق — بديل عن أي ألوان hex
/// مبعثرة داخل الشاشات (status badges, severity, completeness...).
@immutable
class AppSemanticColors extends ThemeExtension<AppSemanticColors> {
  const AppSemanticColors({
    required this.success,
    required this.onSuccess,
    required this.successContainer,
    required this.warning,
    required this.onWarning,
    required this.warningContainer,
    required this.danger,
    required this.onDanger,
    required this.dangerContainer,
    required this.info,
    required this.onInfo,
    required this.infoContainer,
    required this.neutral,
    required this.neutralContainer,
  });

  final Color success;
  final Color onSuccess;
  final Color successContainer;
  final Color warning;
  final Color onWarning;
  final Color warningContainer;
  final Color danger;
  final Color onDanger;
  final Color dangerContainer;
  final Color info;
  final Color onInfo;
  final Color infoContainer;

  /// لون محايد للحالات الخاملة (مثل "مؤرشف") بتباين كافٍ في الوضعين.
  final Color neutral;
  final Color neutralContainer;

  static const light = AppSemanticColors(
    success: AppColors.success,
    onSuccess: Colors.white,
    successContainer: Color(0xFFDCFCE7),
    warning: AppColors.warning,
    onWarning: Colors.white,
    warningContainer: Color(0xFFFEF3C7),
    danger: AppColors.danger,
    onDanger: Colors.white,
    dangerContainer: Color(0xFFFEE2E2),
    info: AppColors.info,
    onInfo: Colors.white,
    infoContainer: Color(0xFFE0F2FE),
    neutral: Color(0xFF475569),
    neutralContainer: Color(0xFFE2E8F0),
  );

  static const dark = AppSemanticColors(
    success: Color(0xFF4ADE80),
    onSuccess: Color(0xFF052E16),
    successContainer: Color(0xFF14532D),
    warning: Color(0xFFFBBF24),
    onWarning: Color(0xFF422006),
    warningContainer: Color(0xFF713F12),
    danger: Color(0xFFF87171),
    onDanger: Color(0xFF450A0A),
    dangerContainer: Color(0xFF7F1D1D),
    info: Color(0xFF38BDF8),
    onInfo: Color(0xFF082F49),
    infoContainer: Color(0xFF075985),
    neutral: Color(0xFFCBD5E1),
    neutralContainer: Color(0xFF334155),
  );

  /// وصول مختصر من السياق.
  static AppSemanticColors of(BuildContext context) =>
      Theme.of(context).extension<AppSemanticColors>() ?? light;

  @override
  AppSemanticColors copyWith({
    Color? success,
    Color? onSuccess,
    Color? successContainer,
    Color? warning,
    Color? onWarning,
    Color? warningContainer,
    Color? danger,
    Color? onDanger,
    Color? dangerContainer,
    Color? info,
    Color? onInfo,
    Color? infoContainer,
    Color? neutral,
    Color? neutralContainer,
  }) {
    return AppSemanticColors(
      success: success ?? this.success,
      onSuccess: onSuccess ?? this.onSuccess,
      successContainer: successContainer ?? this.successContainer,
      warning: warning ?? this.warning,
      onWarning: onWarning ?? this.onWarning,
      warningContainer: warningContainer ?? this.warningContainer,
      danger: danger ?? this.danger,
      onDanger: onDanger ?? this.onDanger,
      dangerContainer: dangerContainer ?? this.dangerContainer,
      info: info ?? this.info,
      onInfo: onInfo ?? this.onInfo,
      infoContainer: infoContainer ?? this.infoContainer,
      neutral: neutral ?? this.neutral,
      neutralContainer: neutralContainer ?? this.neutralContainer,
    );
  }

  @override
  AppSemanticColors lerp(ThemeExtension<AppSemanticColors>? other, double t) {
    if (other is! AppSemanticColors) return this;
    return AppSemanticColors(
      success: Color.lerp(success, other.success, t)!,
      onSuccess: Color.lerp(onSuccess, other.onSuccess, t)!,
      successContainer: Color.lerp(successContainer, other.successContainer, t)!,
      warning: Color.lerp(warning, other.warning, t)!,
      onWarning: Color.lerp(onWarning, other.onWarning, t)!,
      warningContainer: Color.lerp(warningContainer, other.warningContainer, t)!,
      danger: Color.lerp(danger, other.danger, t)!,
      onDanger: Color.lerp(onDanger, other.onDanger, t)!,
      dangerContainer: Color.lerp(dangerContainer, other.dangerContainer, t)!,
      info: Color.lerp(info, other.info, t)!,
      onInfo: Color.lerp(onInfo, other.onInfo, t)!,
      infoContainer: Color.lerp(infoContainer, other.infoContainer, t)!,
      neutral: Color.lerp(neutral, other.neutral, t)!,
      neutralContainer: Color.lerp(neutralContainer, other.neutralContainer, t)!,
    );
  }
}
