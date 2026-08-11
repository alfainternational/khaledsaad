import 'package:flutter/material.dart';

/// نفس الرموز اللونية المعرفة في resources/css/app.css.
/// أي تغيير في الهوية يجب أن يمر على الملفين معًا.
abstract final class BrandColors {
  static const Color cyan = Color(0xFF0B6B78);
  static const Color blue = Color(0xFF134FC4);
  static const Color navy = Color(0xFF0A1B33);
  static const Color orange = Color(0xFFB45309);
  static const Color red = Color(0xFFB42318);

  static const Color ink = Color(0xFF0A1B33);
  static const Color muted = Color(0xFF4A5A72);
  static const Color line = Color(0xFFE2E8F2);
  static const Color surface = Color(0xFFFFFFFF);
  static const Color surfaceSoft = Color(0xFFF5F9FF);
  static const Color surfaceWarm = Color(0xFFFFF8F2);
  static const Color success = Color(0xFF0B7A66);
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
