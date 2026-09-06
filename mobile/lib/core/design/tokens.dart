// مُولَّد من design/tokens.json — لا تعدّله يدويًّا.
// عدّل المصدر وشغّل: npm run tokens:build
import 'package:flutter/material.dart';

class DesignTokens {
  const DesignTokens._();

  // نقاط التوقّف — نفس القيم التي يقرأها الويب، فلا تنقسم الحقيقة.
  static const double bpSM = 480;
  static const double bpMD = 768;
  static const double bpLG = 1024;
  static const double bpXL = 1280;

  // المسافات
  static const double space1 = 4;
  static const double space2 = 8;
  static const double space3 = 12;
  static const double space4 = 16;
  static const double space6 = 24;
  static const double space8 = 32;
  static const double space12 = 48;
  static const double space16 = 64;

  // نصف القطر
  static const double radiusSm = 6;
  static const double radiusMd = 12;
  static const double radiusLg = 20;
}

class LightTokens {
  const LightTokens._();
  static const Color surface = Color(0xFFFFFFFF);
  static const Color surfaceRaised = Color(0xFFF8FAFC);
  static const Color text = Color(0xFF0F172A);
  static const Color textMuted = Color(0xFF667085);
  static const Color primary = Color(0xFF134FC4);
  static const Color primaryContrast = Color(0xFFFFFFFF);
  static const Color border = Color(0xFFE3E8EF);
  static const Color success = Color(0xFF067647);
  static const Color warning = Color(0xFFB45309);
  static const Color danger = Color(0xFFB42318);
  static const Color info = Color(0xFF0B6B78);
}

class DarkTokens {
  const DarkTokens._();
  static const Color surface = Color(0xFF071F5B);
  static const Color surfaceRaised = Color(0xFF0D2A6F);
  static const Color text = Color(0xFFF1F5F9);
  static const Color textMuted = Color(0xFFA9B6CF);
  static const Color primary = Color(0xFF7AA2FF);
  static const Color primaryContrast = Color(0xFF071F5B);
  static const Color border = Color(0xFF1E3A7A);
  static const Color success = Color(0xFF34D399);
  static const Color warning = Color(0xFFFBBF24);
  static const Color danger = Color(0xFFF87171);
  static const Color info = Color(0xFF5EEAD4);
}
