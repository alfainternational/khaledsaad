import 'package:flutter/material.dart';

import '../design/tokens.dart';

/// ألوان العلامة — مشتقّة من `design/tokens.json` عبر المولَّد.
///
/// كان التعليق هنا يقول: «أي تغيير في الهوية يجب أن يمر على الملفين معًا».
/// وهذه بالضبط القاعدة التي يمنعها INV-10: قاعدةٌ يحفظها إنسانٌ ويطبّقها
/// في موضعين تُنسى مرة، فتتباعد الهويتان بلا أن يقصد أحد. وقد تباعدتا
/// فعلًا — انظر «انحرافٌ معلن» أدناه.
///
/// ما يطابق التوكنز يُفوَّض إليها، فلا يبقى للانحراف موضعٌ جديد.
abstract final class BrandColors {
  // ── مشتقّة من المصدر: تغييرها في tokens.json يصل هنا بالتوليد ──
  static const Color blue = LightTokens.primary;
  static const Color cyan = LightTokens.info;
  static const Color orange = LightTokens.warning;
  static const Color red = LightTokens.danger;
  static const Color surface = LightTokens.surface;

  // ── انحرافٌ معلن ──
  //
  // هذه القيم تخالف التوكنز اليوم. لم تُوحَّد هنا لأن توحيدها يغيّر مظهر
  // التطبيق فعليًّا، وتغييرٌ بصري لا يُدفع بلا فحص على جهاز. مكتوبة صراحةً
  // لتُعدّ وتُغلق، لا لتبقى مخفيّة في أرقامٍ متشابهة.
  //
  //   navy        0A1B33  ←→  DarkTokens.surface      071F5B
  //   ink         0A1B33  ←→  LightTokens.text        0F172A
  //   muted       4A5A72  ←→  LightTokens.textMuted   667085
  //   line        E2E8F2  ←→  LightTokens.border      E3E8EF
  //   surfaceSoft F5F9FF  ←→  LightTokens.surfaceRaised F8FAFC
  //   success     0B7A66  ←→  LightTokens.success     067647
  static const Color navy = Color(0xFF0A1B33);
  static const Color ink = Color(0xFF0A1B33);
  static const Color muted = Color(0xFF4A5A72);
  static const Color line = Color(0xFFE2E8F2);
  static const Color surfaceSoft = Color(0xFFF5F9FF);
  static const Color success = Color(0xFF0B7A66);

  // لا نظير له في التوكنز: خلفية دافئة تخصّ التطبيق وحده.
  static const Color surfaceWarm = Color(0xFFFFF8F2);
}

abstract final class AppTheme {
  static ThemeData build() {
    final base = ThemeData(
      useMaterial3: true,
      brightness: Brightness.light,
      fontFamily: 'IBMPlexSansArabic',
      scaffoldBackgroundColor: BrandColors.surfaceSoft,
      colorScheme: ColorScheme.fromSeed(
        seedColor: BrandColors.blue,
        primary: BrandColors.blue,
        secondary: BrandColors.cyan,
        surface: BrandColors.surface,
      ),
    );

    return base.copyWith(
      appBarTheme: const AppBarTheme(
        backgroundColor: BrandColors.surface,
        foregroundColor: BrandColors.navy,
        elevation: 0,
        centerTitle: false,
      ),
      cardTheme: CardThemeData(
        color: BrandColors.surface,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: const BorderSide(color: BrandColors.line),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: BrandColors.surface,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 14,
          vertical: 14,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(13),
          borderSide: const BorderSide(color: BrandColors.line),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(13),
          borderSide: const BorderSide(color: BrandColors.line),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(13),
          borderSide: const BorderSide(color: BrandColors.blue, width: 2),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: BrandColors.blue,
          foregroundColor: Colors.white,
          minimumSize: const Size.fromHeight(50),
          shape: const StadiumBorder(),
          textStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 16),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: BrandColors.navy,
          minimumSize: const Size.fromHeight(50),
          shape: const StadiumBorder(),
          side: const BorderSide(color: BrandColors.line),
        ),
      ),
      textTheme: base.textTheme.apply(
        bodyColor: BrandColors.ink,
        displayColor: BrandColors.navy,
      ),
    );
  }
}
